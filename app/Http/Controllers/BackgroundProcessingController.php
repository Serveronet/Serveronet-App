<?php

namespace App\Http\Controllers;

use App\Dicts\ActionTypes;
use App\Dicts\RecordStates;
use App\Dicts\SchedulesTag;
use App\Dicts\SettingIds;
use App\Http\Consts;
use App\Http\H;
use App\Models\BackgroundSchedule;
use App\Models\BackgroundScheduleExecution;
use App\Models\CachedDomain;
use App\Models\CachedResource;
use App\Models\ContentRetrieval;
use App\Models\InternalRequest;
use App\Models\P2pMessage;
use App\Models\Peer;
use App\Models\PendingAction;
use App\Models\Site;
use App\Models\SiteDefinition;
use App\Models\SitePeer;
use App\Models\VisitorRecord;
use App\Models\VisitorResource;
use App\Services\ChunkService;
use App\Services\IPFSService;
use App\Services\ListProviderService;
use App\Services\PeerMixService;
use Exception;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class BackgroundProcessingController extends Controller
{
    /*
    * https://laravel.com/docs/13.x/scheduling#schedule-frequency-options
    */

    public static $schedulesTable = [

        ['tag' => SchedulesTag::Send_Pending_Messages, 'method_name' => 'everyMinute', 'ensure_daily_execution' => false, 'is_one_time' => false, 'log_execution' => false],
        ['tag' => SchedulesTag::Update_External_Port, 'method_name' => 'everyTenMinutes', 'ensure_daily_execution' => false, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Update_External_Ip, 'method_name' => 'everyFiveMinutes', 'ensure_daily_execution' => false, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Check_Peers_Connectivity, 'method_name' => 'everyTenMinutes', 'ensure_daily_execution' => false, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Tracker_Announcing_Hosting, 'method_name' => 'everyTenMinutes', 'ensure_daily_execution' => false, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Publish_Self_As_Site_Peer, 'method_name' => 'hourlyAt', 'ensure_daily_execution' => false, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Process_Content_Retrievals, 'method_name' => 'everyFiveMinutes', 'ensure_daily_execution' => false, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Delete_Site_Definitions_When_Newer_Present, 'method_name' => 'hourlyAt', 'ensure_daily_execution' => true, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Pull_Missing_Site_Definitions, 'method_name' => 'hourlyAt', 'ensure_daily_execution' => true, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Schedule_Pending_Downloads, 'method_name' => 'hourlyAt', 'ensure_daily_execution' => true, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Process_Pending_Downloads, 'method_name' => 'everyTenMinutes', 'ensure_daily_execution' => true, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Process_Pending_Actions, 'method_name' => 'everyTenMinutes', 'ensure_daily_execution' => true, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Review_Overdue_Pending_Actions, 'method_name' => 'hourlyAt', 'ensure_daily_execution' => true, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Replicate_Missed_Site_Definitions, 'method_name' => 'hourlyAt', 'ensure_daily_execution' => false, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Replicate_Missed_Site_Peers, 'method_name' => 'hourlyAt', 'ensure_daily_execution' => false, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Replicate_Missed_Visitor_Records, 'method_name' => 'everyFourMinutes', 'ensure_daily_execution' => false, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Replicate_Missed_Visitor_Resources, 'method_name' => 'everyFiveMinutes', 'ensure_daily_execution' => false, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Verify_Site_Peers, 'method_name' => 'hourlyAt', 'ensure_daily_execution' => false, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Purge_P2p_Message_Body, 'method_name' => 'dailyAt', 'ensure_daily_execution' => true, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Delete_Expired_Retrievals, 'method_name' => 'everyFourMinutes', 'ensure_daily_execution' => false, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Clear_Expired_Domains_From_Cache, 'method_name' => 'hourlyAt', 'ensure_daily_execution' => false, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Retry_Failed_Originated_Messages, 'method_name' => 'hourlyAt', 'ensure_daily_execution' => false, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Delete_Expired_Cached_Resources, 'method_name' => 'dailyAt', 'ensure_daily_execution' => true, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Delete_Expired_Visitor_Items, 'method_name' => 'dailyAt', 'ensure_daily_execution' => true, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Check_Hosted_Promotable_Site_Definitions, 'method_name' => 'hourlyAt', 'ensure_daily_execution' => true, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Refresh_Lists_Cache, 'method_name' => 'everyTenMinutes', 'ensure_daily_execution' => true, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Process_Site_Definitions_Pending_Distribution, 'method_name' => 'everyFourMinutes', 'ensure_daily_execution' => true, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Sqlite_Database_Backup, 'method_name' => 'hourlyAt', 'ensure_daily_execution' => true, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Test_Tor_Access, 'method_name' => 'everyTenMinutes', 'ensure_daily_execution' => false, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Test_Ipfs, 'method_name' => 'everyTenMinutes', 'ensure_daily_execution' => false, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Schuffle_Peer_Id, 'method_name' => 'dailyAt', 'ensure_daily_execution' => true, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Purge_Excessive_Records, 'method_name' => 'dailyAt', 'ensure_daily_execution' => true, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Update_Sites_Hosted_State, 'method_name' => 'hourlyAt', 'ensure_daily_execution' => true, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Update_Statistics, 'method_name' => 'everyTenMinutes', 'ensure_daily_execution' => true, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Sites_Maintenance, 'method_name' => 'hourlyAt', 'ensure_daily_execution' => true, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Join_Chunks_Or_Require, 'method_name' => 'everyTenMinutes', 'ensure_daily_execution' => true, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Reset_Non_Hosted_Sites_Database, 'method_name' => 'dailyAt', 'ensure_daily_execution' => true, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Establish_Passive_Sessions, 'method_name' => 'everyFiveMinutes', 'ensure_daily_execution' => false, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Report_Peers_Connectivity, 'method_name' => 'everyFiveMinutes', 'ensure_daily_execution' => false, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Check_Client_Update_Available, 'method_name' => 'dailyAt', 'ensure_daily_execution' => true, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Get_New_Trackers_From_NewTrackon, 'method_name' => 'dailyAt', 'ensure_daily_execution' => true, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Update_Client, 'method_name' => 'dailyAt', 'ensure_daily_execution' => true, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Get_Newer_Site_Definitions_For_Non_Hosted, 'method_name' => 'dailyAt', 'ensure_daily_execution' => true, 'is_one_time' => false, 'log_execution' => true],
        ['tag' => SchedulesTag::Ensure_Daily_Actions_Executed, 'method_name' => 'hourlyAt', 'ensure_daily_execution' => false, 'is_one_time' => false, 'log_execution' => false],

    ];

    public static function getBackgroundSchedulesConfig(): Collection
    {
        $dbSchedules = BackgroundSchedule::get();
        $dbPeriodicSchedules = $dbSchedules->where('is_one_time', false);
        $schedulesTable = collect(BackgroundProcessingController::$schedulesTable);

        foreach (BackgroundProcessingController::$schedulesTable as $key => $scheduleEntry) {
            $tag = $scheduleEntry['tag'];
            $dbPeriodicSchedule = $dbPeriodicSchedules->where('tag', $tag)->first();
            if ($dbPeriodicSchedule) {
                $scheduleDefinition = $schedulesTable->where('tag', $tag)->first();
                $dbPeriodicSchedule->method_name = $scheduleDefinition['method_name'];
                $dbPeriodicSchedule->ensure_daily_execution = $scheduleDefinition['ensure_daily_execution'];
            } else {
                /* Upgrade required */
                UpdaterController::upgradeSchedules();
            }
        }

        return $dbSchedules;
    }

    public static function schedulePendingDownloads($site_id = null, $pfm = false)
    {
        info('schedulePendingDownloads '.$site_id);

        $queryBuilder = DB::table('site_definitions')
            ->leftJoin('sites', 'site_definitions.site_id', '=', 'sites.site_id')
            ->where('is_to_be_hosted', true)
            ->whereNotIn('download_state', [RecordStates::download_scheduled, RecordStates::download_downloaded]);

        if ($site_id) {
            $queryBuilder->where('site_definitions.site_id', $site_id);
        }

        $siteDefinitionsPendingDownloadAction = $queryBuilder->take(5)->get();

        foreach ($siteDefinitionsPendingDownloadAction as $sd) {

            $siteDefinition = SiteDefinition::where([
                ['site_id', $sd->site_id],
                ['entity_created', $sd->entity_created],
            ])->first();

            $site_resources = json_decode($siteDefinition->file_listing_json);
            H::pfm($site_resources, pfm: $pfm);

            $resIsMissing = false;

            foreach ($site_resources as $site_resource) {

                $siteResourcePresent = CachedResource::whereSha256($site_resource->sha256)->first();

                if ($siteResourcePresent) {
                    H::pfm('Res is present', pfm: $pfm);
                } else {
                    $resIsMissing = true;
                    H::pfm('Res is needed ', pfm: $pfm);

                    $chunks = json_decode($site_resource->chunks_json);

                    $allChunksAreAvailble = true;

                    foreach ($chunks as $key => $chunk) {
                        $resourceChunkPresent = CachedResource::where512($chunk->sha256)->first();

                        if (! $resourceChunkPresent) {
                            $allChunksAreAvailble = false;

                            $pendingAction = PendingAction::where([
                                ['action_type', ActionTypes::retrieve_resource],
                                ['sha256', $site_resource->sha256],
                                ['site_id', $siteDefinition->site_id],
                            ])->first();
                            if (! $pendingAction) {
                                $pendingAction = new PendingAction;
                                $pendingAction->action_type = ActionTypes::retrieve_resource;
                                $pendingAction->sha256 = $site_resource->sha256;
                                $pendingAction->mime_type = $site_resource->mime_type;
                                $pendingAction->site_id = $siteDefinition->site_id;
                                $pendingAction->ipfs_hash = $site_resource->ipfs_hash;
                                $pendingAction->file_size = $site_resource->file_size;
                                $pendingAction->save();
                            }

                            H::pfm('Download Resource action created: '.$site_resource->sha256, pfm: $pfm);
                        }

                    }
                    if ($allChunksAreAvailble) {
                        $resIsMissing = false;
                        (new ChunkService)->joinChunks($chunks, $site_resource->sha256, $site_resource->file_size);
                    }
                }
            }

            if ($resIsMissing) {
                $siteDefinition->download_state = RecordStates::download_scheduled;
                $siteDefinition->download_scheduled_at = now();
            } else {
                $siteDefinition->download_state = RecordStates::download_downloaded;
                $siteDefinition->download_scheduled_at = null;
            }

            $siteDefinition->save();
        }

        H::pfm('siteDefinitionsPendingDownloadAction count: '.count($siteDefinitionsPendingDownloadAction), pfm: $pfm);
    }

    public static function sendPendingMessages($site_id = null, $limit = 200, $pfm = false)
    {
        info('sendPendingMessages start');

        $queryBuilderCount = P2pMessage::whereNull('sent_at')
            ->whereNull('sending_started_at')->orderBy('priority');

        if ($site_id) {
            $queryBuilderCount = $queryBuilderCount->where('site_id', $site_id);
        }

        $pendingP2pMessagesCount = $queryBuilderCount->count();

        if ($pendingP2pMessagesCount == 0 || $limit < 1) {
            info('No more pending or limit '.$limit);
            H::pfm('No more pending or limit hit: '.$limit.' '.__FUNCTION__, pfm: $pfm);

            return;
        }

        /* Throttling */
        $from = now()->subMinutes(1)->toDateTimeString();
        $to = now()->toDateTimeString();

        $recentlySentP2pMessagesCount = P2pMessage::whereNotNull('sent_at')
            ->whereBetween('sent_at', [$from, $to])->count();

        info('recentlySentP2pMessagesCount '.$recentlySentP2pMessagesCount);

        if ($recentlySentP2pMessagesCount > $limit) {
            info('Too many pending messages already '.$limit);
            H::pfm('No more pending or limit hit: '.$limit.' '.__FUNCTION__, pfm: $pfm);

            return;
        }
        /* Throttling End */

        $queryBuilder = P2pMessage::whereNull('sent_at')
            ->whereNull('sending_started_at')->orderBy('priority');

        if ($site_id) {
            $queryBuilder->where('site_id', $site_id);
        }

        $pendingP2pMessage = $queryBuilder->first();

        if (! $pendingP2pMessage) {
            return;
        }

        info('Sending PendingP2pMessage: '.$pendingP2pMessage?->message_id.' Limit: '.$limit);
        $pendingP2pMessage->sending_started_at = now();
        $pendingP2pMessage->save();

        $peers = collect();

        $message = $pendingP2pMessage;
        $peers = (new PeerMixService)->getPeerMix(site_id: $message->site_id, excludedPeers: [$message->sender_address]);

        foreach ($peers as $peer) {
            self::sendMessageToPeer($peer, $message, retries_count: 0, pfm: $pfm);
        }

        $pendingP2pMessage->sent_at = now();
        $pendingP2pMessage->save();

        usleep(10_000);

        $limit--;

        self::sendPendingMessages($site_id, $limit, pfm: $pfm);
    }

    public function reviewOverduePendingActions($pfm = false)
    {
        $overduePendingActions = PendingAction::whereIn('state', [
            RecordStates::action_processing,
        ])->where('processing_started_at', '<', now()->subHours(1)->toDateTimeString())
            ->get();

        H::pfm('overduePendingActions: '.count($overduePendingActions).' '.__FUNCTION__, pfm: $pfm);

        foreach ($overduePendingActions as $key => $pendingAction) {
            if ($pendingAction->retries_count < 3) {
                $pendingAction->retries_count++;
                $pendingAction->state = RecordStates::action_pending;
                $pendingAction->processing_started_at = null;
                $pendingAction->save();
            } else {
                $pendingAction->state = RecordStates::action_failed;
                $pendingAction->save();
            }
        }
    }

    public static function processPendingDownloads($site_id = null, $limit = 50, $pfm = false)
    {
        info('processPendingDownloads '.$site_id);
        $queryBuilder = PendingAction::whereIn('state', [
            RecordStates::action_pending,
        ])->whereIn('action_type', [ActionTypes::retrieve_resource]);

        if ($site_id) {
            $queryBuilder->where('site_id', $site_id);
        }

        $pending_action = $queryBuilder->first();

        if (! $pending_action || $limit < 1) {
            H::pfm('No more pending or limit hit: '.$limit.' '.__FUNCTION__, pfm: $pfm);

            return;
        }
        H::pfm('Processing pending action downloads: '.$pending_action->action_type.' '.$pending_action->sha256, pfm: $pfm);
        $pending_action->state = RecordStates::action_processing;
        $pending_action->processing_started_at = now();
        $pending_action->retries_count++;
        $pending_action->save();

        switch ($pending_action->action_type) {
            case ActionTypes::retrieve_resource:
                BackgroundProcessingController::downloadResource($pending_action, pfm: $pfm);
                break;
        }

        $limit--;
        self::processPendingDownloads($site_id, $limit, pfm: $pfm);
    }

    public static function processPendingActions($site_id = null, $limit = 100, $pfm = false)
    {
        $queryBuilder = PendingAction::whereIn('state', [
            RecordStates::action_pending,
        ])->whereIn('action_type', [
            ActionTypes::upload_site_resource,
            ActionTypes::upload_visitor_resource,
            ActionTypes::send_targeted_p2p_message,
        ]);

        if ($site_id) {
            $queryBuilder->where('site_id', $site_id);
        }

        $pendingAction = $queryBuilder->first();
        if (! $pendingAction || $limit < 1) {
            H::pfm('No more pending or limit hit: '.$limit.' '.__FUNCTION__, pfm: $pfm);

            return;
        }

        H::pfm('Processing pending action upload: '.$pendingAction->action_type, pfm: $pfm);
        $pendingAction->state = RecordStates::action_processing;
        $pendingAction->processing_started_at = now();
        $pendingAction->retries_count++;
        $pendingAction->save();

        switch ($pendingAction->action_type) {
            case ActionTypes::upload_site_resource:
                BackgroundProcessingController::uploadResource($pendingAction, pfm: $pfm);
                break;

            case ActionTypes::upload_visitor_resource:
                BackgroundProcessingController::uploadResource($pendingAction, pfm: $pfm);
                break;

            case ActionTypes::send_targeted_p2p_message:
                $pendingAction->state = RecordStates::action_completed;
                $pendingAction->save();    
                $client_address = H::a($pendingAction->client_address);
                if (! in_array($client_address, H::getSelfAddresses())) {
                    $peer = new Peer(['client_address' => $pendingAction->client_address]);
                    $message = P2pMessage::where('message_id', $pendingAction->entity_id)->first();
                    self::sendMessageToPeer($peer, $message, $pendingAction->retries_count, pfm: $pfm);
                }
                break;
        }

        $limit--;
        self::processPendingActions($site_id, $limit, pfm: $pfm);
    }

    protected static function downloadResource(PendingAction $pendingAction, $pfm = false)
    {
        $sha256 = $pendingAction->sha256;
        $site_id = $pendingAction->site_id;

        $cr = CachedResource::where('sha256', $sha256)->first();
        if ($cr) {
            $pendingAction->state = RecordStates::action_completed;
            $pendingAction->save();

            return;
        }

        $retrieved = IPFSService::retrieveFromIpfs($pendingAction->file_size, $pendingAction->ipfs_hash, $pendingAction->sha256);
        if ($retrieved) {
            $pendingAction->state = RecordStates::action_completed;
            $pendingAction->save();

            return;
        }

        $peers = (new PeerMixService)->getPeerMix(site_id: $pendingAction->site_id);

        $file_downloaded = false;
        $max_execution_time = H::getMaxExecutionTime();
        foreach ($peers as $peer) {
            H::pfm('Begin downloading file '.$peer->client_address.' '.$site_id.' '.$sha256, pfm: $pfm);

            try {
                $url = H::a($peer->client_address).'p2p_api/v1/request_data_endpoint';
                $client = H::setupClient(H::isTorAddress($peer->client_address));
                $options = [
                    'timeout' => min($max_execution_time, H::timeoutAdjust(H::isTorAddress($peer->client_address), 60)),
                    'form_params' => [
                        'sha256' => $sha256,
                        'action_type' => $pendingAction->action_type,
                    ],
                    'prepare_ip' => true,
                ];
                H::prepareOptions($options, $url);

                $response = $client->post($url, $options);
                $body = (string) $response->getBody();
                H::pfm($body, pfm: $pfm);
                H::pfm('Downloading file '.$peer->client_address.' '.$site_id.' '.$sha256.' Result: '.
                $response->getStatusCode(), pfm: $pfm);

                $result_message = json_decode($body);
                if ($result_message->success == true) {

                    /* Additional check if downloaded in meantime */
                    $cr = CachedResource::where('sha256', $sha256)->first();

                    if (! $cr) {
                        $file_name = Str::random(40);
                        Storage::disk('cached_resources')->put(
                            $file_name,
                            base64_decode($result_message->data->payload)
                        );

                        $calculatedSha256 = hash('sha256', Storage::disk('cached_resources')->get($file_name));
                        if ($calculatedSha256 == $sha256) {
                            $file_downloaded = true;
                            $cr = new CachedResource;
                            $cr->file_name = $file_name;
                            $cr->ipfs_hash = $result_message->data->ipfs_hash;
                            $cr->sha256 = $sha256;
                            $cr->file_size = Storage::disk('cached_resources')->size($file_name);
                            $cr->save();
                        } else {
                            Storage::disk('cached_resources')->delete($file_name);
                        }

                    } else {
                        $file_downloaded = true;
                    }
                    H::pfm('is file_downloaded: '.$file_downloaded, pfm: $pfm);
                }
            } catch (Exception $e) {
                info('downloadResource '.$e->getMessage());
                H::pfm($e->getMessage(), pfm: $pfm);
            }

            if ($file_downloaded) {
                $pendingAction->state = RecordStates::action_completed;
                $pendingAction->save();
                break;
            }
        }
    }

    protected static function checkPeersConnectivity($limit = 100, $pfm = false)
    {
        $frequencyMinutes = App::isProduction() ? 60 : 1;

        $peerNotChecked = Peer::query()->withoutSelf()
            ->where('last_connection_check_at', '<', now()->subMinutes($frequencyMinutes))
            ->orWhereNull('last_connection_check_at')
            ->orderBy('last_connection_check_at')
            ->first();

        if (! $peerNotChecked || $limit < 1) {
            H::pfm('No more pending limit hit: '.$limit, pfm: $pfm);

            return;
        }
        H::pfm('checkPeersConnectivity '.$peerNotChecked->client_address, pfm: $pfm);

        $peer = $peerNotChecked;

        (new ClientController)->checkPeerConnectivity(peer: $peer,
            do_inbound_connectivity_check: true);

        $limit--;
        self::checkPeersConnectivity($limit);
    }

    protected static function uploadResource(PendingAction $pendingAction, $pfm = false)
    {
        $sha256 = $pendingAction->sha256;
        $taget_client_address = $pendingAction->client_address;
        $res = CachedResource::whereSha256($sha256)->first();
        info('uploading to: '.$taget_client_address.' CR file name '.$res->file_name);
        $fileResourceSize = Storage::disk('cached_resources')->size($res->file_name);

        if ($fileResourceSize > Consts::p2pUploadMaxSizeBytesServeronet) {
            $pendingAction->state = RecordStates::action_failed;
            $pendingAction->failed_message = 'File too large fo Serveronet';
            $pendingAction->save();

            return;
        }

        H::pfm('Uploading: '.$taget_client_address.' '.$sha256, pfm: $pfm);
        $peer = Peer::where('client_address', $taget_client_address)->first();
        if (! $peer && ! in_array(H::a($taget_client_address), H::getSelfAddresses())) {
            $peer = new Peer;
        }
        $peer->client_address = H::a($taget_client_address);

        H::pfm($peer, pfm: $pfm);

        try {
            $encoded_upload_body = base64_encode(Storage::disk('cached_resources')->get($res->file_name));

            switch ($pendingAction->action_type) {

                case ActionTypes::upload_site_resource:

                    $siteDefinition = SiteDefinition::where([
                        ['site_id', $pendingAction->site_id],
                    ])->orderByDesc('created_at')->first();
                    $justification_record_json = $siteDefinition->record_json;
                    $justification_signature = $siteDefinition->signature;
                    $justification_signer_verification_key_base64 = $siteDefinition->signer_verification_key_base64;

                    break;

                case ActionTypes::upload_visitor_resource:

                    $visitorResource = VisitorResource::where([
                        ['entity_id', $pendingAction->entity_id],
                        ['site_id', $pendingAction->site_id],
                    ])->first();
                    $justification_record_json = $visitorResource->record_json;
                    $justification_signature = $visitorResource->signature;
                    $justification_signer_verification_key_base64 = $visitorResource->signer_verification_key_base64;

                    break;
            }
            $url = H::a($peer->client_address).'p2p_api/v1/resource_upload_endpoint';
            $client = H::setupClient(H::isTorAddress($peer->client_address));
            $options = [
                'timeout' => H::timeoutAdjust(H::isTorAddress($peer->client_address), 1800),
                'form_params' => [
                    'encoded_upload_body' => $encoded_upload_body,
                    'justification_record_json' => $justification_record_json,
                    'justification_signature' => $justification_signature,
                    'justification_signer_verification_key_base64' => $justification_signer_verification_key_base64,
                    'action_type' => $pendingAction->action_type,
                ],
                'prepare_ip' => true,
            ];
            H::prepareOptions($options, $url);
            $response = $client->post($url, $options);

            $body = (string) $response->getBody();
            H::pfm($body, pfm: $pfm);
            $decodedBody = json_decode($body);
            $success = $decodedBody->success;

            if ($response->getStatusCode() == 200 && $success) {
                $pendingAction->state = RecordStates::action_completed;
                $pendingAction->completed_at = now();
            } else {
                $pendingAction->failed_message = $decodedBody->message ?? '';
                $pendingAction->retries_count++;
            }
            $pendingAction->save();

        } catch (Throwable $th) {
            H::pfm($th->getLine().' '.$th->getFile().' '.$th->getMessage(), pfm: $pfm);
            info($th->getLine().' '.$th->getFile().' '.$th->getMessage());

            $pendingAction->state = RecordStates::action_failed;
            $pendingAction->failed_message = Str::limit($th->getMessage(), 400);
            $pendingAction->save();
        }
    }

    protected static function getNewerSiteDefinitionsForNonHosted($pfm = false)
    {
        info('getNewerSiteDefinitionsForNonHosted');

        $notToBeHostedSites = Site::where('is_to_be_hosted', false)->publishedOnly()
            ->with('site_definitions')->get();

        foreach ($notToBeHostedSites as $key => $site) {
            H::pfm('getNewerSiteDefinitionsForNonHosted:. '.$site->site_id, pfm: $pfm);
            $rcResult = (new SiteServerController)->handleMissingState(
                request: request(),
                site_id: $site->site_id,
                action_type: ActionTypes::retrieve_site_definition,
                sha256: null,
                mime_type: null,
                ipfs_hash: null,
                entity_id: null
            );
            H::pfm('getNewerSiteDefinitionsForNonHosted:. operation_successful: '.$rcResult->operation_successful
            .' Error: '.$rcResult->error_message, pfm: $pfm);
        }
    }

    public static function prepareMissingSiteDefinitionsRetrievals($site_id = null, $pfm = false)
    {
        info('prepareMissingSiteDefinitionsRetrievals '.$site_id);

        $sitesWithoutSiteDefinition = Site::doesntHave('site_definitions')
            ->where('is_published', true);
        if ($site_id) {
            $sitesWithoutSiteDefinition = $sitesWithoutSiteDefinition->whereSiteId($site_id);
        } else {
            $sitesWithoutSiteDefinition = $sitesWithoutSiteDefinition->where('site_definition_retrieval_count', '<', 3)
                ->orderBy('site_definition_retrieval_count');
        }
        $sitesWithoutSiteDefinition = $sitesWithoutSiteDefinition->get();

        if (count($sitesWithoutSiteDefinition) == 0) {
            H::pfm('No more pending '.__FUNCTION__, pfm: $pfm);

            return;
        }

        foreach ($sitesWithoutSiteDefinition as $key => $site) {
            $contentRetrieval = ContentRetrieval::where([
                ['site_id', $site->site_id],
                ['action_type', ActionTypes::retrieve_site_definition],
            ])
                ->whereIn('state', [RecordStates::retrieval_pending, RecordStates::retrieval_processing])
                ->first();

            if (! $contentRetrieval) {

                $site->site_definition_retrieval_count++;

                $contentRetrieval = new ContentRetrieval;
                $contentRetrieval->retrieval_id = Str::random(40);
                $contentRetrieval->action_type = ActionTypes::retrieve_site_definition;
                $contentRetrieval->site_id = $site->site_id;
                $contentRetrieval->save();

                H::savePassiveTriggersForSite($site_id);
            }
        }
    }

    /**
     * Site Definitions which are for hosted Sites and download of resources is scheduled.
     * When all resources are downloaded a Site Definition is marked as Downloaded and served.
     */
    public static function checkHostedPromotableSiteDefinitions($site_id = null, $pfm = false)
    {
        info('checkHostedPromotableSiteDefinitions '.$site_id);
        $downloadScheduledSiteDefinitionsBuilder = SiteDefinition::whereRelation('site', 'is_to_be_hosted', 1)
            ->where('download_state', RecordStates::download_scheduled)->orderBy('last_download_check_at')
            ->where(function (Builder $query) {
                $query->where('last_download_check_at', '<', now()->subMinutes(1))
                    ->orWhereNull('last_download_check_at');
            });

        if ($site_id) {
            $downloadScheduledSiteDefinitionsBuilder->whereSiteId($site_id);
        }

        $downloadScheduledSiteDefinitions = $downloadScheduledSiteDefinitionsBuilder->get();

        if (count($downloadScheduledSiteDefinitions) == 0) {
            H::pfm('No more pending '.__FUNCTION__, pfm: $pfm);

            return;
        }

        foreach ($downloadScheduledSiteDefinitions as $key => $downloadScheduledSiteDefinition) {

            $files = json_decode($downloadScheduledSiteDefinition->file_listing_json);
            $hashes = Arr::pluck($files, ['sha256']);
            $hashesCount = count($hashes);
            $hostedResouces = CachedResource::whereIn('sha256', $hashes)->get();
            $hostedResouces = $hostedResouces->pluck('sha256')->unique()->toArray();
            $crCount = count($hostedResouces);
            H::pfm($crCount.' '.$hashesCount, pfm: $pfm);
            if ($crCount == $hashesCount) {
                $downloadScheduledSiteDefinition->download_state = RecordStates::download_downloaded;
            } else {
                $downloadScheduledSiteDefinition->last_download_check_at = now();
            }
            $downloadScheduledSiteDefinition->save();
        }
    }

    public function deleteSiteDefinitionsWhenNewerPresent($site_id = null, $pfm = false)
    {
        info('deleteSiteDefinitionsWhenNewerPresent '.$site_id);
        $sitesWithMultipleProdSiteDefinitionsBuilder = Site::with('site_definitions')
            ->withCount('site_definitions');

        if ($site_id) {
            $sitesWithMultipleProdSiteDefinitionsBuilder->whereSiteId($site_id);
        }

        $sitesWithMultipleProdSiteDefinitions = $sitesWithMultipleProdSiteDefinitionsBuilder->get();

        $sitesWithMultipleProdSiteDefinitions = $sitesWithMultipleProdSiteDefinitions->where(
            'site_definitions_count', '>', 1
        );

        foreach ($sitesWithMultipleProdSiteDefinitions as $key => $sitesWithMultipleProdSiteDefinition) {
            $site_id = $sitesWithMultipleProdSiteDefinition->site_id;

            H::pfm($site_id, pfm: $pfm);
            H::pfm('Prods:', pfm: $pfm);

            $cleanUpCandidateProdSiteDefinitions = SiteDefinition::query()
                ->whereSiteId($site_id)
                ->select('entity_created', 'download_state', 'id')
                ->get();

            $isSiteWithMultipleProdSiteDefinitions = count($cleanUpCandidateProdSiteDefinitions) > 0;
            if ($isSiteWithMultipleProdSiteDefinitions) {
                $newestProdDownloaded = $cleanUpCandidateProdSiteDefinitions->where('download_state', RecordStates::download_downloaded)
                    ->sortByDesc('entity_created')->first();
                if ($newestProdDownloaded) {
                    $newestProd = $newestProdDownloaded;
                } else {
                    $newestProd = $cleanUpCandidateProdSiteDefinitions->sortByDesc('entity_created')->first();
                }

                $olderProdSDs = $cleanUpCandidateProdSiteDefinitions->where('entity_created', '<', $newestProd->entity_created)->all();

                foreach ($olderProdSDs as $key => $olderProdSD) {
                    $olderProdSD->delete();
                }
            }
        }
    }

    public function executeClientActionWrapper(Request $request)
    {
        info('executeClientActionWrapper');
        $requestConfigSet = H::unwrapRequestConfigSet();

        if (! H::isInternalCallCurrent($requestConfigSet)) {
            info('Outdated or spoofed internal request');

            return;
        }
        $internalRequestId = H::startInteralRequestReporting(__FUNCTION__);

        $tag = $requestConfigSet['tag'];

        $request->merge(['tag' => $tag]);
        $request->merge(['pfm' => false]);

        /* executeClientAction */
        $this->executeClientAction($request);

        H::endInteralRequestReporting($internalRequestId);
    }

    public function executeClientAction(Request $request)
    {
        info('executeClientAction start');

        $request->validate([
            'tag' => ['required', 'string', 'max:1024'],
            'pfm' => ['nullable', 'boolean'],
        ]);

        $tag = $request->tag;
        $pfm = $request->pfm ?? false;

        $schedulesTable = collect(BackgroundProcessingController::$schedulesTable);
        $scheduleDefinition = $schedulesTable->where('tag', $tag)->first();

        $log_execution = $scheduleDefinition['log_execution'] ?? null;

        H::pfm($tag.' | Log Execution: '.tfyn($log_execution), pfm: $pfm);
        $startedAt = now();

        $backgroundSchedule = BackgroundSchedule::where([
            ['tag', $tag],
            ['is_one_time', false],
        ])->first();

        if ($backgroundSchedule) {
            $backgroundSchedule->last_execution_at = now();
            $backgroundSchedule->save();
        }

        switch ($tag) {

            case SchedulesTag::Send_Pending_Messages:
                BackgroundProcessingController::sendPendingMessages(pfm: $pfm);
                break;

            case SchedulesTag::Update_External_Port:
                (new PortForwardController)->updateExternalPort(pfm: $pfm);
                break;

            case SchedulesTag::Update_External_Ip:
                H::updateExternalIp(pfm: $pfm);
                break;

            case SchedulesTag::Check_Peers_Connectivity:
                self::checkPeersConnectivity(pfm: $pfm);
                break;

            case SchedulesTag::Tracker_Announcing_Hosting:
                TorrentTrackersController::trackerAnnouncingHosting(pfm: $pfm);
                break;

            case SchedulesTag::Publish_Self_As_Site_Peer:
                H::publishSelfAsSitePeer(pfm: $pfm);
                break;

            case SchedulesTag::Process_Content_Retrievals:
                SitesVisitorController::processContentRetrievals(pfm: $pfm);
                break;

            case SchedulesTag::Delete_Site_Definitions_When_Newer_Present:
                (new BackgroundProcessingController)->deleteSiteDefinitionsWhenNewerPresent(pfm: $pfm);
                break;

            case SchedulesTag::Pull_Missing_Site_Definitions:
                self::prepareMissingSiteDefinitionsRetrievals(pfm: $pfm);
                break;

            case SchedulesTag::Schedule_Pending_Downloads:
                BackgroundProcessingController::schedulePendingDownloads(pfm: $pfm);
                break;

            case SchedulesTag::Process_Pending_Downloads:
                BackgroundProcessingController::processPendingDownloads(pfm: $pfm);
                break;

            case SchedulesTag::Process_Pending_Actions:
                BackgroundProcessingController::processPendingActions(pfm: $pfm);
                break;

            case SchedulesTag::Review_Overdue_Pending_Actions:
                BackgroundProcessingController::reviewOverduePendingActions(pfm: $pfm);
                break;

            case SchedulesTag::Replicate_Missed_Site_Definitions:
                (new ReplicationController)->replicateMissedSiteDefinitions(pfm: $pfm, continous_ondemand: true);
                break;

            case SchedulesTag::Replicate_Missed_Site_Peers:
                (new ReplicationController)->replicateMissedSitePeers(pfm: $pfm, continous_ondemand: true);
                break;

            case SchedulesTag::Replicate_Missed_Visitor_Records:
                (new ReplicationController)->replicateMissedSitesVisitorRecords(pfm: $pfm, continous_ondemand: true);
                break;

            case SchedulesTag::Replicate_Missed_Visitor_Resources:
                (new ReplicationController)->replicateMissedVisitorResources(pfm: $pfm, continous_ondemand: true);
                break;

            case SchedulesTag::Verify_Site_Peers:
                self::verifySitePeers(pfm: $pfm);
                break;

            case SchedulesTag::Purge_P2p_Message_Body:
                self::purgeP2pMessageBody(pfm: $pfm);
                break;

            case SchedulesTag::Delete_Expired_Retrievals:
                self::deleteCompletedAndFailedRetrievals(pfm: $pfm);
                break;

            case SchedulesTag::Clear_Expired_Domains_From_Cache:
                self::clearExpiredDomainsFromCache(pfm: $pfm);
                break;

            case SchedulesTag::Retry_Failed_Originated_Messages:
                self::retryFailedOriginatedMessages(pfm: $pfm);
                break;

            case SchedulesTag::Delete_Expired_Cached_Resources:
                self::deleteExpiredCachedResources(pfm: $pfm);
                break;

            case SchedulesTag::Delete_Expired_Visitor_Items:
                self::deleteExpiredVisitorItems(pfm: $pfm);
                break;

            case SchedulesTag::Sqlite_Database_Backup:
                ClientManagerController::sqliteDatabaseBackup(pfm: $pfm);
                break;

            case SchedulesTag::Report_Peers_Connectivity:
                if (H::isDevNode())
                (new DevPublicController)->reportPeersConnectivity(pfm: $pfm);
                break;

            case SchedulesTag::Check_Client_Update_Available:
                (new UpdaterController)->checkClientUpdateAvailableOrUpdate(andUpdate: false);
                break;

            case SchedulesTag::Get_New_Trackers_From_NewTrackon:
                if (! H::isDevNode())
                (new TorrentTrackersController)->getNewTrackersFromNewTrackon();
                break;

            case SchedulesTag::Update_Client:
                (new UpdaterController)->checkClientUpdateAvailableOrUpdate(andUpdate: true);
                break;

            case SchedulesTag::Check_Budle_Update_Available:
                (new UpdaterController)->checkBundleUpdateAvailable();
                break;

            case SchedulesTag::Check_Hosted_Promotable_Site_Definitions:
                BackgroundProcessingController::checkHostedPromotableSiteDefinitions(pfm: $pfm);
                break;

            case SchedulesTag::Refresh_Lists_Cache:
                (new ClientController)->refreshListsCache(pfm: $pfm);
                break;

            case SchedulesTag::Process_Site_Definitions_Pending_Distribution:
                PublishSitesController::processSiteDefinitionsPendingDistribution(pfm: $pfm);
                break;

            case SchedulesTag::Test_Tor_Access:
                (new AdminController)->testTorAccess(pfm: $pfm);
                break;

            case SchedulesTag::Test_Ipfs:
                (new AdminController)->testIpfs(pfm: $pfm);
                break;

            case SchedulesTag::Schuffle_Peer_Id:
                self::schufflePeerId(pfm: $pfm);
                break;

            case SchedulesTag::Purge_Excessive_Records:
                self::purgeExcessiveRecords(pfm: $pfm);
                break;

            case SchedulesTag::Update_Sites_Hosted_State:
                H::updateSitesHostedState(pfm: $pfm);
                break;

            case SchedulesTag::Update_Statistics:
                BackgroundProcessingController::UpdateStatistics(pfm: $pfm);
                break;

            case SchedulesTag::Sites_Maintenance:
                (new SiteManagerController)->onSiteMaintenance(pfm: $pfm);
                break;

            case SchedulesTag::Join_Chunks_Or_Require:
                (new ChunkService)->joinChunksOrRequire(pfm: $pfm);
                break;

            case SchedulesTag::Reset_Non_Hosted_Sites_Database:
                (new SiteManagerController)->resetNotToBeHostedSitesDatabase(pfm: $pfm);
                break;

            case SchedulesTag::Establish_Passive_Sessions:
                (new PassiveClientController)->establishPassiveSessions(pfm: $pfm);
                break;

            case SchedulesTag::Get_Newer_Site_Definitions_For_Non_Hosted:
                BackgroundProcessingController::getNewerSiteDefinitionsForNonHosted(pfm: $pfm);
                break;

            case SchedulesTag::Ensure_Daily_Actions_Executed:
                self::ensureDailyActionsExecuted(pfm: $pfm);
                break;

            default:
                H::pfm($tag, pfm: $pfm);
                break;
        }

        if ($log_execution) {
            $endedAt = now();
            $takenSeconds = $startedAt->diffInSeconds($endedAt);

            (new BackgroundScheduleExecution([
                'tag' => $tag,
                'started_at' => $startedAt,
                'ended_at' => $endedAt,
                'taken_seconds' => $takenSeconds,
            ]))->save();
        }
    }

    protected static function sendMessageToPeer($peer, $message, $retries_count, $pfm = false)
    {
        info('sendMessageToPeer');
        $message_json = json_encode($message);
        try {
            $url = H::a($peer->client_address).'p2p_api/v1/receive_message_endpoint_'.$message->type;
            $client = H::setupClient(H::isTorAddress($peer->client_address));
            $options = [
                'timeout' => H::timeoutAdjust(H::isTorAddress($peer->client_address), 10),
                'form_params' => ['message_json' => $message_json],
                'prepare_ip' => true,
            ];
            H::prepareOptions($options, $url);
            $response = $client->post($url, $options);
            
            $body = (string) $response->getBody();
            $decodedBody = json_decode($body);
            $success = $decodedBody->success;

            H::pfm('Message sent to '.$peer->client_address.' Result: '.$response->getStatusCode().' '.$message->message_id, pfm: $pfm);

            if ($response->getStatusCode() == 200 && $success) {
                $message->successful_deliveries_count++;
                $message->save();
            }

        } catch (ClientException $th) {
            info('$response->getStatusCode() '.$th->getResponse()->getStatusCode().' '.$retries_count);
            if ($th->getResponse()->getStatusCode() == 429 && $retries_count <= 5) {
                self::enqueueRetry($message, $peer->client_address);
                if (! in_array($peer->client_address, H::getSelfAddresses())) {
                    $peer = Peer::where('client_address', $peer->client_address)->first();
                    H::decreasePeerReputation($peer);
                }
            }
        } catch (Throwable $th) {
            info('sendMessageToPeer '.$th->getMessage().' '.$th->getFile().' '.$th->getLine().' '.get_class($th));
            ClientController::handlePeerNotConnectable($th, $peer->client_address);
        }
    }

    protected static function enqueueRetry($message, $client_address)
    {
        info('enqueueRetry');
        $pendingAction = PendingAction::where('entity_id', $message->message_id)->first();
        if ($pendingAction) {
            $pendingAction->retries_count++;
            $pendingAction->state = RecordStates::action_pending;

            if ($pendingAction->retries_count > 4) {
                $pendingAction->delete();

                return;
            }

        } else {
            $pendingAction = new PendingAction;
            $pendingAction->action_type = ActionTypes::send_targeted_p2p_message;
            $pendingAction->entity_id = $message->message_id;
            $pendingAction->client_address = $client_address;
        }
        $pendingAction->save();
    }

    protected static function schufflePeerId($pfm = false)
    {
        $newPeerId = Str::random(12);
        H::setSettingValue(SettingIds::peer_random_id, $newPeerId);
        H::pfm('$newPeerId: '.$newPeerId, pfm: $pfm);
    }

    protected function purgeExcessiveRecords($pfm = false)
    {
        info('purgeExcessiveRecords');

        BackgroundScheduleExecution::where([
            ['created_at', '<', now()->subHours(24)],
        ])->delete();

        InternalRequest::where([
            ['created_at', '<', now()->subHours(24)],
        ])->delete();

        // Maybe as well:
        // - peers
        // - site peers
        // - sites
    }

    /**
     * Add existing files and remove resources without files stored
     */
    public function resourcesMaintenance($pfm = false)
    {
        $pfm = request()->pfm ?? false;

        H::pfm('Deleting cached resources without file present', pfm: $pfm);
        $allResources = CachedResource::get();
        $deletedCrsCount = 0;
        foreach ($allResources as $key => $resource) {
            $crFileExists = Storage::disk('cached_resources')->exists($resource->file_name);
            if (! $crFileExists) {
                $resource->delete();
                $deletedCrsCount++;
            }
        }
        H::pfm('Resources without content file deleted: '.$deletedCrsCount, pfm: $pfm);

        H::pfm('Adding new resources based on found content file', pfm: $pfm);
        $allResources = CachedResource::get();
        $crFileNames = [];
        foreach ($allResources as $key => $resource) {
            array_push($crFileNames, $resource->file_name);
        }

        $disk = Storage::disk('cached_resources');
        $allFilesInCrDir = $disk->allFiles('');
        $filesToBeHashed = array_diff($allFilesInCrDir, $crFileNames);
        $addedCrsCount = 0;
        foreach ($filesToBeHashed as $key => $file_name) {
            $calculatedSha256 = hash_file('sha256', Storage::disk('cached_resources')->path($file_name));

            $alreadyPresentCrWithHashCount = CachedResource::whereSha256($calculatedSha256)->count();

            if ($alreadyPresentCrWithHashCount > 0) {
                Storage::disk('cached_resources')->delete($file_name);
            } else {
                $file_size = Storage::disk('cached_resources')->size($file_name);
                $cr = new CachedResource;
                $cr->file_name = $file_name;
                $cr->file_size = $file_size;
                $cr->sha256 = $calculatedSha256;
                $cr->save();
                $addedCrsCount++;
            }
        }
        H::pfm('New resources added based found on content file: '.$addedCrsCount, pfm: $pfm);

        H::pfm('Removing duplicates when no IPFS hash', pfm: $pfm);

        $uniqueResources = CachedResource::groupBy('sha256')->get();
        $deletedCrsCount = 0;

        foreach ($uniqueResources as $key => $uniqueResource) {
            $duplicatesCount = CachedResource::whereSha256($uniqueResource->sha256)->count();
            if ($duplicatesCount > 1) {
                $deleteCandidates = CachedResource::whereSha256($uniqueResource->sha256)->whereNot('id', $uniqueResource->id)
                    ->whereNull('ipfs_hash')->get();
                foreach ($deleteCandidates as $key => $deleteCandidate) {
                    $crFileExists = Storage::disk('cached_resources')->exists($deleteCandidate->file_name);
                    if (! $crFileExists) {
                        $deleteCandidate->delete();
                        $deletedCrsCount++;
                    }
                    $deleteCandidate->delete();
                }
            }
        }
        H::pfm('Removed duplicates when no IPFS hash: '.$deletedCrsCount, pfm: $pfm);
    }

    public static function ensureDailyActionsExecuted($pfm = false)
    {
        $schedulesDefinitions = BackgroundProcessingController::getBackgroundSchedulesConfig();
        $schedulesDefinitions = $schedulesDefinitions
            ->filter(function ($schedulesDefinition) {
                return
                $schedulesDefinition->last_execution_at < now()->subHours(24)
                ||
                $schedulesDefinition->last_execution_at == null;
            });
        $missedSchedule = $schedulesDefinitions->where('ensure_daily_execution', true)->first();

        if ($missedSchedule) {
            $request = new Request;
            $request->merge(['tag' => $missedSchedule->tag, 'pfm' => $pfm]);
            (new BackgroundProcessingController)->executeClientActionWrapper($request);
        } else {
            H::pfm('No missed schedules', pfm: $pfm);
        }
    }

    protected static function purgeP2pMessageBody($pfm = false)
    {
        H::pfm('Purge p2p message body', pfm: $pfm);
        $readyToBeDeltedP2pMessages = P2pMessage::whereNotNull('sent_at')->whereNull('purged_at')
            ->take(1000)->get();
        H::pfm('Ready to be purged: '.count($readyToBeDeltedP2pMessages), pfm: $pfm);

        foreach ($readyToBeDeltedP2pMessages as $key => $p2pMessage) {
            $p2pMessage->json_payload = null;
            $p2pMessage->purged_at = now();
            $p2pMessage->save();
        }
    }

    protected static function deleteCompletedAndFailedRetrievals($pfm = false)
    {
        H::pfm('Delete completed and failed retrievals older then 1 minute', pfm: $pfm);
        $outdatedContentRetrievals = ContentRetrieval::where('created_at', '<', now()->subMinute())
            ->whereIn('state', [RecordStates::retrieval_completed, RecordStates::retrieval_failed])
            ->take(1000)->get();

        H::pfm('Pending Retrievals ready to be deleted: '.count($outdatedContentRetrievals), pfm: $pfm);
        foreach ($outdatedContentRetrievals as $key => $outdatedContentRetrievals) {
            $outdatedContentRetrievals->delete();
        }
    }

    protected static function clearExpiredDomainsFromCache($pfm = false)
    {
        H::pfm('clearExpiredDomainsFromCache', pfm: $pfm);
        CachedDomain::where([
            ['updated_at', '<', now()->subHours(24)],
            ['is_persistent', false],
        ])->delete();
    }

    protected static function retryFailedOriginatedMessages($pfm = false)
    {
        H::pfm('retryFailedOriginatedMessages', pfm: $pfm);
        $failedOriginatedMessages = P2pMessage::where([
            ['self_origin', true],
            ['successful_deliveries_count', 0],
        ])
            ->where('created_at', '>', now()->subDays(1)->toDateTimeString())
            ->get();

        foreach ($failedOriginatedMessages as $key => $message) {
            $message->sent_at = null;
            $message->save();
        }
    }

    public static function deleteExpiredCachedResources($pfm = false)
    {
        $requiredHashes = [];

        $authorityBannedSiteIDs = (new ListProviderService)->getBannedSiteIds();

        $toBeHostedSites = Site::where('is_to_be_hosted', true)
            ->with('most_recent_site_definition')
            ->whereNotIn('site_id', $authorityBannedSiteIDs)->get();

        $frequentlyRequestedResources = CachedResource::where('last_request_at', '>', now()->subdays(2))->get();

        $frequentlyRequestedHashes = [];
        foreach ($frequentlyRequestedResources as $key => $res) {
            array_push($frequentlyRequestedHashes, $res->sha256);
        }

        $siteDefinitionRequiredHashes = [];

        foreach ($toBeHostedSites as $key => $site) {
            $fileListingsFiles = [];

            if (! $site->most_recent_site_definition) {
                continue;
            }

            $currentlyServedSDfiles = json_decode($site->most_recent_site_definition->file_listing_json ?? '[]');

            $fileListingsFiles = array_merge($fileListingsFiles, $currentlyServedSDfiles);

            $siteNewerSiteDefinitions = SiteDefinition::whereSiteId($site->site_id)
                ->where('entity_created', '>', $site->most_recent_site_definition->entity_created)->get();

            foreach ($siteNewerSiteDefinitions as $key => $siteDefinition) {
                $newerSDfiles = json_decode($siteDefinition->file_listing_json);
                $fileListingsFiles = array_merge($fileListingsFiles, $newerSDfiles);
            }

            foreach ($fileListingsFiles as $key => $fileListingsFile) {
                $siteDefinitionRequiredHashes[] = $fileListingsFile->sha256;
                $chunks = json_decode($fileListingsFile->chunks_json);
                foreach ($chunks as $key => $chunk) {
                    $sha256 = $chunk->sha256;
                    $siteDefinitionRequiredHashes[] = $sha256;
                }
            }
        }
        $siteDefinitionRequiredHashes = array_unique($siteDefinitionRequiredHashes);
 
        $visitorResourceRequiredHashes = [];
        $requiredVisitorResources = VisitorResource::whereIn('site_id', $toBeHostedSites->pluck('site_id'))->get();

        foreach ($requiredVisitorResources as $key => $requiredVisitorResource) {
            $visitorResourceRequiredHashes[] = $requiredVisitorResource->sha256;
            $chunks = json_decode($requiredVisitorResource->chunks_json);
            foreach ($chunks as $key => $chunk) {
                $sha256 = $chunk->sha256;
                $visitorResourceRequiredHashes[] = $sha256;
            }
        }

        H::pfm('$frequentlyRequestedHashes count: '.count($frequentlyRequestedHashes), pfm: $pfm);
        H::pfm('$siteDefinitionRequiredHashes count: '.count($siteDefinitionRequiredHashes), pfm: $pfm);
        H::pfm('$visitorResourceRequiredHashes count: '.count($visitorResourceRequiredHashes), pfm: $pfm);

        $requiredHashes = array_unique(array_merge($frequentlyRequestedHashes, $siteDefinitionRequiredHashes, $visitorResourceRequiredHashes));

        H::pfm('$requiredHashes count: '.count($requiredHashes), pfm: $pfm);

        $possibleToDeletedResources = CachedResource::whereNotIn('sha256', $requiredHashes)->get();

        $possibleToDeletedHashes = $possibleToDeletedResources->pluck('sha256');

        H::pfm('Possible to delete: '.count($possibleToDeletedHashes), pfm: $pfm);

        foreach ($possibleToDeletedHashes as $key => $hash) {
            H::pfm('possibleToDeletedHash: '.$hash, pfm: $pfm);
            $res = CachedResource::where('sha256', $hash)->first();
            if ($res) {
                if ($res->ipfs_hash) {
                    IPFSService::unpinFromIpfs($res->ipfs_hash);
                }

                $r = Storage::disk('cached_resources')->delete($res->file_name);
                $res->delete();
                H::pfm($r, pfm: $pfm);
            }
        }
    }

    /**
     * VisitorRecords of non hosted sites - after 2 days since created_at
     * VisitorResources of non hosted sites - after 2 days since created_at
     * */
    protected static function deleteExpiredVisitorItems($pfm = false)
    {
        H::pfm('deleteExpiredVisitorItems start', pfm: $pfm);

        $notToBeHostedSiteIds = Site::where('is_to_be_hosted', false)->pluck('site_id');

        if ($notToBeHostedSiteIds->isEmpty()) {
            H::pfm('No non-hosted sites to clean up.', pfm: $pfm);

            return;
        }

        /* Delete expired VisitorRecords for non-hosted sites */
        $deletedVisitorRecordsCount = VisitorRecord::whereIn('site_id', $notToBeHostedSiteIds)
            ->where('created_at', '<', now()->subDays(2))
            ->forceDelete();
        H::pfm('Deleted '.$deletedVisitorRecordsCount.' expired visitor records.', pfm: $pfm);

        /* Delete expired VisitorResources for non-hosted sites */
        $deletedVisitorResourcesCount = VisitorResource::whereIn('site_id', $notToBeHostedSiteIds)
            ->where('created_at', '<', now()->subDays(2))
            ->forceDelete();
        H::pfm('Deleted '.$deletedVisitorResourcesCount.' expired visitor resources.', pfm: $pfm);

        H::pfm('deleteExpiredVisitorItems end', pfm: $pfm);
    }

    protected static function verifySitePeers($limit = 100, $pfm = false)
    {
        $sitePeer = SitePeer::query()->withoutArchived()->withoutSelf()
            ->where(function (Builder $query) {
                $query
                    ->where('last_verification_at', '<', now()->subHours(1))
                    ->orWhere('last_verification_at', null);
            })
            ->orderBy('last_verification_at')
            ->first();

        if (! $sitePeer || $limit < 1) {
            H::pfm('No more pending or limit hit: '.$limit.' '.__FUNCTION__, pfm: $pfm);

            return;
        }

        $site_peer_id = $sitePeer->id;
        sleep(100);
        (new BackgroundProcessingController)->verifySitePeer($site_peer_id);

        $limit--;
        self::verifySitePeers($limit, pfm: $pfm);
    }

    public function verifySitePeer(int $site_peer_id, $pfm = false): JsonResponse
    {
        info('verifySitePeer site_peer_id '.$site_peer_id);

        $site_peer_id = request()->site_peer_id ?? $site_peer_id;

        request()->merge(['site_peer_id' => $site_peer_id]);

        request()->validate(['site_peer_id' => 'required|int']);

        $sitePeer = SitePeer::find($site_peer_id);
        info('sitePeer '.json_encode($sitePeer));

        H::pfm('Checking hosting state of peer '.$sitePeer->client_address.' '.$sitePeer->site_id, pfm: $pfm);
        info('Checking hosting state of peer '.$sitePeer->client_address.' '.$sitePeer->site_id);
        $sitePeer->last_verification_at = now();
        $sitePeer->save();
        $startedAt = now();

        $success = false;
        $data = null;
        try {
            $url = H::a($sitePeer->client_address).'p2p_api/v1/check_site_hosting_state';
            $client = H::setupClient(H::isTorAddress($sitePeer->client_address));
            $options = [
                'timeout' => H::timeoutAdjust(H::isTorAddress($sitePeer->client_address), 6),
                'form_params' => [
                    'site_id' => $sitePeer->site_id,
                ],
                'prepare_ip' => true,
            ];
            H::prepareOptions($options, $url);

            $response = $client->post($url, $options);
            $body = (string) $response->getBody();

            H::pfm($body, pfm: $pfm);
            $result = json_decode($body);
            $success = $result->success;
            $data = $result->data;

        } catch (Throwable $th) {
            info('verifySitePeer '.$th->getMessage().' '.$th->getFile().' '.$th->getLine());
            $peer = Peer::where('client_address', $sitePeer->client_address)->first();
            H::decreasePeerReputation($peer);
            H::pfm($th->getMessage().' '.$th->getFile().' '.$th->getLine(), pfm: $pfm);
        }

        if ($success === true) {
            $endedAt = now();
            $secondsTaken = $endedAt->diffInSeconds($startedAt) == 0 ?: 1;
            if ($data->hosting_state == true) {
                $sitePeer->last_successfully_verified_at = now();
                $sitePeer->archived_at = null;
                $sitePeer->save();
            } elseif ($data->hosting_state == false) {
                $sitePeer->archived_at = now();
                $sitePeer->save();
            }

            $peer = Peer::where('client_address', $sitePeer->client_address)->first();
            if ($peer) {
                $peer->response_speed_seconds = $secondsTaken;
                $peer->save();
            }

            H::pfm('Got response '.$sitePeer->client_address.' '.json_encode($data), pfm: $pfm);
        } else {
            $last_successfully_verified_at = Carbon::parse($sitePeer->last_successfully_verified_at) ?? now()->subHours(25);
            if ($last_successfully_verified_at->diffInHours(now()) > 24 && $sitePeer->created_at->diffInHours(now()) > 24) {
                $sitePeer->delete();
                H::pfm('Site Peer not verified in 24h', pfm: $pfm);
            }
        }

        return $this->return_success(['verification_attempt_successful' => $success, 'state' => $data]);
    }

    public function hostedSiteInitHostingActions($main_site_id)
    {
        info('hostedSiteInitHostingActions '.$main_site_id);
        $siteIds = [$main_site_id];

        foreach ($siteIds as $key => $site_id) {
            BackgroundProcessingController::prepareMissingSiteDefinitionsRetrievals($site_id);

            SitesVisitorController::processContentRetrievals(site_id: $site_id, limit: 10, pfm: false);

            BackgroundProcessingController::schedulePendingDownloads($site_id, pfm: false);
            BackgroundProcessingController::processPendingDownloads($site_id, pfm: false);
            BackgroundProcessingController::checkHostedPromotableSiteDefinitions(site_id: $site_id, pfm: false);
            (new BackgroundProcessingController)->deleteSiteDefinitionsWhenNewerPresent(site_id: $site_id, pfm: false);

            (new ReplicationController)->replicateMissedSitePeers(continous_ondemand: true, site_id: $site_id, pfm: false);
            (new ReplicationController)->replicateMissedSiteDefinitions(continous_ondemand: true, site_id: $site_id, pfm: false);
            (new ReplicationController)->replicateMissedSitesVisitorRecords(continous_ondemand: true, site_id: $site_id, pfm: false);
            (new ReplicationController)->replicateMissedVisitorResources(continous_ondemand: true, site_id: $site_id, pfm: false);
            H::updateSitesHostedState(site_id: $site_id, pfm: false);
        }
    }

    public function scheduleOnDemandBackgroundAction(Request $request)
    {
        $tag = $request->tag;
        $scheduleEntry = [
            'tag' => $tag,
            'ensure_daily_execution' => false,
            'is_one_time' => true,
            'method_name' => 'dailyAt',
        ];

        $tagetExecutionTime = (now()->addMinutes(2));

        $scheduleEntry['execution_hour'] = $tagetExecutionTime->hour;
        $scheduleEntry['execution_minute'] = $tagetExecutionTime->minute;

        $cron = $scheduleEntry['execution_minute'].' '.$scheduleEntry['execution_hour'].' * * *';

        $alreadyPresentSchedule = BackgroundSchedule::where([
            ['cron', $cron],
            ['tag', $scheduleEntry['tag']],
        ])->first();

        if (! $alreadyPresentSchedule) {
            $backgroundSchedule = new BackgroundSchedule;

            $backgroundSchedule->tag = $scheduleEntry['tag'];
            $backgroundSchedule->ensure_daily_execution = $scheduleEntry['ensure_daily_execution'];
            $backgroundSchedule->method_name = $scheduleEntry['method_name'];
            $backgroundSchedule->execution_hour = $scheduleEntry['execution_hour'];
            $backgroundSchedule->execution_minute = $scheduleEntry['execution_minute'];
            $backgroundSchedule->cron = $cron;
            $backgroundSchedule->is_one_time = $scheduleEntry['is_one_time'];
            $backgroundSchedule->save();

            H::pfm('Scheduled One Time '.$scheduleEntry['tag']);
        } else {
            H::pfm('Background schedule already scheduled');
        }
    }

    public static function UpdateStatistics($pfm = false)
    {
        $sites = Site::get();
        foreach ($sites as $key => $site) {
            if (! $site->is_hosted) {
                $site->visitor_records_total_size = null;
                $site->visitor_resources_total_size = null;
            } else {
                $visitorRecordsSize = 0;
                $visitorRecords = VisitorRecord::where([
                    ['site_id', $site->site_id],
                ])->get();

                foreach ($visitorRecords as $key => $value) {
                    $visitorRecordsSize += strlen($value->record_json);
                }
                $site->visitor_records_total_size = $visitorRecordsSize;
                $site->visitor_records_count = count($visitorRecords);

                $visitorResourcesSize = 0;
                $visitorResources = VisitorResource::where([
                    ['site_id', $site->site_id],
                ])->get();

                foreach ($visitorResources as $key => $value) {
                    $visitorResourcesSize += $value->file_size;
                }

                $site->visitor_resources_total_size = $visitorResourcesSize;
                $site->visitor_resources_count = count($visitorResources);
            }
            $site->save();

            H::pfm('Updated: '.$site->site_id, pfm: $pfm);
        }
    }
}
