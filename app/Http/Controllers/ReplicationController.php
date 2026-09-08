<?php

namespace App\Http\Controllers;

use App\Dicts\DataTypes;
use App\Dicts\PeerReplicationSessionStates;
use App\Dicts\ReplicationSessionStates;
use App\Http\Consts;
use App\Http\H;
use App\Models\PeerReplicationSession;
use App\Models\ReplicationSession;
use App\Models\Site;
use App\Services\P2pReplicationService;
use App\Services\PeerMixService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Throwable;

class ReplicationController extends Controller
{
    public function peerReplicationSessionHandler(Request $request)
    {
        $requestConfigSet = H::unwrapRequestConfigSet();

        if (! H::isInternalCallCurrent($requestConfigSet)) {
            info('Outdated or spoofed internal request');

            return;
        }
        $internalRequestId = H::startInteralRequestReporting(__FUNCTION__);

        $peerSessionDefinition = $requestConfigSet;
        $peer_replication_session_id = $peerSessionDefinition['peer_replication_session_id'];
        $session_id = $peerSessionDefinition['session_id'];
        $site_id = $peerSessionDefinition['site_id'];
        $from = $peerSessionDefinition['from'];
        $to = $peerSessionDefinition['to'];
        $data_type = $peerSessionDefinition['data_type'];
        $startOfSession = Carbon::parse($peerSessionDefinition['startOfSession']);
        $max_execution_time = $peerSessionDefinition['max_execution_time'];

        $peerReplicationSession = PeerReplicationSession::find($peer_replication_session_id);
        info('onPeerReplicationSessionHandler: '.$peerReplicationSession->client_address);
        $peer = $peerReplicationSession;

        $client_address = $peer->client_address;
        info('peerReplicationSessionHandler $peerSessionDefinition '.$client_address);

        $peerReplicationSession->retries++;
        $peerReplicationSession->detailed_state = 'inc retry';
        $peerReplicationSession->save();

        $replicationSession = ReplicationSession::where('session_id', $session_id)->first();
        $replicationSession->last_heartbeat = now();
        $replicationSession->save();

        try {
            /* Request for page 1 with count_only to get total count */
            $url = H::a($peer->client_address).'p2p_api/v1/request_modified_between_dates';

            $client = H::setupClient(H::isTorAddress($peer->client_address));
            $options = [
                'timeout' => H::timeoutAdjust(H::isTorAddress($peer->client_address), 10),
                'form_params' => [
                    'data_type' => $data_type,
                    'from' => $from,
                    'to' => $to,
                    'site_id' => $site_id,
                    'count_only' => true,
                ],
                'prepare_ip' => true,
            ];
            H::prepareOptions($options, $url);
            $response = $client->post($url, $options);

            $body = (string) $response->getBody();
            $json_payload = $body;
            $page = json_decode($json_payload);

            ClientController::handlePeerIsConnectable($peer->client_address);

        } catch (Throwable $th) {
            $peerReplicationSession->state = PeerReplicationSessionStates::failed;
            $peerReplicationSession->detailed_state = 'first_page_retrieval_failed';
            if (H::isNetworkAvailable()) {
                $peerReplicationSession->retries++;
                ClientController::handlePeerNotConnectable($th, $peer->client_address);
            }
            $peerReplicationSession->save();
            info($th->getMessage().' '.$th->getFile().' '.$th->getLine());

            return $client_address.' '.$peerReplicationSession->state;
        }

        try {
            $total_records = $page->data->count;
            $record_count_map_json = $page->data->record_count_map ?? [];

            $per_page = $page->data->per_page;

            $peerReplicationSession->total_records = $total_records;

            $peerReplicationSession->per_page = $per_page;
            $peerReplicationSession->record_count_map = $record_count_map_json;
            $peerReplicationSession->state = PeerReplicationSessionStates::ongoing;
            $peerReplicationSession->detailed_state = 'count_known';
            $peerReplicationSession->last_heartbeat = now();
            $peerReplicationSession->save();

            $replicationSession = ReplicationSession::where('session_id', $session_id)->first();
            $replicationSession->last_heartbeat = now();
            $replicationSession->save();

            if (! $per_page) {

                $peerReplicationSession->detailed_state = 'no_per_page';
                $peerReplicationSession->state = PeerReplicationSessionStates::failed;
                $peerReplicationSession->save();

                return $client_address.' '.$peerReplicationSession->state;

            } else {
                $peerReplicationSession->retries = 0;
                $peerReplicationSession->detailed_state = 'total_records_present';
                $peerReplicationSession->save();
            }
            ClientController::handlePeerIsConnectable($peer->client_address);

            if ($total_records == 0) {

                $peerReplicationSession->state = PeerReplicationSessionStates::completed;
                $peerReplicationSession->detailed_state = 'no_rows_so_skipping';
                $peerReplicationSession->save();

                return $client_address.' '.$peerReplicationSession->state;

            } else {
                $peerReplicationSession->detailed_state = 'total_gt_0';
                $peerReplicationSession->save();
            }

            /* First request is disregarded to decrease complexity */

        } catch (Throwable $th) {
            info($th->getMessage().' '.$th->getFile().' '.$th->getLine());
            $peerReplicationSession->state = PeerReplicationSessionStates::failed;
            $peerReplicationSession->detailed_state = 'total_count_retrieval_failed';

            if (H::isNetworkAvailable()) {
                ClientController::handlePeerNotConnectable($th, $peer->client_address);
            }

            $peerReplicationSession->save();

            return $client_address.' '.$peerReplicationSession->state;
        }

        $last_completed_date = $peerReplicationSession->last_completed_date;
        $record_count_map = json_decode($record_count_map_json);

        $record_count_map = collect($record_count_map)->sortKeys();
        $record_count_map = $record_count_map->filter(function ($value, $key) use ($last_completed_date) {
            return $key > $last_completed_date;
        });

        foreach ($record_count_map as $key => $countForDay) {
            info('record_count_map. Processing date: '.$key.' '.$client_address);
            $from = Carbon::parse($key);
            $to = Carbon::parse($key)->addDay();
            $pagesCount = ceil($countForDay / $per_page);

            for ($i = $peerReplicationSession->current_page; $i <= $pagesCount; $i++) {

                $pageTimeOverLimit = $startOfSession->diffInSeconds(now()) > $max_execution_time - 5;
                info('pageTimeOverLimit: '.tfyn($pageTimeOverLimit).' | Start Diff: '.round($startOfSession->diffInSeconds(now()), 2)
                    .' | MET: '.$max_execution_time.' '.urlencode($client_address));

                if ($pageTimeOverLimit) {
                    $peerReplicationSession->state = PeerReplicationSessionStates::execution_time_exceeded;
                    $peerReplicationSession->detailed_state = 'session_timeout s: '.$startOfSession->diffInSeconds(now());
                    $peerReplicationSession->save();

                    return $client_address.' '.$peerReplicationSession->state;
                }

                $peerReplicationSession->current_page = $i;
                $peerReplicationSession->retries = 0;
                $peerReplicationSession->state = PeerReplicationSessionStates::ongoing;
                $peerReplicationSession->save();
                try {
                    $response = $client->post($url, [
                        'timeout' => H::timeoutAdjust(H::isTorAddress($peer->client_address), 10),
                        'form_params' => [
                            'data_type' => $data_type,
                            'from' => $from->toDateTimeString(),
                            'to' => $to->toDateTimeString(),
                            'site_id' => $site_id,
                            'page' => $i,
                        ],
                    ]);
                    $body = (string) $response->getBody();
                    $json_payload = $body;
                    $page = json_decode($json_payload);

                    $recordsCollection = $page->data;

                    info('Passing to handleIncomingRecords count '.count($recordsCollection).' '.$client_address);

                    /* handleIncomingRecords */
                    (new P2pReplicationService)->handleIncomingRecords($data_type, collect($recordsCollection)->toJson());

                } catch (Throwable $th) {
                    $peerReplicationSession->state = PeerReplicationSessionStates::failed;
                    $peerReplicationSession->detailed_state = 'page_retrieval_failed';
                    $peerReplicationSession->retries++;
                    $peerReplicationSession->save();
                    info($th->getMessage().' '.$th->getFile().' '.$th->getLine());

                    if (H::isNetworkAvailable()) {
                        ClientController::handlePeerNotConnectable($th, $peer->client_address);
                    }

                    return $client_address.' '.$peerReplicationSession->state;
                }

                if ($i == $pagesCount) {
                    $peerReplicationSession->state = PeerReplicationSessionStates::completed;
                    $peerReplicationSession->detailed_state = 'all_pages_completed for day: '.$key;
                    $peerReplicationSession->save();
                }

                info('Page complete '.$i.' '.urlencode($client_address));
            }

            info('last_completed_date all pages done '.urlencode($client_address));
            $peerReplicationSession->last_completed_date = $key;
            $peerReplicationSession->detailed_state = 'last_completed_date all pages done';
            $peerReplicationSession->current_page = 1;
            $peerReplicationSession->save();
        }

        H::endInteralRequestReporting($internalRequestId);

        return $client_address.' '.$peerReplicationSession->state;
    }

