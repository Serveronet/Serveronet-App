<?php

namespace App\Services;

use App\Dicts\ActionTypes;
use App\Dicts\RecordStates;
use App\Http\Controllers\Controller;
use App\Http\H;
use App\Models\CrowdQuery;
use App\Models\CrowdQueryResult;
use App\Models\PassiveSession;
use App\Models\ResultContainer;
use App\Models\Site;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class RemotePeerService extends Controller
{
    public function askPeersForQueryResult(Request $request): ResultContainer
    {
        CrowdQuery::where('created_at', '<', now()->subMinutes(2))->delete();

        info('askPeersForQueryResult');

        $rc = new ResultContainer;
        $rc->operation_successful = true;

        $query_parameters = $request->query_parameters;
        $site_id = $request->site_id;
        info('ttl '.$request->ttl);
        $originator_peer_id = $request->originator_peer_id;

        $ttl = $request->ttl;
        if ($ttl <= 0) {
            $rc->operation_successful = false;
            $rc->error_message = 'TTL exhausted';

            return $rc;
        }

        $crowdQuery = new CrowdQuery;
        $crowdQuery->query_id = Str::random(40);
        $crowdQuery->query_parameters = json_encode($query_parameters); // Array

        $crowdQuery->site_id = $site_id;
        $crowdQuery->action_type = ActionTypes::crowd_query;
        $crowdQuery->ttl = $ttl;
        $crowdQuery->save();
        $query_id = $crowdQuery->query_id;

        H::savePassiveTriggersForSite($site_id);

        $peers = (new PeerMixService)->getPeerMix(site_id: $site_id);

        // info('$peers');
        // info(json_encode($peers));

        $peer_mix = $peers->pluck('client_address');
        $livePassiveSessionsForSite = PassiveSession::where('last_heartbeat_at', '>', now()->subMinutes(1)->toDateTimeString())
            ->where('site_ids_json', 'like', '%'.$site_id.'%')->get();
        $site = Site::whereSiteId($site_id)->with('most_recent_site_definition')->first();
        $trustedSitePeersArray = (new SiteConfigService)->getSiteConfig($site)->trusted_Site_Peers;

        info('$livePassiveSessionsForSite: '.json_encode($livePassiveSessionsForSite));
        if (count($peers) == 0 && count($livePassiveSessionsForSite) == 0) {
            $rc->operation_successful = false;
            $rc->error_message = 'No peers to handle the query';

            return $rc;
        }

        $ttl--;
        $peerCount = 0;
        $crowdPeers = collect();
        foreach ($peers as $peer) {
            $requestConfigSet = [];
            $peerCount++;
            $requestConfigSet['client_address'] = H::a($peer->client_address);

            if (isset($query_parameters['visitor_files_query'])) {

                $requestConfigSet['address'] = H::a($peer->client_address).'p2p_api/v1/p2p_visitor_files';

            } else {

                $requestConfigSet['address'] = H::a($peer->client_address).'p2p_api/v1/p2p_query_endpoint';

            }

            $requestConfigSet['site_id'] = $site_id;
            $requestConfigSet['query_parameters'] = $query_parameters;
            $requestConfigSet['ttl'] = $ttl;
            $requestConfigSet['originator_peer_id'] = $originator_peer_id;
            $requestConfigSet['remote_peer_id'] = Str::random();
            $requestConfigSet['is_trusted_site_peer'] = in_array($requestConfigSet['client_address'], $trustedSitePeersArray) ?? false;

            if ($requestConfigSet['is_trusted_site_peer'] ?? false) {
                $requestConfigSet['trusted_site_peer_token'] = encrypt(now()->toDateTimeString());
            }

            $requestConfigSet['query_id'] = $crowdQuery->query_id;
            info('$requestConfigSet '.json_encode($requestConfigSet));

            $crowdPeers->push($requestConfigSet);

            H::dispatchInternalAsync('handle_crowd_query_order', $requestConfigSet);
        }

        $delays = [
            ['delay' => 0_100_000,  'minRequired' => 3], // 0
            ['delay' => 0_300_000,  'minRequired' => 3], // 1
            ['delay' => 0_500_000,  'minRequired' => 3], // 2
            ['delay' => 1_000_000,  'minRequired' => 2], // 3
            ['delay' => 1_500_000,  'minRequired' => 2], // 4
            ['delay' => 2_000_000,  'minRequired' => 2], // 5
            ['delay' => 2_500_000,  'minRequired' => 1], // 6
            ['delay' => 3_500_000,  'minRequired' => 1], // 7
            // Max 10 sec
        ];

        $delayReached = 0;

        $crowdQueryResults = [];
        $trustedSitePeerResponded = false;

        $debug_data['query_id'] = $query_id;

        $debug_data['trustedSitePeerResponded'] = tfyn($trustedSitePeerResponded);
        $debug_data['Peer Mix'] = json_encode($peer_mix);
        $debug_data['Passive peers count'] = count($livePassiveSessionsForSite);

        foreach ($delays as $key => $d) {
            $delayReached++;
            $debug_data['Delay Reached'] = $delayReached.' '.($delays[$delayReached]['delay'] ?? '');

            $crowdQueryResultsCount = CrowdQueryResult::where('query_id', $query_id)->count();
            info($d['delay'].' $crowdQueryResultsCount: '.$crowdQueryResultsCount);
            if ($crowdQueryResultsCount == 0) {
                usleep($d['delay']);

                continue;
            }

            $crowdQueryResults = CrowdQueryResult::where('query_id', $query_id)
                ->select('payload', 'fulfiller_id', 'remote_peer_id', 'trusted_site_peer_token')->get();

            $trustedSitePeerResults = $crowdQueryResults->whereNotNull('trusted_site_peer_token');

            foreach ($trustedSitePeerResults as $key => $result) {
                try {
                    $decyptedToken = decrypt($result->trusted_site_peer_token);
                    $queryTS = Carbon::parse($decyptedToken);
                    $validTrustedResponse = $queryTS->diffInMinutes(now(), absolute: true) < 10;
                    if ($validTrustedResponse) {
                        $trustedSitePeerResponded = true;
                        break;
                    }
                } catch (Throwable $th) {
                }
            }

            $minRequiredAdjusted = false;
            $minRequired = $d['minRequired'];
            if ($minRequired > count($peers)) {
                $minRequiredAdjusted = true;
                $minRequired = count($peers);
            }

            if ((count($crowdQueryResults) >= $minRequired
                ||
                ($peerCount < 2 && count($crowdQueryResults) > 0))
                || $trustedSitePeerResponded) {

                $completedCrowdQuery = CrowdQuery::where([
                    ['query_id', $query_id],
                ])->first();
                $completedCrowdQuery->state = RecordStates::query_consumed;
                $completedCrowdQuery->save();

                $allResults = [];
                $crowdQueryFinalResults = CrowdQueryResult::where('query_id', $query_id)
                    ->select('payload', 'fulfiller_id', 'remote_peer_id', 'trusted_site_peer_token')->get();

                foreach ($crowdQueryFinalResults as $key => $result) {
                    $records = json_decode($result->payload);
                    foreach ($records as $key => $record) {
                        $record->fulfiller_id = $result->fulfiller_id;
                        array_push($allResults, $record);
                    }
                }

                $debug_data['Count of crowdQueryResults'] = count($crowdQueryFinalResults);
                $debug_data['Fulfillers'] = $crowdQueryFinalResults->pluck('fulfiller_id');
                $debug_data['askPeersForQueryResult count'] = count($allResults);

                $rc->data = $allResults;

                $crowdQueryCondition = 'Unknown';
                if (count($crowdQueryResults) >= $minRequired) {
                    $crowdQueryCondition = 'Minimum peers count reached. Min Required Adjusted: '.tfyn($minRequiredAdjusted);
                } elseif ($peerCount < 2 && count($crowdQueryResults) > 0) {
                    $crowdQueryCondition = 'Minimum peers count for low peer count available';
                } elseif ($trustedSitePeerResponded) {
                    $crowdQueryCondition = 'Trusted responded';
                }

                $rc->debug_data['Resolve Condition'] = $crowdQueryCondition;
                $rc->debug_data['Debug Data'] = $debug_data;

                return $rc;

            } else {
                usleep($d['delay']);

                continue;
            }
        }

        $failedCrowdQuery = CrowdQuery::where('query_id', $query_id)->first();
        $failedCrowdQuery->state = RecordStates::query_failed;
        $failedCrowdQuery->save();

        $crowdPeersfiltered = $crowdPeers->map(function ($peer) {
            return collect($peer)->only(['client_address', 'is_trusted_site_peer', 'ttl', 'address']);
        });

        $rc->operation_successful = false;
        $rc->error_message = 'Crowd query has failed - timeout expired';
        $rc->debug_data = json_encode($debug_data).' | crowdPeers: '.json_encode($crowdPeersfiltered);

        return $rc;
    }
}
