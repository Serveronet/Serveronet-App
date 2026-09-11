<?php

namespace App\Http\Controllers;

use App\Dicts\ActionTypes;
use App\Dicts\DataTypes;
use App\Dicts\KnownResponses;
use App\Dicts\MessageTypes;
use App\Dicts\RecordStates;
use App\Dicts\SettingIds;
use App\Http\Consts;
use App\Http\H;
use App\Models\CachedResource;
use App\Models\ContentRetrieval;
use App\Models\CrowdQuery;
use App\Models\CrowdQueryResult;
use App\Models\ListProviderEntry;
use App\Models\P2pMessage;
use App\Models\PassiveSession;
use App\Models\Peer;
use App\Models\ResultContainer;
use App\Models\Site;
use App\Models\SiteDefinition;
use App\Models\SiteDefinitionEnvelope;
use App\Models\SitePeer;
use App\Models\SitePeerRecordEnvelope;
use App\Models\VisitorRecord;
use App\Models\VisitorResource;
use App\Models\VisitorResourceEnvelope;
use App\Services\ChunkService;
use App\Services\IntegrityService;
use App\Services\ListProviderService;
use App\Services\P2pReplicationService;
use App\Services\PeerMixService;
use App\Services\PQCryptoService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use stdClass;
use Throwable;

class ClientController extends Controller
{
    protected function consumeReceivedMessage($message, $pfm = false)
    {
        $incomingJson = $message->json_payload;

        switch ($message->type) {
            case MessageTypes::site_definition:
                (new P2pReplicationService)->handleIncomingRecords(DataTypes::site_definitions, incomingJson: $incomingJson, pfm: $pfm);
                break;

            case MessageTypes::site_peer:
                (new P2pReplicationService)->handleIncomingRecords(DataTypes::site_peers, incomingJson: $incomingJson, pfm: $pfm);
                break;

            case MessageTypes::visitor_record:
                (new P2pReplicationService)->handleIncomingRecords(DataTypes::visitor_records, incomingJson: $incomingJson, pfm: $pfm);
                break;

            case MessageTypes::visitor_resource:
                (new P2pReplicationService)->handleIncomingRecords(DataTypes::visitor_resources, incomingJson: $incomingJson, pfm: $pfm);
                break;
        }

        return $this->return_success();
    }

    public function processNewMessage(Request $request)
    {
        try {
            $request->validate([
                'message_json' => ['required', 'max:'.(Consts::recordJsonMaxSizeBytes)],
            ]);
        } catch (Throwable $th) {
            return $this->return_failure($th->getMessage());
        }

        $message_json = $request->input('message_json');
        $message = json_decode($message_json);
        $receivedMessage = new P2pMessage;
        $message_id = $message->message_id;

        $messagePresent = P2pMessage::where('message_id', $message_id)->first();

        if ($messagePresent) {
            return $this->return_failure('message already known');
        }

        $json_payload = $message->json_payload;
        $type = $message->type;

        $receivedMessage->message_id = $message_id;
        $receivedMessage->type = $type;
        $receivedMessage->json_payload = $json_payload;
        $receivedMessage->site_id = $message->site_id ?? null;

        $receivedMessage->priority = $message->priority ?? null;

        $receivedMessage->sent_at = now();
        $receivedMessage->consumed_at = now();

        $receivedMessage->save();

        (new ClientController)->consumeReceivedMessage($receivedMessage);

        return $this->return_success('ack');
    }

    public function onReceiveMessage_peer(Request $request)
    {
        return $this->processNewMessage($request);
    }

    public function onReceiveMessage_site_definition(Request $request)
    {
        return $this->processNewMessage($request);
    }

    public function onReceiveMessage_site_peer(Request $request)
    {
        return $this->processNewMessage($request);
    }

    public function onReceiveMessage_visitor_record(Request $request)
    {
        return $this->processNewMessage($request);
    }

    public function onReceiveMessage_visitor_resource(Request $request)
    {
        return $this->processNewMessage($request);
    }

