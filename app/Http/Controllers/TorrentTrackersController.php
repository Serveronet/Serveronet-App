<?php

namespace App\Http\Controllers;

use App\Dicts\CachePrefixes;
use App\Dicts\SettingIds;
use App\Http\H;
use App\Models\Site;
use App\Models\Tracker;
use App\Services\TorrentService;
use Arokettu\Bencode\Bencode;
use Exception;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use stdClass;

class TorrentTrackersController extends Controller
{
    public function index(Request $request)
    {
        $trackersList = Tracker::get();
        $trackersUrls = '';
        foreach ($trackersList as $key => $tracker) {
            $trackersUrls .= $tracker->url."\n";
        }
        $trackersList = $trackersList->sortByDesc('last_connected_at');

        return view('edit_trackers', compact('trackersUrls', 'trackersList'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'trackers_urls' => ['required', 'string'],
        ]);

        $trackersUrls = $request->trackers_urls;
        (new TorrentService)->saveTrackers($trackersUrls);

        return redirect(domainRoute('trackers'))->with('status', 'Trackers saved!');
    }

    public function getNewTrackersFromNewTrackon()
    {
        if (H::isDevNode()) {
            return redirect(domainRoute('trackers'))->with('status', 'Disabled - Dev environment');
        }

        $in_ui = request()->in_ui ?? false;

        $url = 'https://newtrackon.com/api/http';

        $newTrackersSaved = 0;

        $client = H::setupClient(false);
        $options = [
            'timeout' => H::timeoutAdjust(false, 60),
            'prepare_ip' => true,
        ];
        H::prepareOptions($options, $url);

        $response = $client->get($url, $options);

        if ($response->getStatusCode() == 200) {
            $trackersUrls = '';
            $trackersUrls .= (string) $response->getBody();
            $trackersList = Tracker::select('url')->get();
            $trackersList->each(function ($tracker) use (&$trackersUrls) {
                $trackersUrls .= $tracker->url."\n";
            });
            $newTrackersSaved = (new TorrentService)->saveTrackers($trackersUrls);
        }

        if ($in_ui) {
            return redirect(domainRoute('trackers'))->with('status', $newTrackersSaved.' new Trackers added');
        } else {
            return '';
        }
    }

