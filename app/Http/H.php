<?php

namespace App\Http;

use App\Dicts\CachePrefixes;
use App\Dicts\DocsMapping;
use App\Dicts\MessageTypes;
use App\Dicts\MimeType;
use App\Dicts\SchedulesTag;
use App\Dicts\SettingDataTypes;
use App\Dicts\SettingIds;
use App\Http\Controllers\PortForwardController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SitesVisitorController;
use App\Http\Controllers\UtilsController;
use App\Models\InternalRequest;
use App\Models\P2pMessage;
use App\Models\PassiveSession;
use App\Models\Peer;
use App\Models\Setting;
use App\Models\Site;
use App\Models\SitePeer;
use Base32\Base32;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleTor\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\SerializableClosure\SerializableClosure;
use Pdp\Domain;
use Pdp\TopLevelDomains;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Spatie\Url\Url;
use stdClass;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Throwable;
use ZipArchive;

/* General purpose reusable utilities class */
class H
{
    public static function ver()
    {
        $ver = File::get(base_path('resources/includes/sn-client-version.json'));

        return json_decode($ver)->version;
    }

    public static function getBundleVersion(): ?object
    {
        $bundle_version_file_path = dirname(base_path()).'/frankenphp/sn-bundle-version.json';
        $bundle_version_file_exists = File::exists($bundle_version_file_path);
        if ($bundle_version_file_exists) {
            $bundle_version_file_content = File::get($bundle_version_file_path);
            $bundle_version = json_decode($bundle_version_file_content);

            return $bundle_version;
        } else {
            /* Not a client bundle */
            return null;
        }
    }

    public static function helpLink($doc_tag)
    {
        if (! $doc_tag) {
            $doc_tag = DocsMapping::about_serveronet;
        }
        $title = DocsMapping::mapping[$doc_tag]['label'];
        $link = route('documentation', compact('doc_tag'));

        return ['title' => $title, 'link' => $link];
    }

    public static function enqueueP2pMessage($messageType, $payload = null, $site_id = null, $priority = null, $andSend = true)
    {
        info('enqueueP2pMessage andSend: '.$andSend);
        $json_payload = json_encode($payload);

        $self_address = H::getPublicSelfAddress();
        $msg = new P2pMessage;
        $msg->type = $messageType;
        $msg->message_id = Str::random(40);
        $msg->json_payload = $json_payload;
        $msg->sender_address = $self_address;
        $msg->site_id = $site_id;
        $msg->priority = $priority;
        $msg->self_origin = true;
        $msg->consumed_at = now();
        $msg->save();

        if (! $andSend) {
            return;
        }

        $tag = SchedulesTag::Send_Pending_Messages;
        $requestConfigSet = [];
        $requestConfigSet['tag'] = $tag;

        H::dispatchInternalAsync('execute_client_action_wrapper', $requestConfigSet);
    }

    public static function isInternalCallCurrent(array $requestConfigSet): bool
    {
        $ts = $requestConfigSet['ts'] ?? null;

        if (! $ts) {
            return false;
        }

        $ts = Carbon::parse($ts);

        if ($ts->diffInSeconds(now()) > 10) {
            return false;
        }

        return true;
    }

    public static function dispatchInternalAsyncClosureWrapper($closure): void
    {
        info('dispatchInternalAsyncClosureWrapper');

        $secret_key_internal_calls = H::getSettVal(SettingIds::secret_key_internal_calls);
        SerializableClosure::setSecretKey($secret_key_internal_calls);

        $serialized_closure = serialize(new SerializableClosure($closure));
        $internalClosureExecutionId = Str::random(40);
        Cache::put(CachePrefixes::internal_closure_.$internalClosureExecutionId, "", 10);
        $requestConfigSet = [];
        $requestConfigSet['serialized_closure'] = $serialized_closure;
        $requestConfigSet['internalClosureExecutionId'] = $internalClosureExecutionId;
        H::dispatchInternalAsync('handle_internal_closure', $requestConfigSet);
    }

    /* Dispatches by route path after prefix internal/ */
    public static function dispatchInternalAsync($route_path, $requestConfigSet, $timeout = 0.15): void
    {
        info('dispatchInternalAsync '.$route_path);
        $requestConfigSet['ts'] = now()->toDateTimeString();

        try {
            $internalUrl = H::a(config('app.url')).'internal/'.$route_path;
            info('$internalUrl '.$internalUrl);

            $client = H::setupClient(false);
            $options = [
                'timeout' => $timeout,
                'connect_timeout' => 1,
                'form_params' => ['requestConfigSet' => encrypt(json_encode($requestConfigSet))],
            ];
            H::prepareOptions($options, $internalUrl);

            $client->post($internalUrl, $options);

        } catch (ConnectException $e) {
            info('dispatchInternalAsync ConnectException '.$route_path.' '.Str::limit($e->getMessage(), 40));
        } catch (Throwable $th) {
            info('dispatchInternalAsync $th '.$th->getMessage().' '.$th->getFile().' '.$th->getLine());
        }
    }