    public function getDataFromHostingPeers(Request $request): ResultContainer
    {
        $rc = new ResultContainer;
        $rc->operation_successful = true;

        $retrieval_id = $request->retrieval_id;

        if ($retrieval_id) {
            $retrieval = ContentRetrieval::where([
                ['retrieval_id', $retrieval_id],
            ])->first();

            $retrieval->state = RecordStates::retrieval_processing;
            $retrieval->save();
            $site_id = $retrieval->site_id;
            $sha256 = $retrieval->sha256;
            $entity_id = $retrieval->entity_id;
            $action_type = $retrieval->action_type;
        } else {
            $site_id = $request->site_id;
            $sha256 = $request->sha256;
            $entity_id = $request->entity_id;
            $action_type = $request->action_type;
        }

        info('getDataFromHostingPeers retrieval_id:  '.$retrieval_id);

        $originator_peer_id = $request->originator_peer_id;

        $ttl = $request->ttl;

        if ($ttl <= 0) {
            $retrieval->state = RecordStates::retrieval_failed;
            $retrieval->fulfiller_id = 'TTL exhausted';
            $retrieval->save();
            $rc->operation_successful = false;
            $rc->error_message = 'TTL exhausted';

            return $rc;
        }

        $hostingPeers = (new PeerMixService)->getPeerMix(site_id: $site_id);
        $livePassiveSessionsForSite = PassiveSession::where('last_heartbeat_at', '>', now()->subMinutes(1)->toDateTimeString())
            ->where('site_ids_json', 'like', '%'.$site_id.'%')->get();

        info('$hostingPeers');
        info(json_encode($hostingPeers->pluck('client_address')));

        if (count($hostingPeers) == 0 && count($livePassiveSessionsForSite) == 0) {
            $retrieval->state = RecordStates::retrieval_failed;
            $retrieval->fulfiller_id = 'fulfiller_id - No peers';
            $retrieval->save();
            $rc->operation_successful = false;
            $rc->error_message = 'No peers to handle the query';
            $rc->debug_data = json_encode($hostingPeers);

            return $rc;
        }

        $ttl--;
        $peerCount = 0;
        foreach ($hostingPeers as $peer) {
            $peerCount++;
            $requestConfigSet = [];
            $requestConfigSet['retrieval_id'] = $retrieval_id;
            $requestConfigSet['ttl'] = $ttl;
            $requestConfigSet['originator_peer_id'] = $originator_peer_id;
            $requestConfigSet['client_address'] = H::a($peer->client_address);
            $requestConfigSet['address'] = H::a($peer->client_address).'p2p_api/v1/request_data_endpoint';
            $requestConfigSet['sha256'] = $sha256;
            $requestConfigSet['entity_id'] = $entity_id;
            $requestConfigSet['site_id'] = $site_id;
            $requestConfigSet['action_type'] = $action_type;

            H::dispatchInternalAsync('handle_crowd_retrieval_order', $requestConfigSet);
        }

        $delays = [
            ['delay' => 0.1],
            ['delay' => 0.5],
            ['delay' => 1],
            ['delay' => 2],
            ['delay' => 3],
            ['delay' => 4],
            ['delay' => 5],
        ];

        foreach ($delays as $key => $d) {

            /* Retrieved data is validated when saving responses */

            $completedRetrieval = ContentRetrieval::where([
                ['retrieval_id', $retrieval_id], ['state', RecordStates::retrieval_completed],
            ])->first();

            info('getDataFromHostingPeers $retrieval_id '.$d['delay'].' '.$retrieval_id);

            if ($completedRetrieval) {
                $rc->data = RecordStates::retrieval_completed;
                $rc->metadata = $completedRetrieval->action_type;

                return $rc;
            } else {
                sleep($d['delay']);

                continue;
            }
        }

        $retrieval = ContentRetrieval::where('retrieval_id', $retrieval_id)->first();
        $retrieval->state = RecordStates::retrieval_failed;
        $retrieval->save();

        $rc->operation_successful = false;
        $rc->error_message = 'Retrieval failed';

        return $rc;
    }

    public function handleReceiveFulfilledCrowdQuery(Request $request)
    {
        info('handleReceiveFulfilledCrowdQuery start');

        $mayPayloadSize = 1000 * Consts::recordJsonMaxSizeBytes;

        try {
            $request->validate([
                'data' => ['required', 'json', 'max:'.$mayPayloadSize],
            ]);
        } catch (Throwable $th) {
            info('handleReceiveFulfilledCrowdQuery failure');
            info($th->getMessage().' '.$th->getFile().' '.$th->getLine());

            return $this->return_failure($th->getMessage());
        }

        $crowdQueryResult = json_decode($request->data);

        $query_id = $crowdQueryResult->query_id;

        $crowdQuery = CrowdQuery::where('query_id', $query_id)->first();
        if (! $crowdQuery)
            return $this->return_failure('No such a Crowd Query');

        $fulfiller_id = $crowdQueryResult->fulfiller_id;
        $remote_peer_id = $crowdQueryResult->remote_peer_id ?? 'remote_peer_id fallback';
        $trusted_site_peer_token = $crowdQueryResult->trusted_site_peer_token ?? null;

        $request->merge([
            'query_id' => $query_id,
            'fulfiller_id' => $fulfiller_id,
            'remote_peer_id' => $remote_peer_id,
            'trusted_site_peer_token' => $trusted_site_peer_token,
        ]);

        try {
            $request->validate([
                'query_id' => ['required', 'alpha_dash:ascii', 'max:1024'],
                'fulfiller_id' => ['string', 'max:1024'],
                'remote_peer_id' => ['required', 'string', 'max:1024'],
                'trusted_site_peer_token' => ['nullable', 'string', 'max:1024'],
            ]);
        } catch (Throwable $th) {
            info('handleReceiveFulfilledCrowdQuery failure');
            info($th->getMessage().' '.$th->getFile().' '.$th->getLine());

            return $this->return_failure($th->getMessage());
        }

        /* Retransmission protection */
        $alreadyHandledQueryResult = CrowdQueryResult::where([
            ['query_id', $query_id],
            ['fulfiller_id', $fulfiller_id],
            ['remote_peer_id', $remote_peer_id],
        ])->first();
        if ($alreadyHandledQueryResult) {
            info('alreadyHandledQueryResult');

            return;
        }

        $crowdQueryResults = new CrowdQueryResult;
        $crowdQueryResults->query_id = $query_id;
        $crowdQueryResults->fulfiller_id = $fulfiller_id;
        $crowdQueryResults->remote_peer_id = $remote_peer_id;
        $crowdQueryResults->trusted_site_peer_token = $trusted_site_peer_token;
        $crowdQueryResults->payload = json_encode($crowdQueryResult->payload);
        $crowdQueryResults->save();
    }