    public function announceOrGetPeers(string $site_id, bool $is_hosting = false, bool $returnPeersAsap = false): array|RedirectResponse
    {
        info('announceOrGetPeers');
        $in_ui = request()->in_ui;

        $externalIp = null;

        $externalIp = self::getExternalIp();

        $allPeers = [];

        $trackersUrls = (new TorrentService)->getTrackersUrlsForSite($site_id);
        $successful_trackers_count = 0;
        $successful_trackers = [];
        $explicit_failed_trackers = [];
        $tried_trackers_count = count($trackersUrls);

        $trackersStats = [];

        if (
            (! H::getSettVal(SettingIds::http_port_forwarded) && ! H::getSettVal(SettingIds::https_port_forwarded))
            || (! H::getSettVal(SettingIds::http_port_forwarded) && H::getSettVal(SettingIds::https_port_forwarded))
            || (H::getSettVal(SettingIds::http_port_forwarded) && H::getSettVal(SettingIds::https_port_forwarded))
        ) {
            $externalPort = H::getSettVal(SettingIds::client_external_port_https);
        } else {
            $externalPort = H::getSettVal(SettingIds::client_external_port_http);
        }

        foreach ($trackersUrls as $key => $trackerUrl) {
            $announceToTrackerDefinition = [
                'tracker_url' => $trackerUrl,
                'site_id' => $site_id,
                'is_hosting' => $is_hosting,
                'port' => $externalPort,
                'externalIp' => $externalIp,
            ];

            H::dispatchInternalAsync('announce_to_tracker', $announceToTrackerDefinition);
        }

        $allPeers = [];
        $atLeastOneWithPeersCompleted = false;
        $delays = [
            ['delay' => 0_500_000],
            ['delay' => 1_000_000],
            ['delay' => 1_500_000],
            ['delay' => 2_000_000],
            ['delay' => 2_500_000],
            ['delay' => 3_500_000],
            // Max 10 sec
        ];
        foreach ($delays as $key => $d) {
            foreach ($trackersUrls as $key => $tracker_url) {
                $result = Cache::get(CachePrefixes::tracker_yield_result_.$site_id.$tracker_url);

                $peers = json_decode(Cache::get(CachePrefixes::tracker_yield_peers_.$site_id.$tracker_url), true) ?? [];
                if ($result) {
                    switch ($result) {
                        case 'success':
                            $successful_trackers[$tracker_url] = $tracker_url;
                            if (is_array($peers)) {
                                array_push($allPeers, ...$peers);
                            }
                            if (! empty($peers)) {
                                $atLeastOneWithPeersCompleted = true;
                            }
                            break;

                        case 'failure':
                            $explicit_failed_trackers[$tracker_url] = $tracker_url;
                            break;
                    }
                }
            }

            if (($returnPeersAsap && $atLeastOneWithPeersCompleted) ||
            (count($successful_trackers) + count($explicit_failed_trackers)) == count($trackersUrls)) {
                break;
            }

            usleep($d['delay']);
        }

        $trackersStats['all_trackers'] = $trackersUrls;
        $trackersStats['successfull_trackers'] = $successful_trackers;
        $trackersStats['explicit_failed_trackers'] = $explicit_failed_trackers;

        foreach ($trackersUrls as $key => $tracker_url) {
            Cache::forget(CachePrefixes::tracker_yield_result_.$site_id.$tracker_url);
            Cache::forget(CachePrefixes::tracker_yield_peers_.$site_id.$tracker_url);
        }

        $successful_trackers_count = count($successful_trackers);
        $explicit_failed_trackers_count = count($explicit_failed_trackers);

        $allPeers = collect($allPeers)->toArray();

        $peerIdentifiersUnsetting = H::isDevNode() ? false : true;

        if ($peerIdentifiersUnsetting) {
            foreach ($allPeers as $key => &$peer) {
                try {
                    unset($peer['peer id']);
                    unset($peer['isDct']);
                } catch (\Throwable $th) {
                }
            }
        }

        $exludedPeers = [];
        $uniqeHostingPeers = array_unique($allPeers, SORT_REGULAR);

        foreach ($uniqeHostingPeers as $key => $uniqeHostingPeer) {
            if ($uniqeHostingPeer['ip'] == $externalIp && $uniqeHostingPeer['port'] == $externalPort) {
                array_push($exludedPeers, $uniqeHostingPeers[$key]);
                unset($uniqeHostingPeers[$key]);
            }
        }

        $uiquePeers = [
            'hosting' => $uniqeHostingPeers,
            'exluded' => $exludedPeers,
            'successful_trackers_count' => $successful_trackers_count ?? 0,
            'explicit_failed_trackers_count' => $explicit_failed_trackers_count ?? 0,
            'tried_trackers_count' => $tried_trackers_count ?? 0,
            'trackers_stats' => $trackersStats ?? 0,
            'scenario' => '$returnPeersAsap: '.tfyn($returnPeersAsap).' | atLeastOneWithPeersCompleted: '
                .tfyn($atLeastOneWithPeersCompleted),
        ];

        $site = Site::whereSiteId($site_id)->first();
        $site->trackers_site_peers_count = empty($uniqeHostingPeers) ? 0 : count($uniqeHostingPeers);
        $site->trackers_site_peers_cached_at = now();
        $site->save();
        Cache::forget(CachePrefixes::site_.$site_id);

        if ($in_ui) {
            $message = 'Successful announce to: '.$uiquePeers['successful_trackers_count'].'/'.$uiquePeers['tried_trackers_count'];

            return back()->with('success', $message);
        } else {
            return $uiquePeers;
        }
    }