    public static function unwrapRequestConfigSet(): array
    {
        try {
            request()->validate([
                'requestConfigSet' => ['required', 'string', 'max:'.Consts::recordJsonMaxSizeBytes],
            ]);
        } catch (Throwable $th) {
            info('unwrapRequestConfigSet Validation Failed: '.$th->getMessage());
            abort(400);
        }

        try {
            $requestConfigSet = json_decode(decrypt(request()->requestConfigSet), true);
        } catch (Throwable $th) {
            info('unwrapRequestConfigSet Unwrap Failed: '.$th->getMessage());
            abort(400);
        }

        return $requestConfigSet;
    }

    public static function startInteralRequestReporting($routeName): string
    {
        $internalRequestId = Str::random();
        $internalRequest = new InternalRequest;
        $internalRequest->route = $routeName;
        $internalRequest->request_id = $internalRequestId;
        $internalRequest->started_at = now();
        $internalRequest->save();

        return $internalRequestId;
    }

    public static function endInteralRequestReporting($internalRequestId): void
    {
        $internalRequest = InternalRequest::where('request_id', $internalRequestId)->first();
        if (! $internalRequest) {
            return;
        }

        $internalRequest->ended_at = now();
        $taken_seconds = Carbon::parse($internalRequest->started_at)->diffInSeconds(now());
        $internalRequest->taken_seconds = round($taken_seconds, 2);
        $internalRequest->save();

        $oldInternalRequests = InternalRequest::where('started_at', '<', now()->subDay())->take(1000);
        $oldInternalRequests->delete();
    }

    public static function savePassiveTriggersForSite($site_id): void
    {
        $livePassiveSessionsForSite = PassiveSession::where('last_heartbeat_at', '>', now()->subMinutes(1)->toDateTimeString())
            ->where('site_ids_json', 'like', '%'.$site_id.'%')->get();

        foreach ($livePassiveSessionsForSite as $key => $passiveSession) {
            $file_name = $site_id.'_'.$passiveSession->passive_token;
            if (! Storage::disk('passive_triggers')->exists($file_name.'.txt')) {
                Storage::disk('passive_triggers')->put($file_name.'.txt', 'true');
            }
        }
    }

    public static function isFirstRunCompleted()
    {
        $first_run_completed = false;

        if (Storage::disk('local')->exists('first_run_completed.php')) {
            $first_run_completed = true;
        }

        return $first_run_completed;
    }

    public static function isServeronetWelcomeSite(): bool
    {
        return Storage::disk('local')->exists('welcome_site.php');
    }

    public static function isDevNode(): bool
    {
        return Storage::disk('local')->exists('isDevNode.php');
    }

    /* Client address is standarized to end with slash and have schema */
    public static function a($client_address, $generalUrlStandarisation = false, $https = false)
    {
        if (! $generalUrlStandarisation) {
            if (! Str::endsWith($client_address, '/')) {
                $client_address = $client_address.'/';
            }
        }
        if (! Str::startsWith($client_address, 'http')) {
            if ($https) {
                $client_address = 'https://'.$client_address;
            } else {
                $client_address = 'http://'.$client_address;
            }
        }

        return $client_address;
    }

    public static function getSettVal($setting_id)
    {
        $ttl = App::isProduction() ? 60 : 5;
        $setting = Cache::remember(CachePrefixes::setting_.$setting_id, $ttl, function () use ($setting_id) {
            return Setting::where('setting_id', $setting_id)->first();
        });

        if (! $setting) {
            $setting = new Setting;
            $setting->setting_id = $setting_id;
            $default = SettingsController::getDefaultForSetting($setting_id);
            $setting->value = $default['value'];
            $setting->type = $default['type'];
            $setting->save();
        } else {
            $setting->type = SettingsController::getDefaultForSetting($setting->setting_id)['type'];
        }

        $value = null;

        switch ($setting->type) {
            case SettingDataTypes::datetime:
                $value = Carbon::parse($setting->value);
                break;

            case SettingDataTypes::string:
                $value = $setting->value;
                break;

            case SettingDataTypes::boolean:
                $value = (bool) $setting->value;
                break;

            case SettingDataTypes::integer:
                $value = (int) $setting->value;
                break;

            default:
                $value = $setting->value;
                break;
        }

        return $value;
    }

    public static function setSettingValue($setting_id, $value)
    {
        $value = trim($value);
        $setting = Setting::where('setting_id', $setting_id)->first();

        if (! $setting) {
            $setting = new Setting;
            $setting->setting_id = $setting_id;
            $setting->value = $value;
            $default = SettingsController::getDefaultForSetting($setting_id);
            $setting->type = $default['type'];
        } else {
            $setting->value = $value;
        }

        $setting->save();

        $ttl = App::isProduction() ? 60 : 5;
        Cache::remember(CachePrefixes::setting_.$setting_id, $ttl, function () use ($setting_id) {
            return Setting::where('setting_id', $setting_id)->first();
        });
    }

    /* Generates string from from binary verification key */
    public static function verKeyToHashedId(string $binaryVerificationKey)
    {
        /* Hash of binary verification key */
        $binaryHashOfverificationKey = hash('sha256', $binaryVerificationKey, true);
        /* Base32 encode for subdomain usage */
        $base32encodedbinaryHashOfVerificationKey = Base32::encode($binaryHashOfverificationKey);
        /* Lowercase for looks and subdomain usage */
        $lowerCased = strtolower($base32encodedbinaryHashOfVerificationKey);
        /* Remove all equals sings for use in subdomain name */
        $withoutEquals = Str::replace('=', '', $lowerCased);

        return $withoutEquals;
    }