    public function handleReceiveFulfilledRetrieval(Request $request)
    {
        info('$request->data limit 160');
        info(Str::limit($request->data, 160));

        $mayPayloadSize = Consts::payloadMaxSizeBytesServeronet;

        try {
            $request->validate([
                'data' => ['required', 'json', 'max:'.$mayPayloadSize],
            ]);
        } catch (Throwable $th) {
            info('handleReceiveFulfilledRetrieval failure');
            info($th->getMessage().' '.$th->getFile().' '.$th->getLine());

            return $this->return_failure($th->getMessage());
        }

        $responseData = json_decode($request->data);

        $retrieval_id = $responseData->retrieval_id;
        $payload = $responseData->payload;
        // info('$payload');
        // info(Str::limit($payload, 100));

        $client_address = $responseData->client_address ?? 'no_client_address_ipc';
        $fulfiller_id = $request->fulfiller_id ?? 'Fullfiller property is empty. | client_address: '.$client_address;

        info('$fulfiller_id');
        info($fulfiller_id);
        $retrieval = ContentRetrieval::where('retrieval_id', $retrieval_id)->first();
        info('$retrieval_id '.$retrieval_id);

        if (! $retrieval) {
            info('No such retrieval found from response');

            return;
        }

        if (in_array($retrieval->state, [RecordStates::retrieval_completed])) {
            info('Retrieval already completed');

            return;
        }

        $request = new Request;
        $request->merge([
            'fulfiller_id' => $fulfiller_id,
            'retrieval_id' => $retrieval_id,
            'client_address' => $client_address,
        ]);
        try {
            $request->validate([
                'fulfiller_id' => ['required', 'string', 'max:1024'],
                'retrieval_id' => ['required', 'alpha_dash:ascii', 'max:1024'],
                'client_address' => ['required', 'string', 'max:1024'],
            ]);
        } catch (Throwable $th) {
            info('handleReceiveFulfilledRetrieval failure');
            info($th->getMessage().' '.$th->getFile().' '.$th->getLine());

            return $this->return_failure($th->getMessage());
        }
        $retrievalSuccessful = true;
        switch ($retrieval->action_type) {

            case ActionTypes::retrieve_resource:
                info('ActionTypes::retrieve_resource');
                $cr = CachedResource::whereSha256($retrieval->sha256)->first();

                if ($cr) {
                    (new ClientController)->markRetrievalAsCompleted($retrieval, $payload, $fulfiller_id);
                    info('Already retrieved in another thread');

                    return;
                }

                $file_name = Str::random(40);
                $content = base64_decode($payload);
                $file_size = strlen($content);

                $calculatedSha256 = hash('sha256', $content);

                info('$calculatedSha256 '.$calculatedSha256);
                info('$retrieval->sha256 '.$retrieval->sha256);

                if ($calculatedSha256 !== $retrieval->sha256) {
                    $peer = Peer::where('client_address', H::a($client_address))->first();
                    H::decreasePeerReputation($peer);
                    info('handleReceiveFulfilledRetrieval Hash mismatch');

                    return;
                }

                Storage::disk('cached_resources')->put($file_name, $content);
                $cachedResource = new CachedResource;
                $cachedResource->sha256 = $retrieval->sha256;
                $cachedResource->file_name = $file_name;
                $cachedResource->file_size = $file_size;

                $cachedResource->save();

                $retrievalSuccessful = true;

                break;

            case ActionTypes::retrieve_site_definition:
                try {
                    $recordEnvelope = new SiteDefinitionEnvelope(json_decode($payload));
                } catch (Throwable $th) {
                    info('Malformed envelope received - ignoring. '.$th->getMessage());

                    return;
                }
                $retrievalSuccessful = (new P2pReplicationService)
                    ->handleIncomingSiteDefinition(incomingRecordEnvelope: $recordEnvelope, criteria: ['site_id' => $retrieval->site_id]);
                break;

            case ActionTypes::retrieve_visitor_resource_defintion:
                try {
                    $recordEnvelope = new VisitorResourceEnvelope(json_decode($payload));
                } catch (Throwable $th) {
                    info('Malformed envelope received - ignoring. '.$th->getMessage());

                    return;
                }
                $retrievalSuccessful = (new P2pReplicationService)
                    ->handleIncomingVisitorResource(incomingRecordEnvelope: $recordEnvelope, forceSave: true,
                        criteria: ['site_id' => $retrieval->site_id, 'entity_id' => $retrieval->entity_id]);
                break;

            case ActionTypes::retrieve_site_peers:
                try {
                    $recordEnvelope = new SitePeerRecordEnvelope;
                    $incomingRecordEnvelope = json_decode($payload);
                    $recordEnvelope->client_address = $incomingRecordEnvelope->client_address;
                    $recordEnvelope->site_id = $incomingRecordEnvelope->site_id;
                } catch (Throwable $th) {
                    return;
                }
                $retrievalSuccessful = (new P2pReplicationService)
                    ->handleIncomingSitePeer(incomingRecordEnvelope: $recordEnvelope,
                        criteria: ['site_id' => $retrieval->site_id]);
                break;
        }

        if ($retrievalSuccessful) {
            (new ClientController)->markRetrievalAsCompleted($retrieval, $payload, $fulfiller_id);
        }
    }