    public function announceToTracker(Request $request)
    {
        $requestConfigSet = H::unwrapRequestConfigSet();

        if (! H::isInternalCallCurrent($requestConfigSet)) {
            info('Outdated or spoofed internal request');

            return;
        }
        $internalRequestId = H::startInteralRequestReporting(__FUNCTION__);

        $announceToTrackerDefinition = $requestConfigSet;

        $tracker_url = $announceToTrackerDefinition['tracker_url'];
        $site_id = $announceToTrackerDefinition['site_id'];
        $is_hosting = $announceToTrackerDefinition['is_hosting'];
        $port = $announceToTrackerDefinition['port'];

        info('announceToTracker is_hosting '.tfyn($is_hosting).' '.$site_id);

        $firstRunSuccessful = false;
        $statusCode = 'tbd';

        /* Common section */
        $info_hash = sha1($site_id);
        $peerIdPrefix = 'SN_';

        if (H::isDevNode()) {
            $peerIdPrefix = Str::replace(' ', '_', Str::limit(str_pad(env('APP_NAME'), 7) ?? 'APP_NAME_', 7, '')).'_';
        }
        $trackerPeerId = $peerIdPrefix.H::getSettVal(SettingIds::peer_random_id);

        $urlCommon = $tracker_url;
        $urlCommon .= '?info_hash='.urlencode(pack('H*', $info_hash));
        $urlCommon .= '&peer_id='.$trackerPeerId;

        $client_address = H::getPublicSelfAddress();
        $urlCommon .= '&peer_url='.urlencode($client_address);

        $urlCommon .= '&port='.$port;
        $urlCommon .= '&downloaded=0';
        $urlCommon .= '&uploaded=0';
        $urlCommon .= '&left=0';

        $event = $is_hosting ? 'completed' : 'stopped';
        $urlCommon .= '&event='.$event;
        $urlCommon .= '&numwant=20'; // Number of peers requested

        $compact = 0;

        $url = $urlCommon.'&compact=0';

        $r = null;

        $trackerYield = new stdClass;
        $trackerYield->url = $tracker_url;

        try {
            $client = H::setupClient(H::isTorAddress($tracker_url), long_connect: true);
            $options = [
                'timeout' => H::timeoutAdjust(H::isTorAddress($tracker_url), 15),
                'prepare_ip' => true,
            ];
            H::prepareOptions($options, $url);
            $response = $client->get($url, $options);
            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();

            $r = Bencode::decode($body);

            $tracker = Tracker::where('url', $tracker_url)->first();
            $tracker->last_error = null;
            $tracker->last_connected_at = now();
            $tracker->fails_count = 0;
            $tracker->save();
            $trackerYield->result = 'success';
            $firstRunSuccessful = true;

        } catch (\Throwable $th) {
            info('announceToTracker St '.$th->getMessage().' '.$th->getFile().' '.$th->getLine());

            if ($th instanceof ClientException) {
                $statusCode = $th->getResponse()->getStatusCode();
            }

            $tracker = Tracker::where('url', $tracker_url)->first();
            if ($tracker) {
                $tracker->last_error = Str::limit($th->getMessage(), 300);
                $tracker->fails_count++;
                $tracker->save();
            }

            $trackerYield->result = 'failure';
            $trackerYield->result_message = Str::limit($th->getMessage(), 300);
        }

        if ($statusCode === 400 && ! $firstRunSuccessful) {
            /* Second try for compact */

            $url = $urlCommon.'&compact=1';

            $compact = 1;

            $trackerYield = new stdClass;
            $trackerYield->url = $tracker_url;
            try {
                $client = H::setupClient(H::isTorAddress($tracker_url), long_connect: true);
                $options = [
                    'timeout' => H::timeoutAdjust(H::isTorAddress($tracker_url), 15),
                    'prepare_ip' => true,
                ];
                H::prepareOptions($options, $url);
                $response = $client->get($url, $options);
                $statusCode = $response->getStatusCode();
                $body = (string) $response->getBody();

                $r = Bencode::decode($body);

                $tracker = Tracker::where('url', $tracker_url)->first();
                $tracker->last_error = null;
                $tracker->last_connected_at = now();
                $tracker->fails_count = 0;
                $tracker->save();
                $trackerYield->result = 'success';
                $firstRunSuccessful = true;
            } catch (\Throwable $th) {
                info('announceToTracker Nd '.$th->getMessage().' '.$th->getFile().' '.$th->getLine());

                if ($th instanceof ClientException) {
                    $statusCode = $th->getResponse()->getStatusCode();
                    $tracker = Tracker::where('url', $tracker_url)->first();
                    $tracker->last_error = $th->getMessage();
                    $tracker->fails_count++;
                    $tracker->save();

                    $trackerYield->result = 'failure';
                    $trackerYield->result_message = $th->getMessage();
                }
            }
        }

        $result = 'success';
        if (! $r) {
            $result = 'failure';
        }

        if ($compact) {
            $peers = isset($r['peers']) ? $r['peers'] : null;
            $peers = self::parseCompactPeers($peers);
        } else {
            $peers = $r['peers'] ?? null;
        }

        Cache::put(CachePrefixes::tracker_yield_peers_.$site_id.$tracker_url, json_encode($peers));
        Cache::put(CachePrefixes::tracker_yield_result_.$site_id.$tracker_url, $result);

        H::endInteralRequestReporting($internalRequestId);

        return $trackerYield;
    }