    public static function replicateMissedData($secondsLimit = null, $pfm = false)
    {
        info('replicateMissedData 0');

        self::cleanUpReplicationSessions();

        $requestConfigSet = H::unwrapRequestConfigSet();

        $internalRequestId = H::startInteralRequestReporting(__FUNCTION__);

        if (! H::isInternalCallCurrent($requestConfigSet)) {
            info('Outdated or spoofed internal request');

            return;
        }

        $site_id = $requestConfigSet['site_id'];
        $data_type = $requestConfigSet['visitors_data_type'];
        $continous_ondemand = $requestConfigSet['continous_ondemand'] ?? false;
        $pfm = $requestConfigSet['pfm'] ?? $pfm;

        $db_field = self::determineDbField($data_type);

        info('Start replicateMissedData New Request site_id: '.$site_id.' continous_ondemand: '.$continous_ondemand);

        $site = Site::whereSiteId($site_id)->with('most_recent_site_definition')->first();
        $siteReplicationEndTs =
        Carbon::parse($site->{$db_field} ?? Consts::startOfServeronet);

        if ($siteReplicationEndTs->isAfter(now()->subMinutes(self::getOutdatementMinutes()))) {
            info($data_type.' Replication up to date');
            H::updateSitesHostedState(site_id: $site_id, pfm: $pfm);

            return;
        } else {
            info($data_type.' Replication NOT up to date');
        }

        $replicationSession = ReplicationSession::where([
            ['data_type', $data_type],
            ['site_id', $site_id],
        ])->whereNull('archived_at')->first();

        $newReplicationEndTs = now();

        $doCurrentReplication = false;

        if (! $replicationSession) {
            $replicationSession = new ReplicationSession;
            $replicationSession->session_id = Str::random();
            $replicationSession->data_type = $data_type;
            $replicationSession->site_id = $site_id;
            $replicationSession->state = ReplicationSessionStates::created;
            $replicationSession->from = $siteReplicationEndTs;
            $replicationSession->to = $newReplicationEndTs;
            $replicationSession->save();
        }

        if ($replicationSession->state == ReplicationSessionStates::failed && $replicationSession->retries < 3) {
            $replicationSession->retries++;
            $replicationSession->detailed_state = 'failed and retrying';
            $replicationSession->save();
            $doCurrentReplication = true;
        } elseif ($replicationSession->state == ReplicationSessionStates::created) {
            $doCurrentReplication = true;
        } elseif ($replicationSession->state == ReplicationSessionStates::ongoing
        && Carbon::parse($replicationSession->last_heartbeat)->diffInMinutes(now()) > 2) {
            $replicationSession->detailed_state = 'resuming was required';
            $replicationSession->save();
            $doCurrentReplication = true;
        } elseif ($replicationSession->state == ReplicationSessionStates::ongoing) {
            $replicationSession->detailed_state = 'Is ongoing';
            $replicationSession->save();
            $doCurrentReplication = true;
        } elseif ($replicationSession->state == ReplicationSessionStates::completed) {

            self::archiveConsumedReplicationSessions($replicationSession);

            return;
        }

        $session_id = $replicationSession->session_id;

        info('doCurrentReplication before '.$doCurrentReplication.' '.$replicationSession->state);
        $startedAt = now();
        if ($doCurrentReplication) {

            $replicationSession->state = ReplicationSessionStates::ongoing;
            $replicationSession->last_heartbeat = now();
            $replicationSession->save();

            // Loop start
            for ($i = 0; $i < (H::getMaxExecutionTime() / 2) - 4; $i++) {

                /*
                *
                * dispatchActionableActions
                *
                */

                $activePRSes = self::actionAndManageSessions(
                    $siteReplicationEndTs,
                    $data_type,
                    $site->site_id,
                    $session_id,
                );

                self::dispatchActionableActions(
                    $data_type,
                    $siteReplicationEndTs,
                    $newReplicationEndTs,
                    $site_id,
                    $session_id,
                    pfm: $pfm
                );

                $activePRSes = self::actionAndManageSessions(
                    $siteReplicationEndTs,
                    $data_type,
                    $site->site_id,
                    $session_id,
                );
                info('actionAndManageSessions End Second '.$activePRSes->count());

                if ($activePRSes->count() == 0) {
                    info('No more $activePRSes  '.$activePRSes->count());

                    $replicationSession->state = ReplicationSessionStates::completed;
                    $replicationSession->detailed_state = 'No actionable PRSes';
                    $replicationSession->save();

                    self::setNewReplicationEndTS($site_id, $data_type, $newReplicationEndTs);

                    self::archiveConsumedReplicationSessions($replicationSession);
                    
                    info('replicateMissedData | activePRSes - 0 | Ending as completed');
                } else {
                    info('Before sleep(2)');
                    sleep(2);
                }

                $endedAt = now();
                $secondsTaken = $startedAt->diffInSeconds($endedAt);
                info('$secondsTaken: '.round($secondsTaken, 2));
                if ($secondsTaken > H::getMaxExecutionTime() - 1) {
                    break;
                }
            }

            info('replicateMissedData sleep Loop end');

        } else {
            unset($session_id);
            H::updateSitesHostedState($site_id, pfm: $pfm);
        }

        H::endInteralRequestReporting($internalRequestId);

        info('End of replicateMissedData | Cont ondem: '.tfyn($continous_ondemand).' | DT: '.$data_type.' '.$site_id);
        if ($continous_ondemand) {

            usleep(100);

            $requestConfigSet = [];
            $requestConfigSet['site_id'] = $site_id;
            $requestConfigSet['continous_ondemand'] = true;
            $requestConfigSet['visitors_data_type'] = $data_type;
            $requestConfigSet['pfm'] = $pfm;

            H::dispatchInternalAsync('replicate_missed_data', $requestConfigSet);

            return;
        }
    }