    public function handleCrowdRetrievalOrder(Request $request): string
    {
        $requestConfigSet = H::unwrapRequestConfigSet();
        if (! H::isInternalCallCurrent($requestConfigSet)) {
            info('Outdated or spoofed internal request');

            return 'Outdated or spoofed internal request';
        }
        $internalRequestId = H::startInteralRequestReporting(__FUNCTION__);

        $address = $requestConfigSet['address'];
        $client_address = $requestConfigSet['client_address'];
        $site_id = $requestConfigSet['site_id'];
        $retrieval_id = $requestConfigSet['retrieval_id'];
        $entity_id = $requestConfigSet['entity_id'] ?? null;
        $ttl = $requestConfigSet['ttl'];
        $originator_peer_id = $requestConfigSet['originator_peer_id'];
        $sha256 = $requestConfigSet['sha256'];

        $action_type = $requestConfigSet['action_type'];

        try {
            $url = $address;

            $client = H::setupClient(H::isTorAddress($url));

            $form_params = [
                'site_id' => $site_id,
                'retrieval_id' => $retrieval_id,
                'ttl' => $ttl,
                'sha256' => $sha256,
                'entity_id' => $entity_id,
                'originator_peer_id' => $originator_peer_id,
                'action_type' => $action_type,
            ];
            $options = [
                'timeout' => H::timeoutAdjust(H::isTorAddress($url), 10),
                'form_params' => $form_params,
                'prepare_ip' => true,
            ];
            H::prepareOptions($options, $url);

            $response = $client->post($url, $options);

            info('json_encode($requestConfigSet)');
            info(json_encode($requestConfigSet));
            info($response->getStatusCode());
            $body = (string) $response->getBody();

            $success = false;
            try {
                $body = (string) $response->getBody();
                $decoded_body = json_decode($body);
                $success = false;

                if ($decoded_body) {
                    if (property_exists($decoded_body, 'success')) {
                        $success = $decoded_body->success;
                    }
                }

                if ($success == true) {

                    ClientController::handlePeerIsConnectable($client_address);

                    $retrieval_id = $decoded_body->data->retrieval_id;
                    $payload = $decoded_body->data->payload;

                    $responseObject = new stdClass;
                    $responseObject->retrieval_id = $retrieval_id;
                    $responseObject->payload = $payload;
                    $responseObject->client_address = $client_address;

                    $request = new Request;

                    $request->merge([
                        'data' => json_encode($responseObject),
                    ]);

                    (new ClientController)->handleReceiveFulfilledRetrieval($request);

                }
            } catch (Throwable $th) {
                info($th->getMessage().' '.$th->getFile().' '.$th->getLine());
            }

        } catch (Throwable $th) {
            info($th->getMessage().' '.$th->getFile().' '.$th->getLine());
        }
        H::endInteralRequestReporting($internalRequestId);

        return true;
    }

    protected function markRetrievalAsCompleted($retrieval, $payload, $fulfiller_id): void
    {
        if (H::isDevNode()) {
            /* Debug */
            $payloadLength = strlen($payload);
            $payloadFirstChars = Str::limit($payload, 25);
            $retrieval->payload = '$payloadLength: '.$payloadLength.' $payloadFirstChars: '.$payloadFirstChars;
        }

        $retrieval->fulfiller_id = $fulfiller_id;
        $retrieval->state = RecordStates::retrieval_completed;
        $retrieval->save();
    }

    public static function handlePeerIsConnectable($client_address)
    {
        $peer = Peer::where('client_address', H::a($client_address))->first();
        if (! $peer) {
            if (in_array($client_address, H::getSelfAddresses())) {
                return;
            }
            $peer = new Peer;
            $peer->client_address = H::a($client_address);
        } else {
            H::increasePeerReputation($client_address);
        }
        $peer->last_connection_check_at = now();
        $peer->last_connected_at = now();
        $peer->save();
    }

    public static function handlePeerNotConnectable(Throwable $th, $client_address)
    {
        info('handlePeerNotConnectable '.$client_address);
        switch (get_class($th)) {
            case 'GuzzleHttp\Exception\ConnectException':

                $peer = Peer::where('client_address', H::a($client_address))->first();
                if (! $peer) {
                    if (in_array($client_address, H::getSelfAddresses())) {
                        return;
                    }

                    $peer = new Peer;
                    $peer->client_address = H::a($client_address);
                } else {
                    H::decreasePeerReputation($peer);
                }
                $peer->last_connection_check_at = now();
                $peer->save();

                break;

            default:

                break;
        }
    }

    public function handlePeerRequestingData(Request $request)
    {
        info('handlePeerRequestingData start');
        try {
            $request->validate([
                'sha256' => ['nullable', 'alpha_dash:ascii', 'max:1024'],
                'entity_id' => ['nullable', 'alpha_dash:ascii', 'max:1024'],
                'action_type' => ['nullable', 'alpha_dash:ascii', 'max:1024'],
                'retrieval_id' => ['nullable', 'alpha_dash:ascii', 'max:1024'],
                'site_id' => Consts::notRequiredSiteIdValidationRule,
            ]);
        } catch (Throwable $th) {
            info($th->getMessage().' '.$th->getFile().' '.$th->getLine());

            return $this->return_failure($th->getMessage());
        }

        $sha256 = $request->sha256;
        $entity_id = $request->entity_id;
        $site_id = $request->site_id;
        $action_type = $request->action_type;
        $retrieval_id = $request->retrieval_id;

        info('handlePeerRequestingData '.$action_type.' '.$site_id.' '.$sha256);
        switch ($action_type) {
            case ActionTypes::retrieve_resource:
                $res = CachedResource::whereSha256($sha256)->first();

                if (! $res) {
                    return $this->return_failure('Resource not in possession.');
                }

                $res->last_request_at = now();
                $res->requests_counter++;
                $res->save();

                $file_content = Storage::disk('cached_resources')->get($res->file_name);

                $data = [
                    'payload' => base64_encode($file_content),
                    'retrieval_id' => $retrieval_id,
                    'ipfs_hash' => $res->ipfs_hash,
                    'fulfiller_id' => H::getPublicSelfAddress(),
                ];

                return $this->return_success($data);

                break;

            case ActionTypes::retrieve_site_definition:

                $siteDefinition = SiteDefinition::where('site_id', $site_id)->select(SiteDefinition::$publicProperties)
                    ->orderByDesc('entity_created')->first();

                if (! $siteDefinition) {
                    return $this->return_failure('No Site Definition in possession.');
                }

                $site = Site::whereSiteId($site_id)->first();
                $siteDefinition->visitor_records_preliminary_total_size = $site->visitor_records_total_size;
                $siteDefinition->visitor_resources_preliminary_total_size = $site->visitor_resources_total_size;

                return $this->return_success([
                    'payload' => json_encode(
                        [
                            'record_json' => $siteDefinition->record_json,
                            'signature' => $siteDefinition->signature,
                            'signer_verification_key_base64' => $siteDefinition->signer_verification_key_base64,
                        ]
                    ),
                    'retrieval_id' => $retrieval_id,
                    'fulfiller_id' => H::getPublicSelfAddress(),
                ]);

                break;

            case ActionTypes::retrieve_visitor_resource_defintion:

                $visitorResource = VisitorResource::where([
                    ['entity_id', $entity_id],
                    ['site_id', $site_id],
                ])->withTrashed()->select(VisitorResource::$publicProperties)
                    ->first();

                if (! $visitorResource) {
                    return $this->return_failure('No such Visitor Resource');
                }

                return $this->return_success([
                    'payload' => json_encode(
                        [
                            'record_json' => $visitorResource->record_json,
                            'signature' => $visitorResource->signature,
                            'signer_verification_key_base64' => $visitorResource->signer_verification_key_base64,
                        ]
                    ),
                    'retrieval_id' => $retrieval_id,
                    'fulfiller_id' => H::getPublicSelfAddress(),
                ]);

                break;

            case ActionTypes::retrieve_site_peers:

                $sitePeers = SitePeer::where('site_id', $site_id)->withoutArchived()
                    ->select(SitePeer::$publicProperties)->get();

                if (! count($sitePeers) > 0) {
                    return $this->return_failure('No site peers');
                }

                return $this->return_success([
                    'payload' => json_encode($sitePeers),
                    'retrieval_id' => $retrieval_id,
                    'fulfiller_id' => H::getPublicSelfAddress(),
                ]);

                break;
        }

        return $this->return_failure('Nothing to return');
    }