    public static function parseCompactPeers(string|null $peerBinary): array
    {
        $peers = [];
        if (! $peerBinary)
        return $peers;

        $length = strlen($peerBinary);

        if ($length % 6 !== 0) {
            throw new Exception('Invalid peer list length: not a multiple of 6');
        }

        for ($i = 0; $i < $length; $i += 6) {
            // Extract 4 bytes for IP
            $ip = ord($peerBinary[$i]).'.'.
                ord($peerBinary[$i + 1]).'.'.
                ord($peerBinary[$i + 2]).'.'.
                ord($peerBinary[$i + 3]);

            // Extract 2 bytes for port (big-endian)
            $port = (ord($peerBinary[$i + 4]) << 8) + ord($peerBinary[$i + 5]);

            $peers[] = ['ip' => $ip, 'port' => $port];
        }

        return $peers;
    }

    public static function getExternalIp()
    {
        $last_external_ip_detected_ts = H::getSettVal(SettingIds::last_external_ip_detected_ts);

        $diffLastDetect = now()->diffInMinutes($last_external_ip_detected_ts);

        if ($diffLastDetect < 10 && $diffLastDetect > -1) {
            H::updateExternalIp();
        }

        $recent_external_ip = H::getSettVal(SettingIds::recent_external_ip);

        return $recent_external_ip;
    }

    public static function trackerAnnouncingHosting($limit = 5, $site_id = null, $pfm = false)
    {
        info('trackerAnnouncingHosting');
        $clientNetworkProfile = H::getClientNetworkProfile();
        if (! $clientNetworkProfile->publishToTrackers) {
            info('trackerAnnouncingHosting disabled by profile');

            return;
        }

        $announceFrequencyMinutes = ( ! H::isDevNode()) ? 10 : 1;

        $hostedSitePendingAnnounceBuilder = Site::where([
            ['is_hosted', 1],
        ])
            ->where(function ($query) use ($announceFrequencyMinutes) {
                $query->where('last_self_hosting_trackers_announced_at', '<', now()->subMinutes($announceFrequencyMinutes))
                    ->orWhereNull('last_self_hosting_trackers_announced_at');
            });

        if ($site_id) {
            $hostedSitePendingAnnounceBuilder->whereSiteId($site_id);
        }

        $hostedSitePendingAnnounce = $hostedSitePendingAnnounceBuilder->first();

        if (! $hostedSitePendingAnnounce || $limit < 1) {
            H::pfm('No more pending or limit hit: '.$limit.' '.__FUNCTION__, pfm: $pfm);

            return;
        }
        $site = $hostedSitePendingAnnounce;
        $t = new TorrentTrackersController;

        H::pfm($site->site_id, pfm: $pfm);
        $site->last_self_hosting_trackers_announced_at = now();
        $site->save();
        $t->announceOrGetPeers(site_id: $site->site_id, is_hosting: true);

        $limit--;

        if (! $site_id) {
            self::trackerAnnouncingHosting($limit, $site_id, pfm: $pfm);
        }
    }
}