    public static function cleanUpReplicationSessions(): void
    {
        $expiredReplicationSessions = ReplicationSession::whereNotNull('archived_at')
            ->where('archived_at', '<', now()->subMinutes(3)->toDateTimeString())->get();

        foreach ($expiredReplicationSessions as $key => $session) {
            $session->delete();
        }

        $expiredPeerReplicationSessions = PeerReplicationSession::whereNotNull('archived_at')
            ->where('archived_at', '<', now()->subMinutes(3)->toDateTimeString())->get();
        foreach ($expiredPeerReplicationSessions as $key => $session) {
            $session->delete();
        }
    }

    public static function determineDbField(string $data_type): string
    {
        $fields = [
            DataTypes::visitor_records => 'visitor_records_replication_end_ts',
            DataTypes::visitor_resources => 'visitor_resources_replication_end_ts',
            DataTypes::site_definitions => 'site_definitions_replication_end_ts',
            DataTypes::site_peers => 'site_peers_replication_end_ts',
        ];

        return $fields[$data_type];
    }

    public static function setNewReplicationEndTS($site_id, $data_type, $newReplicationEndTs)
    {
        if ($data_type == DataTypes::visitor_records) {
            $site = Site::whereSiteId($site_id)->first();
            $site->visitor_records_replication_end_ts = $newReplicationEndTs;
            $site->save();
        } elseif ($data_type == DataTypes::visitor_resources) {
            $site = Site::whereSiteId($site_id)->first();
            $site->visitor_resources_replication_end_ts = $newReplicationEndTs;
            $site->save();
        } elseif ($data_type == DataTypes::site_definitions) {
            $site = Site::whereSiteId($site_id)->first();
            $site->site_definitions_replication_end_ts = $newReplicationEndTs;
            $site->save();
        } elseif ($data_type == DataTypes::site_peers) {
            $site = Site::whereSiteId($site_id)->first();
            $site->site_peers_replication_end_ts = $newReplicationEndTs;
            $site->save();
        }
    }