    public function handleRequestModifiedBetweenDates(Request $request)
    {
        info('handleRequestModifiedBetweenDates Page: '.$request->page);

        try {
            $request->validate([
                'count_only' => ['boolean'],
                'page' => ['integer'],
                'data_type' => ['required', 'string', 'max:1024'],
                'from' => ['required', 'string', 'max:1024'],
                'to' => ['required', 'string', 'max:1024'],
                'site_id' => Consts::notRequiredSiteIdValidationRule,
            ]);
        } catch (Throwable $th) {
            return $this->return_failure($th->getMessage());
        }

        $count_only = $request->count_only;
        $data_type = $request->data_type;
        $from = $request->from;
        $to = $request->to;
        $site_id = $request->site_id;
        $page = $request->page;

        /* Maximum adjusted for low spec peers - do not increase */
        $perPage = 20;

        $response = null;
        $range = [];

        switch ($data_type) {

            case DataTypes::site_peers:

                $range = [['updated_at', '>', $from], ['updated_at', '<', $to]];
                $queryBuilder = SitePeer::where($range)->withoutArchived()->where('site_id', $site_id)
                    ->select(SitePeer::$publicProperties)->orderBy('updated_at');
                break;

            case DataTypes::site_definitions:

                $from = Carbon::parse($from)->format(Consts::visitorDataDateIdFormat);
                $to = Carbon::parse($to)->format(Consts::visitorDataDateIdFormat);
                $range = [['entity_created', '>', $from], ['entity_created', '<', $to]];
                $queryBuilder = SiteDefinition::where($range)->where('site_id', $site_id)
                    ->select('record_json', 'signature', 'signer_verification_key_base64')->orderBy('updated_at');
                break;

            case DataTypes::visitor_records:

                $from = Carbon::parse($from)->format(Consts::visitorDataDateIdFormat);
                $to = Carbon::parse($to)->format(Consts::visitorDataDateIdFormat);
                $range = [['entity_updated', '>', $from], ['entity_updated', '<', $to]];
                $queryBuilder = VisitorRecord::withTrashed()->where($range)->where('site_id', $site_id)
                    ->with('grant_record')
                    ->select('record_json', 'signature', 'grantee_visitor_id', 'visitor_id', 'signer_verification_key_base64')
                    ->orderBy('updated_at');
                break;

            case DataTypes::visitor_resources:

                $from = Carbon::parse($from)->format(Consts::visitorDataDateIdFormat);
                $to = Carbon::parse($to)->format(Consts::visitorDataDateIdFormat);
                $range = [['entity_updated', '>', $from], ['entity_updated', '<', $to]];
                $queryBuilder = VisitorResource::withTrashed()->where($range)->where('site_id', $site_id)
                    ->with('grant_record')
                    ->select('record_json', 'signature', 'grantee_visitor_id', 'visitor_id', 'signer_verification_key_base64')
                    ->orderBy('updated_at');
                break;
        }
        info('From DT To: '.$from.' '.$data_type.' '.$to.' page: '.$page.' range: '.json_encode($range));

        if ($count_only) {
            $response = $queryBuilder->count();

            $record_count_map = $queryBuilder
                ->select(DB::raw('DATE(updated_at) as date'), DB::raw('COUNT(*) as count'))
                ->groupBy(DB::raw('DATE(updated_at)'))
                ->orderBy('date', 'asc')
                ->pluck('count', 'date')
                ->toArray();

            return $this->return_success([
                'count' => $response,
                'per_page' => $perPage,
                'record_count_map' => json_encode($record_count_map),
            ]);
        } else {

            info($queryBuilder->toSql());

            $response = $queryBuilder->paginate(perPage: $perPage, page: $page);

            return response()->json($response, 200);
        }
    }

    /** Is peer eager to host a specific site */
    public function handleSiteHostingEagernessCheck(Request $request)
    {
        $site_id = $request->site_id;
        if ($invalidResult = (new UtilsController)->validatedReturnSiteId($request, $site_id)) {
            return $invalidResult;
        }

        $site = Site::whereSiteId($site_id)->with('most_recent_site_definition')->first();

        if ($site?->is_to_be_hosted) {
            return $this->return_success(['eager' => true]);
        } else {
            return $this->return_success(['eager' => false]);
        }
    }

