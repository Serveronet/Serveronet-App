<?php

namespace App\Http\Controllers;

use App\Dicts\ActionTypes;
use App\Dicts\ListProviderTypes;
use App\Dicts\PublishedVersionsSources;
use App\Dicts\SchedulesTag;
use App\Dicts\SettingIds;
use App\Http\Consts;
use App\Http\H;
use App\Models\CachedDomain;
use App\Models\ListProviderEntry;
use App\Models\P2pMessage;
use App\Models\Peer;
use App\Models\PendingAction;
use App\Models\Site;
use App\Models\SitePeer;
use App\Services\IPFSService;
use App\Services\ListProviderService;
use App\Services\PQCryptoService;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;

/* Hadles UI of the Admin section */
class AdminController extends Controller
{
    public function advancedControlPanel()
    {
        $current_source = H::getSettVal(SettingIds::client_automatic_update_source);
        $current_channel = H::getSettVal(SettingIds::client_automatic_update_channel);

        switch ($current_source) {
            case 'remote':
                H::setSettingValue(SettingIds::client_automatic_update_source, PublishedVersionsSources::central_server);
                $current_source = PublishedVersionsSources::central_server;
                break;

            case 'local':
                H::setSettingValue(SettingIds::client_automatic_update_source, PublishedVersionsSources::dev_server);
                $current_source = PublishedVersionsSources::dev_server;
                break;
        }

        $requiredPhrase = Consts::symlinkRequiredPhrase;
        $symlinkExists = (new PublishSitesController)->isSymlinkExists();
        $autoCreateSymlink = H::getSettVal(SettingIds::auto_create_developed_sites_symlink);
        $updateVariants = UpdaterController::$updateVariants;
        $ownerOnly = config('sn.serve_owner_only');
        $tags = SchedulesTag::getConstants();

        return view('advanced_control_panel', compact('requiredPhrase', 'symlinkExists',
            'autoCreateSymlink', 'updateVariants',
            'ownerOnly', 'tags', 'current_source', 'current_channel'
        ));
    }

    public function controlPanel()
    {
        $mailLoopStatus = self::getMainLoopState();

        $blockListSites = (new ListProviderService)->getBlockListSites();

        $enabledLists = json_decode(H::getSettVal(SettingIds::enabled_list_providers)) ?? [];

        $bundle_version = H::getBundleVersion();

        $isPublicSelfAddressOverridden = ((bool) H::getSettVal(SettingIds::is_public_self_address_overridden)) ? 'true' : 'false';
        $publicSelfAddress = H::getPublicSelfAddress();
        $ui_addresses = implode("\n", H::getUiAddresses());

        return view('control_panel', compact('mailLoopStatus',
            'blockListSites', 'enabledLists', 'bundle_version',
            'isPublicSelfAddressOverridden', 'publicSelfAddress', 'ui_addresses'
        ));
    }

    protected static function getMainLoopState()
    {
        $execDiff = now()->diffInMinutes(H::getSettVal(SettingIds::last_heartbeat_ts));
        $mainLoopExecutedInLast2Minutes = $execDiff <= 2 && $execDiff > -1;
        $mainLoopStopSignaled = H::getSettVal(SettingIds::main_loop_stop_signaled);

        $mailLoopStatus = ['label' => 'Not Started ⚠', 'css' => 'light'];
        if ($mainLoopExecutedInLast2Minutes && ! $mainLoopStopSignaled) {
            $mailLoopStatus = ['label' => 'Operational 🆗', 'css' => 'success'];
        } elseif ($mainLoopExecutedInLast2Minutes && $mainLoopStopSignaled) {
            $mailLoopStatus = ['label' => 'Stopping', 'css' => 'warning'];
        } elseif (! $mainLoopExecutedInLast2Minutes && $mainLoopStopSignaled) {
            $mailLoopStatus = ['label' => 'Stopped ⚠', 'css' => 'danger'];
        }
        $mailLoopStatus['mainLoopExecutedInLast2Minutes'] = $mainLoopExecutedInLast2Minutes;
        $mailLoopStatus['mainLoopStopSignaled'] = $mainLoopStopSignaled;

        return $mailLoopStatus;
    }

    public function AddManuallyIndex()
    {
        return view('add_manually');
    }