    public static function archiveConsumedReplicationSessions($replicationSession): void
    {
        if ($replicationSession->archived_at != null) {
            return;
        }

        $replicationSession->archived_at = now();
        $replicationSession->save();
        $relatedPeerSessions = PeerReplicationSession::where([
            ['session_id', $replicationSession->session_id],
        ])->get();
        foreach ($relatedPeerSessions as $key => $session) {
            $session->archived_at = now();
            $session->save();
        }
    }

    public static function actionAndManageSessions(
        $siteReplicationEndTs,
        $data_type,
        $site_id,
        $session_id,
    ): SupportCollection {

        $actionablePeerReplicationSessions = collect();

        $pendingPeerReplicationSessions = PeerReplicationSession::where([
            ['session_id', $session_id],
        ])->whereIn('state', [

            PeerReplicationSessionStates::created,
            PeerReplicationSessionStates::failed,
            PeerReplicationSessionStates::execution_time_exceeded,
            PeerReplicationSessionStates::ongoing,

        ])->whereNull('archived_at')->get();

        foreach ($pendingPeerReplicationSessions as $key => $session) {
            if ($session->state == PeerReplicationSessionStates::ongoing && Carbon::parse($session->last_heartbeat)->diffInMinutes(now()) > 2) {
                $session->detailed_state = 'Reinstated as was stalled';
                $session->state = PeerReplicationSessionStates::created;
                $session->save();
                $actionablePeerReplicationSessions->push($session);

            } elseif ($session->state == PeerReplicationSessionStates::ongoing) {
                $session->detailed_state = 'Is ongoing';
                $session->save();
                $actionablePeerReplicationSessions->push($session);

            } elseif ($session->state == PeerReplicationSessionStates::execution_time_exceeded && $session->retries < 1000) {
                $session->state = PeerReplicationSessionStates::created;
                $session->detailed_state = 'Reinstated as timed out';
                $session->save();
                $actionablePeerReplicationSessions->push($session);

            } elseif ($session->state == PeerReplicationSessionStates::failed && $session->retries < 10) {
                $session->state = PeerReplicationSessionStates::created;
                $session->detailed_state = 'Reinstated as failed';
                $session->save();
                $actionablePeerReplicationSessions->push($session);

            } elseif ($session->state == PeerReplicationSessionStates::created) {
                $session->detailed_state = 'Created';
                $session->save();
                $actionablePeerReplicationSessions->push($session);

            } else {
                $session->state = 'Ignored as not handled';
                $session->detailed_state = 'Prev state: '.$session->state;
                $session->save();
            }
        }

        return $actionablePeerReplicationSessions;
    }