    public static function isValidHashedId(string $hashedId): bool
    {
        /*
        * SHA-256 = 256 bits.
        * Base32 representation without padding = 52 characters.
        * Lowercase RFC 4648 Base32 alphabet = a-z, 2-7.
        */
        return preg_match('/^[a-z2-7]{52}$/', $hashedId) === 1;
    }

    public static function getUriLink($site): string
    {
        $protocol = 'serveronet:';
        $site_id_property = 'site_id='.urlencode($site->site_id);
        $title_property = '&title='.urlencode($site->latest_site_definitions_title ?? $site->title ?? $site->most_recent_site_definition->title ?? $model->title_draft ?? 'title missing');

        $peers_property = '';

        $site_peer_client_address = H::getCached(
            CachePrefixes::site_peer_client_address_.$site->site_id,
            SitePeer::whereSiteId($site->site_id)->select('client_address')->withoutSelf()->withoutArchived()->take(10),
            isCollection: true
        );

        if ($site->is_hosted) {
            $site_peer_client_address->push(new Peer(['client_address' => H::getPublicSelfAddress()]));
        }

        $site_peer_client_address = $site_peer_client_address->pluck('client_address')->toArray();

        $peerClientAddresses = [];
        foreach ($site_peer_client_address as $key => $client_address) {
            $peerClientAddresses[] = $client_address;
        }

        $peerClientAddresses = array_unique($peerClientAddresses);
        foreach ($peerClientAddresses as $key => $peerClientAddress) {
            $peers_property .= '&peer='.urlencode($peerClientAddress);
        }

        $uri = $protocol.$site_id_property.$title_property.$peers_property;

        return $uri;
    }

    public static function getShortSiteID($site_id, $withUnderscore = false): string
    {
        $connectChar = $withUnderscore ? '_' : '-';

        return substr($site_id, 0, 5).$connectChar.substr($site_id, strlen($site_id) - 5, 5);
    }

    public static function echoHeaderWithViewport($pfm = true)
    {
        if (! $pfm) {
            return;
        }

        echo '
        <!DOCTYPE html><html lang="en">
        <head>
          <meta charset="UTF-8" />
          <meta name="viewport" content="width=device-width, initial-scale=1.0" />
          <title>Serveronet</title>
        </head>
        <body>
        ';
        H::forceFlush();
    }

    /* Shorthand */
    public static function pfm(mixed $message, $pfm = true, $log = false)
    {
        if (! is_string($message)) {
            $message = json_encode($message);
        }

        if ($log) {
            Log::debug('PFM: '.$message);
        }
        if ($pfm) {
            self::printFlushMessage($message);
        }
    }

    private static function printFlushMessage(mixed $message)
    {
        if (! is_string($message)) {
            $message = json_encode($message);
        }

        $id = Str::random(5);

        $style = 'background-color: #f1f9ff; border-left: 1px dotted #b0b1b3; border-right: 1px dotted #b0b1b3; 
        border-top: 1px dotted #b0b1b3; border-bottom: 1px solid gray; padding: 3px; margin: 6px; overflow: auto;';

        echo '<div id="'.$id.'" style="'.$style.'">'.e($message).'</div>';
        echo '<script>
            const div_'.$id.' = document.getElementById("'.$id.'");
            document.body.prepend(div_'.$id.');
            </script>';

        H::forceFlush();
    }

    public static function getMimeTypeByExtension($ext = 'txt')
    {
        $mapping = MimeType::MIME_TYPES;

        $ext = Str::lower($ext);

        return $mapping[$ext] ?? 'text/plain';
    }

    public static function stringToHash($str)
    {
        $hash = 0;
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            $chr = ord($str[$i]);
            $hash = ($chr + (($hash << 5) - $hash)) & 0xFFFFFFFF;
        }

        /* Handle signed 32-bit overflow like in JS */
        if ($hash > 0x7FFFFFFF) {
            $hash -= 0x100000000;
        }