    public function AddManually(Request $request)
    {
        $dataType = $request->input('data_type');
        $client_address = $request->input('client_address');
        $site_id = $request->input('site_id');
        $site_base64_seed = $request->input('site_base64_seed');
        $reason = $request->input('reason');
        $domain = $request->input('domain');

        $status = '';

        switch ($dataType) {
            case 'site_by_seed':

                $request->validate(['site_base64_seed' => 'required|string']);

                try {
                    $keySet = (new PQCryptoService)->getKeySetFromSeed($site_base64_seed);
                    $verKeyBytes = $keySet->verification_key_bytes;
                } catch (\Throwable $th) {
                    throw ValidationException::withMessages(['message' => $th->getMessage()]);
                }

                $site_id = H::verKeyToHashedId($verKeyBytes);

                $site = Site::where('site_id', $site_id)->first();
                if (! $site) {
                    $site = new Site;
                    $site->site_id = $site_id;
                    $site->is_published = false;
                    $site->is_shell = true;
                    $site->is_to_be_hosted = false;

                    $status = 'Added Site as shell: '.$site_id;
                } else {
                    $status = 'Private Seed set for Site: '.$site_id;
                }
                $site->encrypted_seed = encrypt($keySet->seed);
                $site->save();
                break;

            case 'peer':

                $request->validate(['client_address' => 'required|max:2048|url']);

                if (in_array(H::a($client_address), H::getSelfAddresses())) {
                    $status = 'Not adding own address!';
                    break;
                }

                $peer = Peer::where('client_address', H::a($client_address))->first();
                if (! $peer) {
                    $peer = new Peer;
                    $peer->client_address = H::a($client_address);
                    $peer->save();
                    $status = 'Added';
                } else {
                    $status = 'Already present';
                }

                $closure = function () use ($peer) {
                    (new ClientController())->checkPeerConnectivity(peer: $peer, do_inbound_connectivity_check: true);
                };

                H::dispatchInternalAsyncClosureWrapper($closure);

                break;

            case 'site_peer':

                $request->validate([
                    'client_address' => 'required|max:2048|url',
                ]);
                (new UtilsController)->validateRawSiteId($request);

                $status = self::upsertSitePeerAndPeer($site_id, $client_address);

                $sitePeer = SitePeer::where([
                    ['client_address', $client_address],
                    ['site_id', $site_id],
                ])->first();

                $closure = function () use ($sitePeer) {
                    (new BackgroundProcessingController)->verifySitePeer($sitePeer->id);
                };

                H::dispatchInternalAsyncClosureWrapper($closure);

                break;

            case 'banned_site':

                (new UtilsController)->validateRawSiteId($request);

                $listProviderEntry = ListProviderEntry::where([
                    ['data_type', ListProviderTypes::banned_sites],
                    ['content', $site_id],
                    ['is_manual_entry', true],
                ])->first();
                if (! $listProviderEntry) {
                    $listProviderEntry = new ListProviderEntry;
                    $listProviderEntry->content = $site_id;
                    $listProviderEntry->data_type = ListProviderTypes::banned_sites;
                    $listProviderEntry->is_manual_entry = true;
                    $listProviderEntry->reason = $reason;
                    $listProviderEntry->save();
                    $status = 'Added Banned Site: '.$site_id;
                } else {
                    $status = 'Site already banned: '.$site_id;
                }

                break;

            case 'domain':
                $request->validate(['domain' => 'required|string|max:253']);

                $domainToBeSaved = new CachedDomain;
                $domainToBeSaved->domain = $domain;
                $domainToBeSaved->site_id = $site_id;
                $domainToBeSaved->is_persistent = true;

                $domainToBeSaved->save();

                $status = 'Added';

                break;

            default:

                break;
        }

        return redirect(domainRoute('add_manually'))->with('status', $status);
    }