    public static function dispatchActionableActions($data_type, $from, $to, $site_id = null, $session_id = null, $pfm = false)
    {
        info('dispatchActionableActions - start ');
        $startOfSession = now();
        $max_execution_time = H::getMaxExecutionTime();
        $max_execution_time = $max_execution_time - 1;
        $from = Carbon::parse($from);

        $actionablePeerReplicationSessions = collect();

        $currentCreatedPeerReplicationSessions = PeerReplicationSession::where([
            ['session_id', $session_id],
        ])
            ->whereIn('state', [
                PeerReplicationSessionStates::created,
            ])->whereNull('archived_at')->get();

        if ($currentCreatedPeerReplicationSessions->count() < 5) {
            $actionablePeerReplicationSessions = collect();
            $peerMix = (new PeerMixService)->getPeerMix(site_id: $site_id);
            foreach ($peerMix as $key => $peer) {
                $currenPeerAdded = PeerReplicationSession::where([
                    ['session_id', $session_id],
                ])
                    ->where('client_address', $peer->client_address)
                    ->first();

                if (! $currenPeerAdded) {
                    $peerReplicationSession = new PeerReplicationSession;
                    $peerReplicationSession->client_address = $peer->client_address;
                    $peerReplicationSession->session_id = $session_id;
                    $peerReplicationSession->state = PeerReplicationSessionStates::created;
                    $peerReplicationSession->save();
                    $actionablePeerReplicationSessions->push($peerReplicationSession);
                }

            }
        }

        $currentCreatedPeerReplicationSessions = PeerReplicationSession::where([
            ['session_id', $session_id],
        ])
            ->whereIn('state', [
                PeerReplicationSessionStates::created,
            ])->whereNull('archived_at')->get();

        foreach ($currentCreatedPeerReplicationSessions as $key => $session) {
            $actionablePeerReplicationSessions->push($session);
        }

        $peerSessionDefinitions = [];

        foreach ($actionablePeerReplicationSessions as $actionablePeerReplicationSession) {
            $peerSessionDefinitions[] = [
                'peer_replication_session_id' => $actionablePeerReplicationSession->id,
                'session_id' => $session_id,
                'site_id' => $site_id,
                'from' => $from->toDateTimeString(),
                'to' => $to->toDateTimeString(),
                'data_type' => $data_type,
                'startOfSession' => $startOfSession->toDateTimeString(),
                'max_execution_time' => $max_execution_time,
            ];
        }

        foreach ($peerSessionDefinitions as $peerSessionDefinition) {
            H::dispatchInternalAsync('peer_replication_session_handler', $peerSessionDefinition);
        }
    }