    public function handleHashesHostingStateCheck(Request $request)
    {
        try {
            $request->validate([
                'hashes' => ['required', 'array', 'max:10024'],
                'justification_record_json' => ['nullable', 'string', 'max:'.Consts::recordJsonMaxSizeBytes],
                'justification_signature' => ['nullable', 'string', 'max:10024'],
                'justification_signer_verification_key_base64' => ['nullable', 'string', 'max:10024'],
            ]);
        } catch (Throwable $th) {
            return $this->return_failure($th->getMessage());
        }

        $hashes = $request->hashes;

        $justification_record_json = $request->justification_record_json;
        $justification_signature = $request->justification_signature;
        $justification_signer_verification_key_base64 = $request->justification_signer_verification_key_base64;

        try {
            $incmmingRecordEnvelope = new stdClass;
            $incmmingRecordEnvelope->record_json = $justification_record_json;
            $incmmingRecordEnvelope->signature = $justification_signature;
            $incmmingRecordEnvelope->signer_verification_key_base64 = $justification_signer_verification_key_base64;
            $recordEnvelope = new SiteDefinitionEnvelope($incmmingRecordEnvelope);

        } catch (Throwable $th) {
            info('Malformed envelope received - ignoring. '.$th->getMessage());

            return $this->return_failure('Check not justified');
        }

        $isJustified = self::isJustified(DataTypes::site_definitions, $recordEnvelope);
        if (! $isJustified) {
            return $this->return_failure('Check not justified');
        }

        $hostedResources = CachedResource::whereIn('sha256', $hashes)->select('sha256')->get();

        $hostedHashes = $hostedResources->pluck('sha256')->toArray();

        $notHostedHashes = array_diff($hashes, $hostedHashes);

        info('$notHostedHashes');
        info(json_encode($notHostedHashes));

        if (count($notHostedHashes) == 0) {
            $notHostedHashes = [];
        }

        return $this->return_success($notHostedHashes);
    }

    public function handleSiteHostingStateCheck(Request $request)
    {
        try {
            $request->validate([
                'site_id' => Consts::siteIdValidationRule,
            ]);
        } catch (Throwable $th) {
            return $this->return_failure($th->getMessage());
        }

        $site_id = $request->site_id;
        $site = Site::whereSiteId($site_id)->with('list_entries')->first();

        if (! $site) {
            return $this->return_success(['hosting_state' => false, 'site_is_banned' => false,
                'owner_only' => config('sn.serve_owner_only')]);
        }

        $site_is_banned = $site->list_entries->sum('is_manual_entry') > 0;

        return $this->return_success(['hosting_state' => $site->is_hosted,
            'site_is_banned' => $site_is_banned, 'owner_only' => config('sn.serve_owner_only')]);
    }

    public function handleReceivedResourceUpload(Request $request)
    {
        info('handleReceivedResourceUpload');

        try {
            $request->validate([
                'action_type' => ['required', 'alpha_dash:ascii', 'max:1024'],
                'justification_record_json' => ['required', 'string', 'max:'.Consts::recordJsonMaxSizeBytes],
                'justification_signature' => ['required', 'string', 'max:10024'],
                'justification_signer_verification_key_base64' => ['nullable', 'string', 'max:10024'],
            ]);
        } catch (Throwable $th) {
            info($th->getLine().' '.$th->getFile().' '.$th->getMessage());

            return $this->return_failure($th->getMessage());
        }

        try {
            $request->validate([
                'encoded_upload_body' => ['required', 'max:'.(Consts::p2pUploadMaxFileSizeBytesNetwork)],
            ]);
        } catch (Throwable $th) {
            info($th->getLine().' '.$th->getFile().' '.$th->getMessage());

            return $this->return_failure($th->getMessage().' uploaded_file');
        }

        $upload_body = base64_decode($request->encoded_upload_body);
        $uploadedFile = $upload_body;
        $sha256 = hash('sha256', $uploadedFile);

        $action_type = $request->action_type;

        $justification_context_map = Consts::justification_context_map;
        $justification_context = $justification_context_map[$action_type];

        $justification_record_json = $request->justification_record_json;
        $justification_signature = $request->justification_signature;
        $justification_signer_verification_key_base64 = $request->justification_signer_verification_key_base64;

        try {
            $incmmingRecordEnvelope = new stdClass;
            $incmmingRecordEnvelope->record_json = $justification_record_json;
            $incmmingRecordEnvelope->signature = $justification_signature;
            $incmmingRecordEnvelope->signer_verification_key_base64 = $justification_signer_verification_key_base64;
            $recordEnvelope = new SiteDefinitionEnvelope($incmmingRecordEnvelope);

        } catch (Throwable $th) {
            info('Malformed envelope received - ignoring. '.$th->getMessage());

            return $this->return_failure('Check not justified');
        }

        $isJustified = self::isJustified($justification_context, $recordEnvelope);
        if (! $isJustified) {
            return $this->return_failure('Upload not justified');
        }

        info('$upload_body '.strlen($request->encoded_upload_body).' '.Str::limit(trim($upload_body), 20));

        try {
            $cachedResource = CachedResource::where([
                ['sha256', $sha256],
            ])->first();
            $storgingWasRequired = false;
            if (! $cachedResource) {
                $file_name = Str::random(40);
                Storage::disk('cached_resources')->put($file_name, $uploadedFile);

                $cachedResource = new CachedResource;
                $cachedResource->sha256 = $sha256;
                $cachedResource->file_name = $file_name;
                $cachedResource->file_size = strlen($uploadedFile);

                $cachedResource->save();

                $storgingWasRequired = true;

                (new ChunkService)->consumeReceivedResourceChunk($action_type, $justification_record_json, $sha256);
            }

            return $this->return_success(['storgingWasRequired' => $storgingWasRequired]);
        } catch (Throwable $th) {
            info($th->getLine().' '.$th->getFile().' '.$th->getMessage());

            return $this->return_failure($th->getMessage());
        }
    }