    public function adminDashboard()
    {
        $ui_addresses = H::getUiAddresses();

        $newer_client_version_detected = H::getSettVal(SettingIds::newer_client_version_detected);
        $newer_bundle_version_detected = H::getSettVal(SettingIds::newer_bundle_version_detected);

        $scheduledP2pMessages = P2pMessage::whereNull('sent_at')->get();

        $pendingUploads =
        PendingAction::whereIn(
            'action_type', [
                ActionTypes::upload_site_resource,
                ActionTypes::upload_visitor_resource,
            ]
        )
            ->whereNull('completed_at')->get();

        $pendingUploadsTotalFileSize = $pendingUploads->sum('file_size');

        $pendingRetrievalActions =
        PendingAction::whereIn(
            'action_type', [
                ActionTypes::retrieve_resource,
                ActionTypes::retrieve_visitor_resource_defintion,
            ]
        )->whereNull('completed_at')->get();

        $pendingRetrievalActionsTotalFileSize = $pendingRetrievalActions->sum('file_size');

        $statisticsOfPending = [
            '📨 Count of Scheduled P2P Messages' => count($scheduledP2pMessages),

            '📤 #️⃣ Count of Pending Uploads' => count($pendingUploads),
            '📤 📦 Total of Pending Uploads' => H::bytesCountToReadable($pendingUploadsTotalFileSize),

            '📥 #️⃣ Count of Pending Retrieval Actions' => count($pendingRetrievalActions),
            '📥 📦 Total of Pending Retrieval Actions' => H::bytesCountToReadable($pendingRetrievalActionsTotalFileSize),
        ];

        $ver = H::ver();

        $indicators = [
            '🔄 Newer version available' => $newer_client_version_detected ? 'Yes ⚠️' : 'No',
            '🔁 Main loop state' => self::getMainLoopState()['label'],
            '🌐 IPFS Operational' => tfyn(IPFSService::getIsIpfsAvailable()),
            '🧅 Tor Operational' => tfyn(H::getIsTorAvailable()),
            '⁣' => '⁣', // Kept as is (likely a separator/invisible char)
            '📦 Serveronet Version' => $ver,
            '🖴 Disk space free' => round(disk_free_space('./') / 1024 / 1024 / 1024, 2).' GB',
            '📍 Public client Address' => H::getPublicSelfAddress(),
            '🔌 Internal/External HTTP' => H::getSettVal(SettingIds::client_internal_port_http).' / '.H::getSettVal(SettingIds::client_external_port_http),
            '🔒 Internal/External HTTPS' => H::getSettVal(SettingIds::client_internal_port_https).' / '.H::getSettVal(SettingIds::client_external_port_https),
            '🌍 External IP' => H::getSettVal(SettingIds::recent_external_ip),
            '🎨 Laravel Version' => Application::VERSION,
            '🐘 PHP Version' => PHP_VERSION,
            '🕒 Client UTC Time' => now()->toDateTimeString(),
            '🗄️ DB Connection' => config('database.default'),
            '🧠 Mem limit' => ini_get('memory_limit'),
            '📢 Automatic Update Channel' => H::getSettVal(SettingIds::client_automatic_update_channel),
            '📥 Automatic Update Source' => H::getSettVal(SettingIds::client_automatic_update_source),
            '🏠 Single Site Domains' => implode(', ', array_keys(config('single_site_mode.sites'))),
        ];

        $bundle_version = H::getBundleVersion();

        if ($bundle_version) {
            $indicators = array_merge(['Newer Bundle version availabale' => $newer_bundle_version_detected ? 'Yes ⚠' : 'No'], $indicators);
        }

        $indicators = array_merge($indicators, $statisticsOfPending);
        $uiAddress = $ui_addresses;
        $i = 0;
        foreach ($uiAddress as $key => $value) {
            $indicators = array_merge($indicators, ['🖥️ UI Address '.$i => H::a($value)]);
            $i++;
        }

        $misconfiguredMssage = null;
        $response = view('admin_dashboard', compact('indicators', 'misconfiguredMssage'));
        if ($newer_client_version_detected) {
            return $response->with('error', 'New Client version is available');
        } elseif ($newer_bundle_version_detected) {
            return $response->with('error', 'New Bundle version is available. Download new Bundle.');
        } else {
            return $response;
        }
    }

    public function testTorAccess($pfm = false)
    {
        if (! $pfm) {
            $pfm = request()->pfm;
        }

        H::echoHeaderWithViewport($pfm);

        H::pfm('Testing Tor access. Please wait...', pfm: $pfm);
        if ($pfm) {
            $tor_address = H::getSettVal(SettingIds::tor_address);
            $tor_port = H::getSettVal(SettingIds::tor_port);
            H::pfm('Hint: Default ports for a local installation - 9050, for Browser - 9150');
            H::pfm('Checking Tor on Address '.$tor_address.' and Port '.$tor_port);
        }

        $url = 'https://check.torproject.org/api/ip';

        try {
            $client = H::setupClient(is_tor_address: true, forceTorClient: true);
            $options = ['timeout' => 20];
            H::prepareOptions($options, $url);
            $response = $client->request('GET', $url, $options);
            $body = (string) $response->getBody();

            $isTorConnectable = json_decode($body)->IsTor;

            H::setSettingValue(SettingIds::was_tor_connectable, $isTorConnectable);
            H::setSettingValue(SettingIds::last_tor_connectivity_check_ts, now());

            H::pfm('Is tor connectable: '.(tfyn($isTorConnectable)), pfm: $pfm);
        } catch (\Throwable $th) {

            H::setSettingValue(SettingIds::was_tor_connectable, false);
            H::setSettingValue(SettingIds::last_tor_connectivity_check_ts, now());

            H::pfm('Error while connecting to tor.', pfm: $pfm);
            H::pfm($th->getMessage(), pfm: $pfm);
        }
    }