    public function replicateMissedSiteDefinitions($continous_ondemand, $pfm = false, $site_id = null)
    {
        info('replicateMissedSiteDefinitions');

        $sitePendingSiteDefinitionReplication = null;
        $sitePendingSiteDefinitionReplicationBuilder = Site::publishedOnly()
            ->where([
                ['is_to_be_hosted', true],
            ])
            ->where(function ($query) {
                $query->where('site_definitions_replication_end_ts', '<',
                    now()->subMinutes(self::getOutdatementMinutes()))
                    ->orWhereNull('site_definitions_replication_end_ts');
            })->with('most_recent_site_definition')->orderBy('site_definitions_replication_end_ts');

        if ($site_id) {
            $sitePendingSiteDefinitionReplicationBuilder->whereSiteId($site_id);
        }

        $sitePendingSiteDefinitionReplication = $sitePendingSiteDefinitionReplicationBuilder->first();

        if (! $sitePendingSiteDefinitionReplication) {
            H::pfm('No sites Pending Replication', pfm: $pfm);

            return;
        }

        H::pfm('sitePendingReplication: '.$sitePendingSiteDefinitionReplication->site_id.' '
        .DataTypes::site_definitions.' '.$sitePendingSiteDefinitionReplication->site_definitions_replication_end_ts, pfm: $pfm);
        $requestConfigSet = [];
        $requestConfigSet['site_id'] = $sitePendingSiteDefinitionReplication->site_id;
        $requestConfigSet['visitors_data_type'] = DataTypes::site_definitions;
        $requestConfigSet['continous_ondemand'] = $continous_ondemand;
        $requestConfigSet['pfm'] = true;

        H::dispatchInternalAsync('replicate_missed_data', $requestConfigSet, 2);
    }

    public function replicateMissedSitePeers($continous_ondemand, $pfm = false, $site_id = null)
    {
        $sitePendingSitePeerReplication = null;
        $sitePendingSitePeerReplicationBuilder = Site::publishedOnly()
            ->where([
                ['is_to_be_hosted', true],
            ])
            ->where(function ($query) {
                $query->where('site_peers_replication_end_ts', '<',
                    now()->subMinutes(self::getOutdatementMinutes()))
                    ->orWhereNull('site_peers_replication_end_ts');
            })->with('most_recent_site_definition')->orderBy('site_peers_replication_end_ts');

        if ($site_id) {
            $sitePendingSitePeerReplicationBuilder->whereSiteId($site_id);
        }

        $sitePendingSitePeerReplication = $sitePendingSitePeerReplicationBuilder->first();

        if (! $sitePendingSitePeerReplication) {
            H::pfm('No sites Pending Replication', pfm: $pfm);

            return;
        }

        H::pfm('sitePendingReplication: '.$sitePendingSitePeerReplication->site_id.' '.DataTypes::site_peers, pfm: $pfm);
        $requestConfigSet = [];
        $requestConfigSet['site_id'] = $sitePendingSitePeerReplication->site_id;
        $requestConfigSet['visitors_data_type'] = DataTypes::site_peers;
        $requestConfigSet['continous_ondemand'] = $continous_ondemand;
        $requestConfigSet['pfm'] = true;

        H::dispatchInternalAsync('replicate_missed_data', $requestConfigSet, 2);
    }

