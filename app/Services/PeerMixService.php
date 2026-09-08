<?php

namespace App\Services;

use App\Dicts\CachePrefixes;
use App\Dicts\DataTypes;
use App\Dicts\SettingIds;
use App\Http\H;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\BackgroundProcessingController;
use App\Http\Controllers\Controller;
use App\Models\CachedResource;
use App\Models\InternalRequest;
use App\Models\LocalTrackerInfoHashPeer;
use App\Models\Peer;
use App\Models\PeerReplicationSession;
use App\Models\ReplicationSession;
use App\Models\Site;
use App\Models\SiteDefinition;
use App\Models\SitePeer;
use App\Models\Tracker;
use App\Models\Visitor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

class PeerMixService extends Controller
{
    function getPeerMix($site_id, $excludedPeers = []): Collection 
    {
        $peerMixSize = 15;
        $minReputation = -2000;
        
        $useSitePeersEnabledInSettings = H::getSettVal(SettingIds::use_source_site_peers);

        $peerMix = collect();

        $trustedSitePeers = collect();
        $sitePeersVerified = collect();
        $sitePeersConnectable = collect();
        $anySitePeers = collect();
        $trackerHostingPeers = collect();

        if ($useSitePeersEnabledInSettings) {
            
            /* Trusted */
            $site = Site::whereSiteId($site_id)->with('most_recent_site_definition')->first();
            if ($site) {
                $trustedSitePeersSiteConfig = collect((new SiteConfigService)
                ->getSiteConfig($site)?->trusted_Site_Peers ?? collect())->shuffle();

                foreach ($trustedSitePeersSiteConfig->take(3) as $key => $client_address) {
                    if (in_array($client_address, H::getSelfAddresses()))
                    continue;

                    if (filter_var($client_address, FILTER_VALIDATE_URL) === false)
                    continue;

                    $trustedSitePeer = SitePeer::where([
                        ['client_address', $client_address],
                        ['site_id', $site_id],
                    ])->with('peer')->first();

                    if (! $trustedSitePeer) {
                        $peer = Peer::where('client_address', $client_address)->first();
                        if (! $peer) {
                            $peer = new Peer(['client_address' => $client_address,]);
                            $peer->save();
                        }
                        $trustedSitePeer = new SitePeer([
                            'client_address' => $client_address,
                            'site_id' => $site_id,
                            'source' => 'trusted',
                        ]);
                        $trustedSitePeer->save();
                        $closure = function() use ($trustedSitePeer) {
                            (new BackgroundProcessingController())->verifySitePeer($trustedSitePeer->id);
                        };
                        H::dispatchInternalAsyncClosureWrapper($closure);
                    }
                    if (($trustedSitePeer->peer->reputation ?? 0) > $minReputation) {
                        $trustedSitePeer->debug_source = 'trusted_site_peers';
                        $trustedSitePeers->push($trustedSitePeer);
                    }
                }
            }
            
            /* Verified */
            $sitePeersMostRecentVerifiedBuilder = SitePeer::query();
            $sitePeersMostRecentVerifiedBuilder->whereSiteId($site_id)
            ->where('source', 'replication')
            ->withoutArchived()->withoutSelf()
            ->orderByDesc('last_successfully_verified_at')->with('peer');

            $sitePeersMostRecentVerifiedBuilder
            ->where(function (Builder $query) use ($minReputation) {
                return $query->whereRelation('peer', 'reputation', '>=', $minReputation)
                        ->orWhereDoesntHave('peer');
            });
            $sitePeersVerified = $sitePeersMostRecentVerifiedBuilder->select(SitePeer::$publicProperties)->take(10)->get();

            /* Connectable */
            $sitePeersRecentlyConnectableBuilder = SitePeer::query();
            $sitePeersRecentlyConnectableBuilder->whereNotNull('last_successfully_verified_at')
            ->withoutArchived()->where('source', 'replication')
            ->with('peer')->whereRelation('peer', 'reputation', '>=', 0);
            $sitePeersRecentlyConnectableBuilder->whereSiteId($site_id);
            $sitePeersConnectable = $sitePeersRecentlyConnectableBuilder->select(SitePeer::$publicProperties)->take(10)->get(); //->inRandomOrder();

            /* Any Site Peer */
            $anySitePeerBuilder = SitePeer::whereSiteId($site_id)->withoutArchived()->withoutSelf()
            ->select(SitePeer::$publicProperties)->take(10);
            $anySitePeers = $anySitePeerBuilder->get();
        }      
        
        if (H::getSettVal(SettingIds::use_source_trackers) ) {
            $site = Site::whereSiteId($site_id)->first();
            if ($site) {
                $trackerPeersRC = (new TorrentService)->getUpdatedTrackerSitePeers($site_id);
                $trackerPeers = $trackerPeersRC->data;
                $trackerPeersMetadata = $trackerPeersRC->metadata;
            }
         
            $trackerHostingPeers = collect($trackerPeers['hosting'] ?? [])->take(10);
            
            foreach ($trackerHostingPeers as $key => $trackerHostingPeer) {
                $sitePeer = new SitePeer([
                    'client_address' => H::prepareIp($trackerHostingPeer['ip']).':'.$trackerHostingPeer['port'],
                    'site_id' => $site_id,
                    'source' => 'tracker',
                ]);
                $sitePeer->debug_source = 'trackers | '.$trackerPeersMetadata;
                $trackerHostingPeers->push($sitePeer);
            }
        }

        $targetPeersBreakdown = [
            'trusted' => 0.3,
            'verified' => 0.3,
            'trackers' => 0.3,
            'connectable' => 0.3,
        ];

        $targetPeersBreakdown = [
            'trusted' => (int)ceil($targetPeersBreakdown['trusted'] * $peerMixSize),
            'verified' => (int)ceil($targetPeersBreakdown['verified'] * $peerMixSize),
            'trackers' => (int)ceil($targetPeersBreakdown['trackers'] * $peerMixSize),
            'connectable' => (int)ceil($targetPeersBreakdown['connectable'] * $peerMixSize),
        ];
        
        //trustedSitePeers
        $peerMix = $peerMix->merge($trustedSitePeers->take($targetPeersBreakdown['trusted']));

        //sitePeersVerified
        $peerMix = $peerMix->merge($sitePeersVerified->take($targetPeersBreakdown['verified']));

        //trackerPeers
        $peerMix = $peerMix->merge($trackerHostingPeers->take($targetPeersBreakdown['trackers']));

        //connectablePeers
        $peerMix = $peerMix->merge($sitePeersConnectable->take($targetPeersBreakdown['connectable']));

        //anySitePeers
        if ($peerMix->count() < $peerMixSize)
        $peerMix = $peerMix->merge($anySitePeers->take($peerMixSize - $peerMix->count()));

        $peerMix = $peerMix->whereNotIn('client_address', H::getSelfAddresses());

        $peerMix = $peerMix->whereNotIn('client_address', $excludedPeers);

        $uniquePeers = $peerMix->unique(function ($item) {
            return $item->client_address;
        });

        return $uniquePeers;
    }
}