        return $hash;
    }

    public static function hashToColor($hash)
    {
        $r = ($hash >> 16) & 0xFF;
        $g = ($hash >> 8) & 0xFF;
        $b = $hash & 0xFF;

        return "rgb($r, $g, $b)";
    }

    public static function checkIpIsPublic($client_address)
    {
        if (Str::contains($client_address, '.onion')) {
            return true;
        }

        $ip = H::obtainIpForUrl($client_address);
        if (filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        )) {
            return true;
        }

        return false;
    }

    public static function updateSitesHostedState($site_id = null, $pfm = false)
    {
        info('updateSitesHostedState');
        $sitesBuilder = Site::where('is_published', true);

        if ($site_id) {
            $sitesBuilder->where('site_id', $site_id);
        }

        $sites = $sitesBuilder->get();
        foreach ($sites as $key => $site) {

            $siteHostingTargetState = H::getSiteHostingTargetState($site->site_id);

            $site->is_hosted = $siteHostingTargetState['is_hosted'];
            $site->hosting_state_reason = $siteHostingTargetState['hosting_state_reason'];
            $site->save();

            H::publishSelfAsSitePeer(site_id: $site_id, pfm: $pfm);
        }
    }

    public static function prepareIp($ip): string
    {
        $ip = Str::contains($ip, ':') ? '['.$ip.']' : $ip;

        return $ip;
    }

    public static function getSiteHostingTargetState($site_id)
    {
        info('getSiteHostingState: '.$site_id);
        $site = Site::whereSiteId($site_id)->where('is_to_be_hosted', true)
            ->with('most_recent_site_definition')->first();

        if (! $site || ! $site?->most_recent_site_definition) {
            $hostingState['is_hosted'] = false;
            $hostingState['hosting_state_reason'] = 'No Site Definitions or not to be hosted';

            return $hostingState;
        }

        $siteConfig = json_decode($site->most_recent_site_definition->site_config_json);
        $site_Has_Database = $siteConfig->site_Has_Database;
        if ($site_Has_Database) {
            $visitor_records_replication_end_ts = Carbon::parse(
                $site->visitor_records_replication_end_ts ?? Consts::startOfServeronet);

            if ($visitor_records_replication_end_ts->diffInMinutes(now()) > 60) {
                $hostingState['is_hosted'] = false;
                $hostingState['hosting_state_reason'] = 'Visitors Records replication not up to date';

                return $hostingState;
            }
        }

        $allow_Visitor_Files = $siteConfig->allow_Visitor_Files ?? false;
        if ($allow_Visitor_Files) {
            $visitor_resources_replication_end_ts = Carbon::parse(
                $site->visitor_resources_replication_end_ts ?? Consts::startOfServeronet);

            if ($visitor_resources_replication_end_ts->diffInMinutes(now()) > 60) {
                $hostingState['is_hosted'] = false;
                $hostingState['hosting_state_reason'] = 'Visitors Resources replication not up to date';

                return $hostingState;
            }
        }

        $hostingState['is_hosted'] = true;
        $hostingState['hosting_state_reason'] = '';

        return $hostingState;
    }

    public static function getSelfAddresses(): array
    {
        $ui_addresses = H::getUiAddresses();
        $httpSwitcherUrls = H::httpSwitcherUrls();

        $selfAddresses = [
            H::a(config('app.url')),
            $httpSwitcherUrls['http'],
            $httpSwitcherUrls['https'],
            H::a(H::getSettVal(SettingIds::recent_external_ip).':'.H::getSettVal(SettingIds::client_external_port_http), https: false),
            H::a(H::getSettVal(SettingIds::recent_external_ip).':'.H::getSettVal(SettingIds::client_external_port_http), https: true),
            H::a(H::getSettVal(SettingIds::recent_external_ip).':'.H::getSettVal(SettingIds::client_external_port_https), https: true),
            H::a(H::getSettVal(SettingIds::recent_external_ip).':'.H::getSettVal(SettingIds::client_external_port_https), https: false),

            H::a(H::getSettVal(SettingIds::recent_external_ip).':'.H::getSettVal(SettingIds::client_internal_port_http), https: false),
            H::a(H::getSettVal(SettingIds::recent_external_ip).':'.H::getSettVal(SettingIds::client_internal_port_http), https: true),
            H::a(H::getSettVal(SettingIds::recent_external_ip).':'.H::getSettVal(SettingIds::client_internal_port_https), https: true),
            H::a(H::getSettVal(SettingIds::recent_external_ip).':'.H::getSettVal(SettingIds::client_internal_port_https), https: false),
        ];
        foreach ($ui_addresses as $key => $value) {
            $value = H::a($value);

        }
        $selfAddresses = array_merge($selfAddresses, $ui_addresses);
        $selfAddresses = array_unique($selfAddresses);

        return $selfAddresses;
    }

    public static function getPublicSelfAddress(): ?string
    {
        $selfAddressesWithIp = H::getAppUrl();

        /* Static */
        if (H::getSettVal(SettingIds::is_public_self_address_overridden)) {
            $selfAddressesWithIp = H::getSettVal(SettingIds::static_public_self_address);

            return $selfAddressesWithIp;
        }

        /* No external IP - empty string */
        if (! H::getSettVal(SettingIds::recent_external_ip) || H::getSettVal(SettingIds::recent_external_ip) == '') {
            return $selfAddressesWithIp;
        }

        /* External IP but all forwarding failed -> internal https */
        if (! H::getSettVal(SettingIds::http_port_forwarded) && ! H::getSettVal(SettingIds::https_port_forwarded)) {
            $selfAddressesWithIp =
            H::getSettVal(SettingIds::recent_external_ip).':'.H::getSettVal(SettingIds::client_internal_port_https);
            $selfAddressesWithIp = H::a($selfAddressesWithIp, https: true);

            return $selfAddressesWithIp;
        }

        /* Https forwarded -> external https */
        if (! H::getSettVal(SettingIds::http_port_forwarded) && H::getSettVal(SettingIds::https_port_forwarded)) {
            $selfAddressesWithIp =
            H::getSettVal(SettingIds::recent_external_ip).':'.H::getSettVal(SettingIds::client_external_port_https);
            $selfAddressesWithIp = H::a($selfAddressesWithIp, https: true);

            return $selfAddressesWithIp;
        }
        /* Https and http forward  -> external https as https prefered */
        if (H::getSettVal(SettingIds::http_port_forwarded) && H::getSettVal(SettingIds::https_port_forwarded)) {
            $selfAddressesWithIp =
            H::getSettVal(SettingIds::recent_external_ip).':'.H::getSettVal(SettingIds::client_external_port_https);
            $selfAddressesWithIp = H::a($selfAddressesWithIp, https: true);

            return $selfAddressesWithIp;
        }

        /* Http only forwarded -> external http */
        if (H::getSettVal(SettingIds::http_port_forwarded) && ! H::getSettVal(SettingIds::https_port_forwarded)) {
            $selfAddressesWithIp =
            H::getSettVal(SettingIds::recent_external_ip).':'.H::getSettVal(SettingIds::client_external_port_http);
            $selfAddressesWithIp = H::a($selfAddressesWithIp);

            return $selfAddressesWithIp;
        }

        return $selfAddressesWithIp;
    }

    public static function getAppUrl(): string
    {
        return H::a(config('app.url'));
    }

    public static function publishSelfAsSitePeer($limit = 20, $site_id = null, $pfm = false)
    {
        if (! H::getSettVal(SettingIds::publish_self_as_site_peer)) {
            H::pfm('Publish self as peer not enabled', pfm: $pfm);

            return;
        }

        $hostedSitePendingSelfPublishBuilder = Site::where([
            ['is_hosted', 1],
        ])
            ->where(function ($query) {
                $query->where('last_self_hosting_published_at', '<', now()->subHours(1))
                    ->orWhereNull('last_self_hosting_published_at');
            });

        if ($site_id) {
            $hostedSitePendingSelfPublishBuilder = $hostedSitePendingSelfPublishBuilder->whereSiteId($site_id);
        }

        $hostedSitePendingSelfPublish = $hostedSitePendingSelfPublishBuilder->first();

        if (! $hostedSitePendingSelfPublish || $limit < 1) {
            H::pfm('No more pending limit hit: '.$limit, pfm: $pfm);

            return;
        }

        $hostedSitePendingSelfPublish->last_self_hosting_published_at = now();
        $hostedSitePendingSelfPublish->save();

        $clientNetworkProfile = H::getClientNetworkProfile();
        $publishAsSitePeerAddresses = $clientNetworkProfile->publishAsSitePeerAddresses;

        foreach ($publishAsSitePeerAddresses as $key => $self_address) {

            /* Transient */
            $sitePeer = new SitePeer;
            $sitePeer->site_id = $hostedSitePendingSelfPublish->site_id;
            $sitePeer->client_address = $self_address;

            H::enqueueP2pMessage(
                MessageTypes::site_peer,
                payload: $sitePeer->only(SitePeer::$publicProperties),
                site_id: $hostedSitePendingSelfPublish->site_id,
                priority: 240,
            );
        }

        $limit--;
        self::publishSelfAsSitePeer(limit: $limit);
    }

    public static function isNetworkAvailable()
    {
        /* Privacy concern - */
        $dnsProviders = [
            // Cloudflare
            '1.1.1.1',
            '1.0.0.1',

            // Google Public DNS
            '8.8.8.8',
            '8.8.4.4',

            // Quad9
            '9.9.9.9',
            '149.112.112.112',

            // OpenDNS (Cisco)
            '208.67.222.222',
            '208.67.220.220',

            // AdGuard DNS
            '94.140.14.14',
            '94.140.15.15',

            // CleanBrowsing
            '185.228.168.9',
            '185.228.169.9',

            // Verisign Public DNS
            '64.6.64.6',
            '64.6.65.6',

            // DNS.WATCH
            '84.200.69.80',
            '84.200.70.40',
        ];

        $host = $dnsProviders[array_rand($dnsProviders)];

        $port = 53;
        $timeout = 3;
        $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if ($connection) {
            fclose($connection);

            return true;
        }

        return false;
    }

    public static function getMaxExecutionTime(): int
    {
        $max_execution_time = ini_get('max_execution_time');
        if ($max_execution_time <= 0) {
            $max_execution_time = 30;
        }

        return $max_execution_time;
    }

    public static function decreasePeerReputation(?Peer $peer): void
    {
        if (! $peer) {
            return;
        }

        try {
            if ($peer->reputation < 0) {
                $peer->reputation = $peer->reputation - 1;
            } else {
                $peer->reputation = -1;
            }
            $peer->save();
        } catch (Throwable $th) {
            info($th->getMessage().' '.$th->getFile().' '.$th->getLine());
        }
    }

    public static function increasePeerReputation(string $client_address): void
    {
        Log::debug('increasePeerReputation');
        $peer = Peer::where('client_address', $client_address)->first();
        if (! $peer) {
            return;
        }

        try {
            if ($peer->reputation >= 0) {
                $peer->reputation = $peer->reputation + 1;
            } else {
                $peer->reputation = 1;
            }
            $peer->save();
        } catch (Throwable $th) {
            info($th->getMessage().' '.$th->getFile().' '.$th->getLine());
        }
    }

    public static function bytesCountToReadable($bytesCount)
    {
        if ($bytesCount == 0) {
            return '0 B';
        }
        if (! $bytesCount) {
            return '...';
        }

        $thresholds = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];
        for ($i = 0; $bytesCount > 1024; $i++) {
            $bytesCount = $bytesCount / 1024;
        }

        return round($bytesCount, 2).' '.$thresholds[$i];
    }

    public static function getBytesFromHumanSizeString($sizeString): int
    {
        $sizeString = trim($sizeString);

        if (is_numeric($sizeString)) {
            return (int) $sizeString;
        }

        $lastChar = strtolower($sizeString[strlen($sizeString) - 1]);
        $sizeString = substr($sizeString, 0, -1);

        switch ($lastChar) {
            case 'g':
                $sizeString *= 1024;
            case 'm':
                $sizeString *= 1024;
            case 'k':
                $sizeString *= 1024;
        }

        return (int) $sizeString;
    }

    public static function forceFlush()
    {
        echo str_pad(' ', 4096, ' ');
        echo ' ';
        try {
            ob_flush();
        } catch (Throwable $e) {
        }
        try {
            flush();
        } catch (Throwable $e) {
        }
    }

    public static function getCached($key, $builder, $isCollection = false): mixed
    {
        $ttl = App::isProduction() ? 60 : 5;

        return Cache::remember($key, $ttl, function () use ($builder, $isCollection) {
            return $isCollection ? $builder->get() : $builder->first();
        });
    }

    public static function getUiAddresses(): array
    {
        $ttl = App::isProduction() ? 60 : 1;
        $ui_addresses = Cache::remember(CachePrefixes::ui_addresses, $ttl, function () {

            $ui_address_file_path = storage_path('app/ui_addresses.txt');
            $ui_addresses = [];

            if (File::exists($ui_address_file_path)) {
                $ui_addresses = preg_split('/\r\n|\r|\n/', File::get($ui_address_file_path));
            } else {
                array_push($ui_addresses, H::a(config('app.url')));
                array_push($ui_addresses, H::a(request()->schemeAndHttpHost()));
                $ui_addresses = array_unique(array_values($ui_addresses));
                Storage::put('ui_addresses.txt', implode("\n", $ui_addresses));
            }

            array_push($ui_addresses, H::a(config('app.url')));

            $ui_addresses = array_filter($ui_addresses, function ($ui_address) {
                return trim($ui_address) != '';
            });

            return array_unique($ui_addresses);
        });

        return $ui_addresses;
    }

    public static function getSingleSiteDomains(): array
    {
        return array_keys(config('single_site_mode.sites'));
    }

    public static function getUiDomains(): array
    {
        $ttl = App::isProduction() ? 60 : 1;
        $ui_domains = Cache::remember(CachePrefixes::ui_domains, $ttl, function () {
            $domains = [];

            if (H::isFirstRunCompleted()) {
                $ui_addresses = H::getUiAddresses();

                foreach ($ui_addresses as $key => $address) {
                    try {
                        $url = Url::fromString($address);
                        array_push($domains, $url->getHost());
                    } catch (Throwable $th) {
                    }
                }

                $domains = array_unique(array_values($domains));
                $domains = array_diff($domains, ['']);

            } else {
                $uiDomains = [];

                array_push($uiDomains, H::a(request()->getHost()));
                array_push($uiDomains, env('APP_URL'));
                array_push($uiDomains, config('app.url'));

                foreach ($uiDomains as $key => $address) {
                    if ($address) {
                        $url = Url::fromString($address);
                        array_push($domains, $url->getHost());
                    }
                }

                $domains = array_unique(array_values($domains));
                $domains = array_diff($domains, ['']);
            }

            $localhostDomainPresent = false;

            foreach ($domains as $key => $domain) {
                if (Str::contains($domain, 'localhost')) {
                    $localhostDomainPresent = true;
                }
            }

            if (! $localhostDomainPresent) {
                array_push($domains, 'localhost');
            }

            return $domains;
        });

        return $ui_domains;
    }

    /** Generates Site Url for the current client */
    public static function siteUrl(?string $site_id): string
    {
        if (! $site_id) {
            return 'http://example.com/';
        }

        if (H::inSingleSiteMode(request())) {
            $siteUrl = request()->getSchemeAndHttpHost();
        } else {
            $domain = getDomain();
            $siteUrl = $site_id.'.'.$domain;
            $siteUrl = Url::fromString(H::a($siteUrl));
            $host = Url::fromString(request()->fullUrl());
            $siteUrl = $siteUrl->withScheme($host->getScheme());
            $siteUrl = $siteUrl->withPort($host->getPort());
        }

        return H::a($siteUrl);
    }

    public static function httpSwitcherUrls(): array
    {
        $currentUrl = Url::fromString(request()->getSchemeAndHttpHost());
        $httpUrl = $currentUrl;
        $httpsUrl = $currentUrl;
        $currentScheme = $currentUrl->getScheme();

        if ($currentScheme == 'http') {
            $httpsUrl = $currentUrl->withScheme('https');
            $httpsUrl = $httpsUrl->withPort(H::getSettVal(SettingIds::client_internal_port_https));
        } else {
            $httpUrl = $currentUrl->withScheme('http');
            $httpUrl = $httpUrl->withPort(H::getSettVal(SettingIds::client_internal_port_http));
        }

        return ['https' => H::a($httpsUrl->__toString()), 'http' => H::a($httpUrl->__toString())];
    }

    public static function subdomainLevel($subdomainLevelOverride): string
    {
        if ($subdomainLevelOverride !== null) {
            return $subdomainLevelOverride;
        }

        $domain = getDomain();
        $host = request()->getHost();
        $subdomainPart = Str::replace($domain, '', $host);
        $dotOccurancesCount = Str::substrCount($subdomainPart, '.');

        return $dotOccurancesCount === 0 ? '' : $dotOccurancesCount;
    }

    public static function inSingleSiteMode(mixed $request): bool
    {
        return in_array($request->getHost(), array_keys(config('single_site_mode.sites')));
    }

    public static function getIsTorAvailable()
    {
        $isTorAvailable = false;

        $was_tor_connectable = H::getSettVal(SettingIds::was_tor_connectable);
        $last_tor_connectivity_check_ts = H::getSettVal(SettingIds::last_tor_connectivity_check_ts);
        $diffLastCheck = now()->diffInMinutes($last_tor_connectivity_check_ts);

        if ($was_tor_connectable && $diffLastCheck > -15) {
            $isTorAvailable = true;
        }

        return $isTorAvailable;
    }

    public static function siteEndpointsCommonResolve(?Request $request, &$site_id, &$domain, &$is_domain = false, &$is_resolved = false)
    {
        $site_id = $request->site_id;
        $subdomain1 = $request->subdomain1;
        $subdomain2 = $request->subdomain2;
        $subdomain3 = $request->subdomain3;
        $subdomain4 = $request->subdomain4;

        if ($subdomain1 && $subdomain2 && $subdomain3 && $subdomain4) {
            $site_id = $subdomain4.'.'.$subdomain3.'.'.$subdomain2.'.'.$subdomain1.'.'.$site_id;
        } elseif ($subdomain1 && $subdomain2 && $subdomain3) {
            $site_id = $subdomain3.'.'.$subdomain2.'.'.$subdomain1.'.'.$site_id;
        } elseif ($subdomain1 && $subdomain2) {
            $site_id = $subdomain2.'.'.$subdomain1.'.'.$site_id;
        } elseif ($subdomain1) {
            $site_id = $subdomain1.'.'.$site_id;
        }

        $resolvedDomainState = UtilsController::resolveIfIsDomain($site_id);

        

        if ($resolvedDomainState['is_domain'] && ! $resolvedDomainState['is_resolved']) {
            $is_domain = $resolvedDomainState['is_domain'];
            $is_resolved = $resolvedDomainState['is_resolved'];
        } elseif ($resolvedDomainState['is_domain']) {
            $domain = $resolvedDomainState['domain'];
            $site_id = $resolvedDomainState['site_id'];
            $is_domain = $resolvedDomainState['is_domain'];
            $is_resolved = $resolvedDomainState['is_resolved'];
        }
    }

    public static function updateExternalIp($pfm = false)
    {
        (new UtilsController)->handleExternalIpDetection($pfm);
    }

    public static function getClientNetworkProfile(): object
    {
        $topLevelDomains = TopLevelDomains::fromPath(resource_path('tlds-alpha-by-domain.txt'));

        $recent_external_ip = H::getSettVal(SettingIds::recent_external_ip);

        $is_recent_external_ip_a_public_ip =
        filter_var(
            $recent_external_ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        $public_self_address = null;
        $publishToTrackers = (bool) $is_recent_external_ip_a_public_ip;

        $addressesPublishableAsSitePeer = [];

        if ($is_recent_external_ip_a_public_ip) {
            $public_self_address = H::getPublicSelfAddress();
            array_push($addressesPublishableAsSitePeer, H::a($public_self_address));
        }

        foreach (H::getUiAddresses() as $key => $uiAddress) {
            if (H::isTorAddress($uiAddress)) {
                array_push($addressesPublishableAsSitePeer, $uiAddress);
            }

            if (H::getSettVal(SettingIds::is_public_self_address_overridden)) {
                $selfAddressesWithIp = H::getSettVal(SettingIds::static_public_self_address);
                array_push($addressesPublishableAsSitePeer, $selfAddressesWithIp);

                continue;
            }

            $url = Url::fromString($uiAddress);
            $domain = Domain::fromIDNA2008($url->getHost());
            $result = $topLevelDomains->resolve($domain);

            $isPublicHost = $result->suffix()->isIANA();
            if ($isPublicHost && ! Str::endsWith($url->getHost(), 'dns2local.ovh')) {
                array_push($addressesPublishableAsSitePeer, $uiAddress);
            }
        }

        $addressesPublishableAsSitePeer = array_unique($addressesPublishableAsSitePeer);

        $profile = new stdClass;
        $profile->publishToTrackers = $publishToTrackers;
        $profile->publishAsSitePeerAddresses = $addressesPublishableAsSitePeer;

        return $profile;
    }

    public static function isTorAddress($address): bool
    {
        $urlComponents = parse_url($address);
        $host = $urlComponents['host'] ?? '';
        $is_tor_address = Str::endsWith($host, '.onion');

        return $is_tor_address;
    }

    public static function setupClient($is_tor_address, $forceTorClient = false, $long_connect = false)
    {
        $isTorAvailable = H::getIsTorAvailable();

        $connect_timeout = $long_connect ? 15 : 8;

        $p2p_tor_only_mode = H::getSettVal(SettingIds::p2p_tor_only_mode);

        $client = null;
        if ($is_tor_address || $p2p_tor_only_mode) {

            if (! $isTorAvailable && ! $forceTorClient) {
                return null;
            }
            $stack = new HandlerStack;
            $stack->setHandler(new CurlHandler);
            $tor_port = H::getSettVal(SettingIds::tor_port);
            $tor_address = H::getSettVal(SettingIds::tor_address);
            $stack->push(Middleware::tor($tor_address.':'.$tor_port));

            $client = new Client([
                'handler' => $stack,
                'verify' => false,
                'connect_timeout' => $connect_timeout + 2,
                'allow_redirects' => ['strict' => true],
            ]);
        } else {
            /* GuzzleClient required for stream support */
            $streamHandler = new CurlHandler;
            $stack = HandlerStack::create($streamHandler);
            $client = new Client([
                'verify' => false,
                'connect_timeout' => $connect_timeout,
                'allow_redirects' => ['strict' => true],
            ]);
        }

        return $client;
    }

    public static function timeoutAdjust($isTorAddress, $initialTimeout)
    {
        if ($isTorAddress) {
            return (int) $initialTimeout + ($initialTimeout * 0.5);
        } else {
            return $initialTimeout;
        }
    }

    public static function prepareOptions(&$options, $urls): void
    {
        if ($options['prepare_ip'] ?? false === true) {

            if (! is_array($urls)) {
                $urls = [$urls];
            }

            $CURLOPT_RESOLVE_strings = [];

            foreach ($urls as $key => $url) {
                $host = (Url::fromString(H::a($url)))->getHost();
                $placeholdedPort = (new PortForwardController)->getPort($url);
                $ip = H::obtainIpForUrl($url);
                $CURLOPT_RESOLVE_string = "$host:$placeholdedPort:$ip";
                array_push($CURLOPT_RESOLVE_strings, $CURLOPT_RESOLVE_string);
            }

            $options['curl'][CURLOPT_RESOLVE] = $CURLOPT_RESOLVE_strings;
        }
    }

    public static function obtainIpForUrl(string $url): ?string
    {
        $ip = null;
        $host = (Url::fromString(H::a($url)))->getHost();
        $hostIsIp = filter_var($host, FILTER_VALIDATE_IP);

        if ($hostIsIp) {
            $ip = $host;

            return $ip;
        } else {

            $ip = Cache::remember(CachePrefixes::ip_for_host_.md5($host), 86400, function () use ($host) {
                $resolvedIp = null;
                if (
                    in_array($host, ['localhost', 'ip6-localhost']) ||
                    Str::endsWith($host, ['.localhost', '.ip6-localhost', '.test', '.local'])
                ) {
                    $resolvedIp = '127.0.0.1';
                } else {
                    $opennicFailureCount = H::getSettVal(SettingIds::opennic_failure_count);
                    if ($opennicFailureCount < 10) {
                        $resolvedIp = SitesVisitorController::getDnsAAddressFromOpennic($host);
                        if (! $resolvedIp) {
                            usleep(200_000);
                            $resolvedIp = SitesVisitorController::getDnsAAddressFromOpennic($host);
                        }
                    }
                }

                return $resolvedIp;
            });

        }

        if (! $ip) {
            $ip = gethostbyname($host);
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        return $ip;
    }

    public static function recursiveCopy($source, $destination, $exclusions = [], $additions = [])
    {
        $finder = new Finder;
        $filesystem = new Filesystem;
        $finder->files()->ignoreDotFiles(false)->in($source);

        foreach ($exclusions as $exclusion) {
            $finder->notPath(''.$exclusion);
        }

        foreach ($finder as $file) {
            $targetPath = $destination.DIRECTORY_SEPARATOR.$file->getRelativePathname();
            if (! $filesystem->exists(dirname($targetPath))) {
                $filesystem->mkdir(dirname($targetPath));
            }

            $filesystem->copy(
                $file->getRealPath(),
                $targetPath
            );
        }
    }

    public function zipDirectory($source, $destination)
    {
        if (! extension_loaded('zip') || ! file_exists($source)) {

            return false;
        }

        try {
            $zip = new ZipArchive;
            if (! $zip->open($destination, ZipArchive::CREATE)) {
                return false;
            }

            $source = str_replace('\\', '/', realpath($source));

            if (is_dir($source)) {
                $iterator = new RecursiveDirectoryIterator($source);
                // Skip "." and ".." directories
                $iterator->setFlags(RecursiveDirectoryIterator::SKIP_DOTS);
                $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::SELF_FIRST);

                foreach ($files as $file) {
                    $file = str_replace('\\', '/', realpath($file));

                    if (is_dir($file)) {
                        $zip->addEmptyDir(str_replace($source.'/', '', $file.'/'));
                    } elseif (is_file($file)) {
                        $contents = file_get_contents($file);
                        $zip->addFromString(str_replace($source.'/', '', $file), $contents);
                        $contents = null;
                    }
                }
            } elseif (is_file($source)) {
                $contents = file_get_contents($source);
                $zip->addFromString(basename($source), $contents);
            }

            return $zip->close();
        } catch (Throwable $th) {
            return false;
        }

        return true;
    }
}