    public function serveExternalPeerDetails(Request $request)
    {
        return $this->return_success($request->ip());
    }

    public function checkPeerConnectivity(
        Peer $peer, $do_inbound_connectivity_check = false, $is_transient = false,
        $connect_timeout = 10, $pfm = false): ResultContainer
    {
        $rc = new ResultContainer;
        $rc->operation_successful = false;
        $rc->data['connection_attempt_successful'] = false;
        $rc->data['do_inbound_connectivity_check'] = $do_inbound_connectivity_check;

        info('checkPeerConnectivity '.json_encode($peer));

        $peer->last_connection_check_at = now();

        $control = Str::random(16);
        
        if (! $is_transient) {
            $peer->save();
        }
        Log::debug('Peer is_transient: '.tfyn($is_transient));

        H::pfm('Checking Peer connectivity '.$peer->client_address, pfm: $pfm);
        $rc->data['client_address'] = $peer->client_address;
        $success = false;

        $url = H::a($peer->client_address).'p2p_api/v1/check_peer';
        $client = H::setupClient(H::isTorAddress($peer->client_address), long_connect: true);
        $peer_random_id = H::getSettVal(SettingIds::peer_random_id);
        
        $params = [
            'requestor_external_address' => H::getPublicSelfAddress() ?? 'No self address',
            'do_inbound_connectivity_check' => $do_inbound_connectivity_check,
            'peer_random_id' => $peer_random_id,
            'control' => $control,
        ];
        $options = [
            'connect_timeout' => H::timeoutAdjust(H::isTorAddress($peer->client_address), $connect_timeout),
            'prepare_ip' => true,
            'form_params' => $params,
        ];
        H::prepareOptions($options, $url);

        try {
            $response = $client->request('POST', $url, $options);
            $body = (string) $response->getBody();
            $decodedBody = json_decode($body);
            $success = $decodedBody->success;
            $data = $decodedBody->data;


            if ($do_inbound_connectivity_check) {

                $inbound_connectivity_successful = $data
                    ->inbound_connectivity_check_result->data->connection_attempt_successful ?? null;

                $peer->last_inbound_connection_check_at = now();

                if ($inbound_connectivity_successful) {
                    $peer->last_inbound_connected_at = now();
                    $rc->data['inbound_connectivity_successful'] = true;
                } else {
                    $peer->last_inbound_connected_at = null;
                    $rc->data['inbound_connectivity_successful'] = false;
                }

                if (! $is_transient) {
                    $peer->save();
                }
                Log::debug('Peer is_transient: '.tfyn($is_transient));
            }
        } catch (Throwable $th) {
            $rc->error_message = $th->getMessage();

            return $rc;
        }

        $rc->operation_successful = true;
        $deleted = false;

        if ($success == true && $data->result == KnownResponses::ok) {
            $rc->data['connection_attempt_successful'] = true;
            $peer->last_connected_at = now();
            H::increasePeerReputation($peer->client_address);
            H::pfm('Connected succesfully to: '.$peer->client_address, pfm: $pfm);
        } elseif ($success == true && $data->result == KnownResponses::cyclic_occured) {

            /* Cyclic occured */
            info('cyclic_occured');
            H::decreasePeerReputation($peer);
            if ($data->control == $control) {
                if (! $is_transient) {
                    $peer->delete();
                    $deleted = true;
                }
            }

            $rc->error_message = 'cyclic_occured';
        } else {
            $rc->error_message = $data->result;
            H::decreasePeerReputation($peer);
            H::pfm('Connection failed to: '.$peer->client_address, pfm: $pfm);
        }

        if (! $is_transient && ! $deleted) {
            $peer->save();
        }

        return $rc;
    }

    public function handlePeerStateCheck(Request $request)
    {
        info('handlePeerStateCheck');
        if (! $request) {
            $request = new Request;
        }

        $do_inbound_connectivity_check = $request->do_inbound_connectivity_check;
        $peer_random_id = $request->peer_random_id;
        $requestor_external_address = $request->requestor_external_address;

        try {
            $request->merge(['do_inbound_connectivity_check' => $do_inbound_connectivity_check]);
            $request->merge(['peer_random_id' => $peer_random_id]);
            $request->merge(['requestor_external_address' => $requestor_external_address]);
            $request->validate([
                'do_inbound_connectivity_check' => ['boolean', 'nullable'],
                'peer_random_id' => ['string', 'nullable'],
                'requestor_external_address' => ['string', 'nullable'],
                'control' => ['required', 'string', 'size:16'],
            ]);
        } catch (Throwable $th) {
            info('handlePeerStateCheck Validation error '.$th->getMessage());
            return $this->return_success([
                'result' => KnownResponses::validation_failed,
                'error_message' => $th->getMessage(),
            ]);
        }

        if ($peer_random_id == H::getSettVal(SettingIds::peer_random_id)) {
            return $this->return_success(['result' => KnownResponses::cyclic_occured, 'control' => $request->control]);
        }

        if ($do_inbound_connectivity_check) {
            if ($request->requestor_external_address) {
                $client_address = H::a($request->requestor_external_address);
                $peer = new Peer;
                $peer->client_address = $client_address;

                $checkRC = (new ClientController)->checkPeerConnectivity(peer: $peer,
                    connect_timeout: 5, is_transient: true);
                info('$checkRC');
                info(json_encode($checkRC));

            }
        }

        return $this->return_success([
            'result' => KnownResponses::ok,
            'control' => $request->control,
            'version' => H::ver(),
            'owner_only' => config('sn.serve_owner_only'),
            'inbound_connectivity_check_result' => $checkRC ?? null,
        ]);
    }

