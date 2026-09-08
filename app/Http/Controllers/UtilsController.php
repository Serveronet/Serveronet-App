<?php

namespace App\Http\Controllers;

use App\Dicts\CachePrefixes;
use App\Dicts\SettingIds;
use App\Http\Consts;
use App\Http\H;
use App\Models\CachedDomain;
use App\Models\Peer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class UtilsController extends Controller
{
    public function validatedReturnSiteId(?Request $request, ?string &$site_id)
    {
        $target_site_id = $site_id;

        if ($site_id == Consts::visitorControlPanelAddress) {
            return null;
        }

        if (! $request) {
            $request = new Request;
        }

        try {
            $request->merge(['target_site_id' => $target_site_id]);
            $request->validate(['target_site_id' => Consts::siteIdValidationRule]); 
        } catch (Throwable $th) {
            return $this->return_failure($th->getMessage().' '.$target_site_id);
        }

        return null;
    }

    public function validateRawSiteId(Request $request)
    {
        $request->validate(['site_id' => Consts::siteIdValidationRule,
        ]);
    }

    public static function resolveIfIsDomain($site_id)
    {
        $result = ['is_domain' => false, 'is_resolved' => false, 'site_id' => $site_id, 'domain' => null];
        if ($site_id === Consts::visitorControlPanelAddress) {
            $result['is_domain'] = false;
            $result['is_resolved'] = false;
            $result['site_id'] = $site_id;
            $result['domain'] = $site_id;

            return $result;
        }

        $isDomain = Str::contains($site_id, ['-']);
        if ($isDomain) {
            $result['is_domain'] = true;
            $result['domain'] = $site_id;

            $cachedDomain = H::getCached(CachePrefixes::domain_.$site_id,
                CachedDomain::where('domain', $site_id));

            if ($cachedDomain) {
                $resolvedSiteId = $cachedDomain->site_id;
                $result['is_resolved'] = true;
                $result['site_id'] = $resolvedSiteId;

                return $result;

            } else {

                /* snet Domains */
                if (Str::endsWith($site_id, '-snet')) {
                    $resolvedSiteId = (new SitesVisitorController)->getSiteIdForSnetDomains($site_id);

                    /* Classic DNS */
                } else {
                    $resolvedSiteId = SitesVisitorController::getDnsLinkAddress($site_id);
                }

            }

            if ($resolvedSiteId) {
                $result['is_resolved'] = true;
                $result['site_id'] = $resolvedSiteId;

                return $result;
            } else {
                $result['is_resolved'] = false;
                $result['site_id'] = null;
                $result['domain'] = $site_id;

                return $result;
            }

        } else {
            return $result;
        }
    }

    public function getFeelingLib()
    {
        return response(
            File::get(resource_path('js/feeling-lib.js')), 200)
            ->withHeaders(['Content-Type' => 'application/javascript']);
    }

    public function getSnLogo()
    {
        return response(
            File::get(public_path('sn_client_resources/img/logo.png')), 200)
            ->withHeaders(['Content-Type' => 'image/png']);
    }

    public function getFavicon(Request $request)
    {
        if ( ! H::isDevNode()) {
            return response(
                File::get(resource_path('favicon.ico')), 200)
                ->withHeaders(['Content-Type' => 'image/png']);
        }

        $key = $request->getScheme().'://'.$request->getHost().':'.$request->getPort();
        $hash = md5($key);

        // Colors from hash
        $bg = '#'.substr($hash, 0, 6);
        $fg = '#'.substr($hash, 6, 6);

        // Pick shape type (0–2)
        $shapeType = hexdec($hash[12]) % 3;

        $svg = '';

        switch ($shapeType) {
            case 0: // circle
                $svg = "<circle cx='16' cy='16' r='10' fill='{$fg}' />";
                break;

            case 1: // triangle
                $svg = "<polygon points='16,6 26,26 6,26' fill='{$fg}' />";
                break;

            case 2: // squares
                $svg = "
                    <rect x='6' y='6' width='8' height='8' fill='{$fg}' />
                    <rect x='18' y='18' width='8' height='8' fill='{$fg}' />
                ";
                break;
        }

        $content = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32">
            <rect width="32" height="32" fill="{$bg}" />
            {$svg}
        </svg>
        SVG;

        return response($content, 200)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'public, max-age=31536000');
    }

    public function createSymLinks(Request $request)
    {
        (new PublishSitesController)->toggleDevSiteSymlink(
            $request->merge(['create' => false, 'required_phrase' => Consts::symlinkRequiredPhrase]));

        return 'Symlinks created';
    }

    public function handleExternalIpDetection(bool $pfm = false)
    {
        if (! $pfm) {
            $pfm = request()->pfm ?? false;
        }

        H::echoHeaderWithViewport($pfm);

        $redirectBack = request()->redirectBack;

        $detectedExteralIp = (new SSDPController(pfm: $pfm))->getExternalIp();

        H::pfm('By SSDP: ', pfm: $pfm);
        H::pfm($detectedExteralIp, pfm: $pfm);

        if ($detectedExteralIp) {
            $externalIp = trim($detectedExteralIp);
            if (filter_var(
                $externalIp,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            )) {
                H::setSettingValue(SettingIds::recent_external_ip, $externalIp);
                H::setSettingValue(SettingIds::last_external_ip_detected_ts, now());
                info('handleExternalIpDetection by SSDP');
                if ($redirectBack) {
                    return redirect()->back()->withFragment('update_public_address');
                }

                return $this->return_success($externalIp, 'SSDP detection');
            }
        }

        info('handleExternalIpDetection by SSDP FAILED');

        if (! H::isFirstRunCompleted()) {
            $bootstrapPeersUrl = H::a(Consts::centralServerAddress).'bootstrap_peers.json';
            
            $response = Http::timeout(5)->get($bootstrapPeersUrl);
            $peers = collect(json_decode($response->body()))->take(10);
            $peers = $peers->whereNotIn('client_address', H::getSelfAddresses());
        } else {
            $peers = $this->getAllPeers();
        }

        H::pfm('Using peers: '.json_encode($peers), pfm: $pfm);

        foreach ($peers as $peer) {
            try {
                $url = H::a($peer->client_address).'p2p_api/v1/serve_external_peer_details';

                $client = H::setupClient(H::isTorAddress($peer->client_address));
                $options = [
                    'connect_timeout' => 2,
                    'timeout' => H::timeoutAdjust(H::isTorAddress($peer->client_address), 3),
                    'prepare_ip' => true,
                ];
                H::prepareOptions($options, $url);
                $response = $client->request('POST', $url, $options);
                $body = (string) $response->getBody();
                $success = (json_decode($body))->success;

                if ($success == true) {
                    $data = (json_decode($body))->data;
                    if (filter_var(
                        $data,
                        FILTER_VALIDATE_IP,
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                    )) {
                        H::setSettingValue(SettingIds::recent_external_ip, $data);
                        H::setSettingValue(SettingIds::last_external_ip_detected_ts, now());

                        info('handleExternalIpDetection by peer SUCCESS '.$peer->client_address);

                        H::pfm('By peers: ', pfm: $pfm);
                        H::pfm($data, pfm: $pfm);

                        if ($redirectBack) {
                            return redirect()->back()->withFragment('update_public_address');
                        }

                        return $this->return_success($data, 'Using peers detection. By '.$peer->client_address);
                    }
                }
                info('handleExternalIpDetection by peer FAILED '.$peer->client_address);
            } catch (Throwable $th) {
                info($th->getMessage().' '.$th->getFile().' '.$th->getLine());
            }
        }
        H::setSettingValue(SettingIds::recent_external_ip, '');
        H::setSettingValue(SettingIds::last_external_ip_detected_ts, now());

        info('handleExternalIpDetection Failure ');

        if ($redirectBack) {
            return redirect()->back()->withFragment('update_public_address');
        }

        return $this->return_success(null, 'Fallback');
    }

    protected function getAllPeers(): array
    {
        $allPeers = [];

        $peersBuilder = Peer::query();
        $dbPeers = $peersBuilder->where('last_connection_check_at', '>', now()->subHour()->toDateTimeString())
            ->whereNotNull('last_connected_at')->orderByLastConnectedDesc()->orderByReputationDesc()
            ->take(10)->select('client_address')->get();

        foreach ($dbPeers as $key => $dbPeer) {
            array_push($allPeers, $dbPeer);
        }

        return $allPeers;
    }
}
