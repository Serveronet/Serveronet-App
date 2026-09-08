<?php

namespace App\Services;

use App\Dicts\CachePrefixes;
use App\Dicts\KnownResponses;
use App\Http\Consts;
use App\Http\Controllers\BackgroundProcessingController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\TorrentTrackersController;
use App\Http\H;
use App\Models\Peer;
use App\Models\ResultContainer;
use App\Models\Site;
use App\Models\SitePeer;
use App\Models\Tracker;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Spatie\Url\Url;

class TorrentService extends Controller
{
    public function saveTrackers($trackersUrls)
    {
        $knownBadTrackers = [];

        $trackers = explode("\n", $trackersUrls);
        $trackersTrimmed = [];
        $newTrackersSaved = 0;

        foreach ($trackers as $key => $trackerUrl) {
            $priority = 2;
            $trackerUrl = trim($trackerUrl);
            array_push($trackersTrimmed, $trackerUrl);
            $url = Url::fromString($trackerUrl);
            $port = $url->getPort();
            $scheme = $url->getScheme();

            if (! $port) {
                if ($scheme == 'http') {
                    $port = '80';
                } elseif ($scheme == 'https') {
                    $port = '443';
                } else {
                    $port = '80';
                }
            }

            if ($trackerUrl != '' && $url->getScheme() != 'udp' && ! in_array($trackerUrl, $knownBadTrackers)) {

                if (in_array($port, Consts::mostConnectablePorts)) {
                    $priority = 1;
                }

                $tracker = Tracker::where('url', $trackerUrl)->first();
                if (! $tracker) {
                    $tracker = new Tracker;
                    $tracker->url = $trackerUrl;
                    $tracker->priority = $priority;
                    $tracker->save();
                    $newTrackersSaved++;
                }
            }
        }

        Tracker::whereNotIn('url', $trackersTrimmed)->delete();

        return $newTrackersSaved;
    }

    public function getTrackersUrlsForSite($site_id): array
    {
        $siteConfig = (new SiteConfigService)->getSiteConfig(Site::whereSiteId($site_id)->first());

        $urls = array_column(Tracker::query()->orderBy('priority')
            ->orderByDesc('last_connected_at')->select('url')->get()->toArray(), 'url');

        $urlsWithHashes = [];
        foreach ($urls as $url) {
            $hashValue = sha1($url.$site_id);
            $urlsWithHashes[] = [
                'url' => $url,
                'hash_value' => $hashValue,
            ];
        }

        usort($urlsWithHashes, function ($a, $b) {
            return strcmp($a['hash_value'], $b['hash_value']);
        });

        $sortedUrls = array_column($urlsWithHashes, 'url');
        $sortedUrls = Arr::take($sortedUrls, 5);

        foreach ($siteConfig->prefered_Trackers ?? [] as $key => $url) {
            array_push($sortedUrls, $url);
        }

        return $sortedUrls;
    }

    public function getUpdatedTrackerSitePeers(string $site_id): ResultContainer
    {
        $rc = new ResultContainer;
        $rc->operation_successful = true;

        $site = H::getCached(CachePrefixes::site_.$site_id, Site::whereSiteId($site_id));

        $trackersAnnounceInterval = ( ! H::isDevNode()) ? 1800 : 10;

        $trackers_site_peers_cached_at = $site->trackers_site_peers_cached_at ?? 0;

        $sitePeerslifeLength = Carbon::parse($trackers_site_peers_cached_at)->diffInSeconds(now());

        if ($sitePeerslifeLength > $trackersAnnounceInterval || ($sitePeerslifeLength > 6 && $site->trackers_site_peers_count == 0)) {
            
            $trackerSitePeers = (new TorrentTrackersController)->announceOrGetPeers($site_id, is_hosting: $site->is_hosted, returnPeersAsap: true);
            $trackerSitePeers = $trackerSitePeers['hosting'];

            foreach ($trackerSitePeers as $key => $trackerSitePeer) {

                $peer_url = $trackerSitePeer['peer_url'] ?? null;
                $peer_ip = $trackerSitePeer['ip'];
                $peer_port = $trackerSitePeer['port'];

                if (! empty($peer_url)) {
                    $urlIp = H::obtainIpForUrl($peer_url);
                    if ($urlIp !== $peer_ip) {
                        $rc = ClientController::peerHttpsStatus($peer_ip, $peer_port);
                        if ($rc->operation_successful && $rc->data != KnownResponses::nok) {
                            $https = $rc->data == 'https';
                            $client_address = H::a(H::prepareIp($peer_ip).':'.$peer_port, https: $https);
                        } else {
                            continue;
                        }
                    } else {
                        $client_address = $peer_url;
                    }

                } else {
                    $rc = ClientController::peerHttpsStatus($peer_ip, $peer_port);
                    if ($rc->operation_successful && $rc->data != KnownResponses::nok) {
                        $https = $rc->data == 'https';
                        $client_address = H::a(H::prepareIp($peer_ip).':'.$peer_port, https: $https);
                    } else {
                        continue;
                    }
                }

                if (in_array($client_address, H::getSelfAddresses())) {
                    info('getUpdatedTrackerSitePeers Not adding peer as is self');

                    continue;
                }

                info('getUpdatedTrackerSitePeers before saving: '.$client_address);

                $sitePeer = SitePeer::where([
                    ['client_address', $client_address],
                    ['site_id', $site_id],
                ])->first();

                if (! $sitePeer) {
                    $sitePeer = new SitePeer([
                        'client_address' => $client_address,
                        'site_id' => $site_id,
                        'source' => 'tracker',
                    ]);
                    $sitePeer->save();
                } else {
                    $sitePeer->archived_at = null;
                    $sitePeer->save();
                }

                $closure = function () use ($sitePeer) {
                    (new BackgroundProcessingController)->verifySitePeer($sitePeer->id);
                };

                H::dispatchInternalAsyncClosureWrapper($closure);

                $peer = Peer::where('client_address', $client_address)->first();
                if (! $peer) {
                    $peer = new Peer;
                    $peer->client_address = $client_address;
                    $peer->save();
                }
            }

            $metadata = 'Using trackers as a source of Peers';
        } else {
            $metadata = 'Using known Site Peers as a source of Peers';
        }
        $sitePeers = SitePeer::whereSiteId($site_id)->where('source', 'tracker')->get();

        $rc->data = $sitePeers;
        $rc->debug_data = [];
        $rc->metadata = $metadata.' | Cached Site Peers seconds: '.round($sitePeerslifeLength, 0).' | trackers_site_peers_cached_at: '.$trackers_site_peers_cached_at;

        return $rc;
    }
}