    public function replicateMissedSitesVisitorRecords($continous_ondemand, $pfm = false, $site_id = null)
    {
        $sitePendingReplication = null;
        $sitesBuilder = Site::publishedOnly()
            ->where([
                ['is_to_be_hosted', true],
            ])
            ->where(function ($query) {
                $query->where('visitor_records_replication_end_ts', '<', now()->subMinutes(self::getOutdatementMinutes()))
                    ->orWhereNull('visitor_records_replication_end_ts');
            })->with('most_recent_site_definition')->orderBy('visitor_records_replication_end_ts');

        if ($site_id) {
            $sitesBuilder->whereSiteId($site_id);
        }

        $sites = $sitesBuilder->get();

        H::pfm('sitePendingReplication count '.count($sites), pfm: $pfm);

        foreach ($sites as $key => $site) {
            H::pfm($site->site_id, pfm: $pfm);

            $site_id = $site->site_id;

            $site_Has_Database = json_decode($site->most_recent_site_definition?->site_config_json)->site_Has_Database ?? null;
            if (! $site_Has_Database) {
                continue;
            }

            $sitePendingReplication = $site;
            break;
        }

        if (! $sitePendingReplication) {
            H::pfm('No sites PendingReplication', pfm: $pfm);

            return;
        }

        H::pfm('Starting or resuming replication: '.$site->site_id.' '.DataTypes::visitor_records, pfm: $pfm);
        $requestConfigSet = [];
        $requestConfigSet['site_id'] = $sitePendingReplication->site_id;
        $requestConfigSet['visitors_data_type'] = DataTypes::visitor_records;
        $requestConfigSet['continous_ondemand'] = $continous_ondemand;
        $requestConfigSet['pfm'] = true;

        H::dispatchInternalAsync('replicate_missed_data', $requestConfigSet, 2);
    }

    public function replicateMissedVisitorResources($continous_ondemand, $pfm = false, $site_id = null)
    {
        $sitePendingReplication = null;

        $sitesBuilder = Site::publishedOnly()
            ->where([
                ['is_to_be_hosted', true],
            ])
            ->where(function ($query) {
                $query->where('visitor_resources_replication_end_ts', '<',
                    now()->subMinutes(self::getOutdatementMinutes()))
                    ->orWhereNull('visitor_resources_replication_end_ts');
            })->with('most_recent_site_definition')->orderBy('visitor_resources_replication_end_ts');

        if ($site_id) {
            $sitesBuilder->whereSiteId($site_id);
        }

        $sites = $sitesBuilder->get();

        foreach ($sites as $key => $site) {
            $allow_Visitor_Files = json_decode($site->most_recent_site_definition?->site_config_json)->allow_Visitor_Files ?? false;
            if ($allow_Visitor_Files) {
                $sitePendingReplication = $site;
                break;
            }
        }

        if (! $sitePendingReplication) {
            H::pfm('No sites PendingReplication', pfm: $pfm);

            return;
        }

        H::pfm('sitePendingReplication: '.$site->site_id.' '.DataTypes::visitor_resources, pfm: $pfm);
        $requestConfigSet = [];
        $requestConfigSet['site_id'] = $sitePendingReplication->site_id;
        $requestConfigSet['visitors_data_type'] = DataTypes::visitor_resources;
        $requestConfigSet['continous_ondemand'] = $continous_ondemand;
        $requestConfigSet['pfm'] = true;

        H::dispatchInternalAsync('replicate_missed_data', $requestConfigSet, 2);
    }

    public static function getOutdatementMinutes(): int
    {
        return (! H::isDevNode()) ? 60 : 10;
    }
}