    public function testIpfs($pfm = false)
    {
        if (! $pfm) {
            $pfm = request()->pfm;
        }

        H::echoHeaderWithViewport($pfm);

        $ipfs_read_only = H::getSettVal(SettingIds::ipfs_read_only);

        H::pfm('Testing IPFS access. Please wait...', pfm: $pfm);
        H::pfm('IPFS config - Read Only: '.tfyn($ipfs_read_only), pfm: $pfm);

        $isIpfsConnectable = false;

        try {
            $ipfs = IPFSService::getIpfsClient(H::getSettVal(SettingIds::ipfs_address), 5);

            H::pfm('Using: '.$ipfs->client->getConfig('base_uri'), pfm: $pfm);

            $contents = $ipfs->cat(Consts::ipfsTestFileHash);

            $isIpfsConnectable = trim($contents) === 'success';

            H::setSettingValue(SettingIds::was_ipfs_connectable, $isIpfsConnectable);
            H::setSettingValue(SettingIds::last_ipfs_connectivity_check_ts, now());

        } catch (\Throwable $th) {

            H::setSettingValue(SettingIds::was_ipfs_connectable, false);
            H::setSettingValue(SettingIds::last_ipfs_connectivity_check_ts, now());

            H::pfm('Error while working with IPFS.', pfm: $pfm);
            H::pfm($th->getMessage(), pfm: $pfm);
        }

        H::pfm('Is IPFS operational: '.tfyn($isIpfsConnectable), pfm: $pfm);
    }

    public function toggleControlPanelProperty(Request $request)
    {
        $request->validate([
            'property' => ['required', 'string', 'max:1024'],
        ]);
        $property = $request->input('property');

        switch ($property) {
            case 'owner_only':
                Artisan::call('config:clear');
                $SERVE_OWNER_ONLY = config('sn.serve_owner_only');
                $SERVE_OWNER_ONLY = ! $SERVE_OWNER_ONLY;
                Artisan::call('env:set SERVE_OWNER_ONLY '.var_export($SERVE_OWNER_ONLY, true));
                Artisan::call('config:clear');
                Artisan::call('route:clear');

                Artisan::call('optimize');

                break;

            default:

                break;
        }

        return redirect(domainRoute('advanced_control_panel'))->with('success', 'Modified')
            ->withFragment('toggle_control_panel_property');
    }

    protected static function upsertSitePeerAndPeer(string $site_id, string $client_address): string
    {
        if (in_array(H::a($client_address), H::getSelfAddresses()) ||
        in_array(H::a($client_address, https: true), H::getSelfAddresses())) {
            $status = 'Not adding own address!';

            return $status;
        }
        $client_address = H::a($client_address);
        $sitePeer = SitePeer::where([
            ['client_address', $client_address],
            ['site_id', $site_id],
        ])->first();
        if (! $sitePeer) {
            $client_address = $client_address;
            $sitePeer = new SitePeer;
            $sitePeer->client_address = $client_address;
            $sitePeer->site_id = $site_id;
            $sitePeer->source = 'replication';
            $sitePeer->save();
            $status = 'Site Peer Added';
        } else {
            $sitePeer->archived_at = null;
            $sitePeer->save();
            $status = 'Site Peer Already present - Reinstating';
        }

        $peer = Peer::where('client_address', $client_address)->first();
        if (! $peer) {
            $peer = new Peer;
            $peer->client_address = $client_address;
            $peer->save();
            $status .= ' | Peer Added';
        } else {
            $status .= ' | Peer Already present';
        }

        return $status;
    }

    public function manageLog()
    {
        return view('manage_log');
    }

    public function handleInternalCallVerification(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'requestConfigSet' => ['required', 'min:1', 'string', 'max:'.Consts::recordJsonMaxSizeBytes],
            ]);
        } catch (\Throwable $th) {
            return $this->return_failure($th->getMessage());
        }

        try {
            $requestConfigSet = json_decode(decrypt($request->requestConfigSet), true);
        } catch (\Throwable $th) {
            abort(400);
        }

        if (! H::isInternalCallCurrent($requestConfigSet)) {
            info('Outdated or spoofed internal request');

            return $this->return_failure('Outdated or spoofed internal request');
        }

        return $this->return_success(['ts' => now(), 'message' => 'Internal call success. requestConfigSet decrypted.']);
    }
}