    public static function peerHttpsStatus(string $ip, string $port): ResultContainer
    {
        info('peerHttpsStatus: '.$ip.' '.$port);
        $rc = new ResultContainer;
        $rc->operation_successful = true;
        $rc->data = KnownResponses::nok;

        try {
            request()->merge(['ip' => $ip, 'port' => $port]);
            request()->validate([
                'ip' => ['required', 'ip'],
                'port' => ['required', 'integer', 'between:1,65535'],
            ]);
        } catch (Throwable $th) {
            info($th->getMessage().' '.$th->getFile().' '.$th->getLine());

            $rc->operation_successful = false;

            return $rc;
        }
        $client_address = $ip.':'.$port;
        $success = null;
        info('peerHttpsStatus - Validation ok '.$ip.' '.$port);

        $control = Str::random(16);

        $protocols = ['https', 'http'];
        foreach ($protocols as $key => $protocol) {
            $url = "{$protocol}://{$client_address}/p2p_api/v1/check_peer";

            info('peerHttpsStatus '.$url);
            $client = H::setupClient(H::isTorAddress($client_address), long_connect: false);

            $peer_random_id = H::getSettVal(SettingIds::peer_random_id);
            $params = [
                'requestor_external_address' => H::getPublicSelfAddress(),
                'peer_random_id' => $peer_random_id,
                'control' => $control,
            ];
            $options = [
                'connect_timeout' => 3,
                'prepare_ip' => true,
                'form_params' => $params,
            ];
            H::prepareOptions($options, $url);

            try {
                $response = $client->request('POST', $url, $options);
                $body = (string) $response->getBody();

                $decodedBody = json_decode($body);

                $success = $decodedBody->success;
                $data = $decodedBody->data;

                if ($success == true && $data->result == KnownResponses::ok) {
                    $rc->data = $protocol;

                    return $rc;
                } elseif ($success == true && $data->result == KnownResponses::cyclic_occured) {
                    return $rc;
                }
            } catch (Throwable $th) {
                info($th->getMessage().' '.$th->getFile().' '.$th->getLine());
            }
        }

        $rc->data = 'https';

        return $rc;
    }

    public function refreshListsCache($pfm = false)
    {
        ListProviderEntry::where('is_manual_entry', false)->delete();

        $sitesWithLists = collect();
        $sitesWithLists = (new ListProviderService)->getBlockListSites();

        $enabledLists = json_decode(H::getSettVal(SettingIds::enabled_list_providers)) ?? [];

        foreach ($sitesWithLists as $key => $site) {
            $lists = collect(json_decode($site->most_recent_site_definition->site_config_json)->list_Providers);

            foreach ($lists as $key => $listEntry) {
                $listId = $listEntry->tag.'@'.$site->site_id;

                if (in_array($listId, $enabledLists)) {
                    $adminSignersRC = (new BackendController)->getListValuesFromProvider([], $listId);

                    if (! $adminSignersRC->operation_successful) {
                        continue;
                    }

                    $list = $adminSignersRC->data;

                    foreach ($list as $key => $content) {
                        $column_value = $content->{$listEntry->column_value};
                        $listProviderEntry = ListProviderEntry::where([
                            ['data_type', $listEntry->data_type],
                            ['content', $column_value],
                        ])->first();

                        if (! $listProviderEntry) {
                            $listProviderEntry = new ListProviderEntry;
                            $listProviderEntry->content = $column_value;
                            $listProviderEntry->data_type = $listEntry->data_type;
                            $listProviderEntry->save();
                        }
                    }
                }
            }
        }
    }

    /**
     * Is upload of resource chunk justified
     * - Is record crypto correct
     * - Is record consistent
     * - Is of the to be hosted site
     */
    protected static function isJustified($justification_context, $incomingRecordEnvelope): bool
    {
        $justification_record_json = $incomingRecordEnvelope->record_json;
        $incomingRecord = json_decode($justification_record_json);

        $site_id = $incomingRecord->site_id ?? $incomingRecord->_sn_site_id;

        $isToBeHostedSite = Site::whereSiteId($site_id)->where('is_to_be_hosted', true)->first();
        if (! $isToBeHostedSite) {
            return false;
        }

        switch ($justification_context) {
            case DataTypes::site_definitions:

                if (! (new PQCryptoService)->isCryptoCorrectVerKey(
                    $incomingRecordEnvelope->signer_verification_key_base64,
                    $incomingRecordEnvelope->record_json,
                    $incomingRecordEnvelope->signature,
                )) {
                    return false;
                }

                $isRecordConsistent = (new IntegrityService)->isRecordConsistent(
                    record_json: $justification_record_json,
                    isVisitors: false,
                    signer_verification_key_base64: $incomingRecordEnvelope->signer_verification_key_base64
                );

                if (! $isRecordConsistent) {
                    return false;
                }
                break;

            case DataTypes::visitor_resources:

                if (! (new PQCryptoService)->isCryptoCorrectVerKey(
                    $incomingRecordEnvelope->signer_verification_key_base64,
                    $incomingRecordEnvelope->record_json,
                    $incomingRecordEnvelope->signature,
                )) {
                    return false;
                }

                $isRecordConsistent = (new IntegrityService)->isRecordConsistent(
                    record_json: $justification_record_json,
                    isVisitors: true,
                    signer_verification_key_base64: $incomingRecordEnvelope->signer_verification_key_base64
                );

                if (! $isRecordConsistent) {
                    return false;
                }
                break;

            default:
                return false;
                break;
        }

        return true;
    }
}
