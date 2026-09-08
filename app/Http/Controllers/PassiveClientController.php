<?php

namespace App\Http\Controllers;

use App\Dicts\ActionTypes;
use App\Dicts\SettingIds;
use App\Http\H;
use App\Models\CrowdQueryResult;
use App\Models\Peer;
use App\Models\ResultContainer;
use App\Models\Site;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PassiveClientController extends Controller
{
    public function establishPassiveSessions($pfm = false): void 
    {      
        Peer::query()->update([
            'is_passive_client' => false,
        ]);

        $inboundBlockedConnectablePeers = Peer::withoutSelf()
        ->where('last_connection_check_at', '>', now()->subMinutes(60))
        ->whereNotNull('last_connection_check_at')
        ->where('last_inbound_connection_check_at', '>', now()->subMinutes(60))
        ->whereNull('last_inbound_connected_at')
        ->where(function (Builder $query) { 
            $query->where('passive_failures_count', '<', 5)
            ->orWhereNull('passive_failures_count');
        })
        ->where(function (Builder $query) { 
            $query->where('last_heartbeat_at', '<', now()->subMinutes(1))
            ->orWhereNull('last_heartbeat_at');
        })
        ->take(5)->get();
        
        H::pfm('$inboundBlockedConnectablePeers:', log: true, pfm: $pfm);
        H::pfm(json_encode($inboundBlockedConnectablePeers), log: true, pfm: $pfm);

        foreach ($inboundBlockedConnectablePeers as $key => $peer) {
            $peer->is_passive_client = true;
            $peer->active_token = null;
            $peer->save();
        }

        foreach ($inboundBlockedConnectablePeers as $key => $peer) {
            $passive_token = null;
            $active_client_address = $peer->client_address;
            $startedAt = null;
            if (! $startedAt) $startedAt = now()->toDateTimeString();

            $requestConfigSet = [];
            $requestConfigSet['passive_token'] = $passive_token;
            $requestConfigSet['active_client_address'] = $active_client_address;
            $requestConfigSet['startedAt'] = $startedAt;
            $requestConfigSet['from_browser'] = false;

            H::dispatchInternalAsync('passive_session_worker', $requestConfigSet);
        }
    }

    public function passiveSessionWorker(Request $request) 
    {
        $requestConfigSet = H::unwrapRequestConfigSet();
        if (! H::isInternalCallCurrent($requestConfigSet)) {
            info('Outdated or spoofed internal request');
            return;
        }
        $internalRequestId = H::startInteralRequestReporting(__FUNCTION__);

        $passive_token = $requestConfigSet['passive_token'];
        $active_client_address = $requestConfigSet['active_client_address'];
        $startedAt = $requestConfigSet['startedAt'] ?? now()->toDateTimeString();
        $from_browser = $requestConfigSet['from_browser'];
        $pfm = $requestConfigSet['pfm'] ?? false;

        H::pfm('passiveSessionWorker begin', pfm: $pfm);
        
        info('passiveSessionWorker begin');
        info('from_browser: '.tfyn($from_browser));

        if (! $active_client_address)
        throw new Exception("No active_client_address", 1);
        
        H::pfm('active_client_address: '.$active_client_address, pfm: $pfm);

        $currentSessionStartedAt = now()->toDateTimeString();
        
        $diff = Carbon::parse($startedAt)->diffInMinutes(now());
        $maxPassiveSessionDurationMinutes = H::getSettVal(SettingIds::max_duration_passive_session_minutes);
        info('Session Time (m): '.round($diff, 2).' / '.$maxPassiveSessionDurationMinutes.' | Token: '.$passive_token);

        if ($diff > $maxPassiveSessionDurationMinutes) {
            info('startedAt too long ');
            $activePeer = Peer::where('client_address', $active_client_address)->first();
            $activePeer->active_token = null;
            $activePeer->last_heartbeat_at = null;
            $activePeer->save();
            return;
        }

        $hostedSites = Site::where([['is_hosted', true],])->select('site_id')->take(1000)->get(); 
        $site_ids = $hostedSites->pluck('site_id')->toArray();

        $publishRC = $this->initializePassiveSession($passive_token, $active_client_address, $site_ids, pfm: $pfm);

        if (! $publishRC->operation_successful) {
            H::pfm('Publishing Hosted Sites and getting passive_token failed. '.$publishRC->error_message, pfm: $pfm, log: true);
            return;
        }

        $passive_token = $publishRC->data->passive_token;
        $remote_max_execution_time = $publishRC->data->max_execution_time;
        $local_max_execution_time = H::getMaxExecutionTime();
        
        if ($remote_max_execution_time == 0) $remote_max_execution_time = 30;

        $max_execution_time = min(60, $remote_max_execution_time, $local_max_execution_time)-2;

        $message = '';
        $i = 0;
        $receivingLenghtMode = false;
        $expectedLengthString = '';
        $expectedLength = 0;
        $transmissionOngoing = false;

        $client = H::setupClient(false);
        info('$site_ids');
        info(json_encode($site_ids));
        $form_params = [        
            'client_address' => H::getAppUrl(),
            'passive_token' => $passive_token,
            'site_ids_json' => json_encode($site_ids),
        ];
        $options = [
            'timeout' => (int)$max_execution_time,
            'read_timeout' => (int)$max_execution_time,
            'form_params' => $form_params,
            'stream' => true,
            'prepare_ip' => true,
        ];

        $url = $active_client_address.'p2p_api/v1/handle_passive_awaiting_requests';

        H::prepareOptions($options, $url);

        info('before while loop '.$url);

        try {
            $response = $client->post($url, $options);

            $body = $response->getBody();
            while (ob_get_level()) {
                ob_end_flush();
            }

            while (! $body->eof()) {
                if (connection_aborted()) {
                    info('Passive detetected connection_aborted - exiting');
                    return;
                }
                $b = $body->read(1);

                $diffSeconds = Carbon::parse($currentSessionStartedAt)->diffInSeconds(now());

                if ($diffSeconds > $max_execution_time)
                throw new Exception('diffSeconds reached - ending session');

                // info('$b');
                // info($b);
                
                if ($b === hex2bin('02') && ! $receivingLenghtMode) {
                    $expectedLength = 0;
                    $receivingLenghtMode = true;
                    $expectedLengthString = '';
                    continue;
                }
                if ($receivingLenghtMode && $b !== hex2bin('03')) {
                    $expectedLengthString .=  $b;
                    continue;
                }
                if ($receivingLenghtMode && $b === hex2bin('03')) {
                    $receivingLenghtMode = false;
                    $expectedLength = (int)$expectedLengthString;
                    $message = '';
                    $transmissionOngoing = true;
                    continue;
                }
                if ($i < $expectedLength && ! $receivingLenghtMode && $transmissionOngoing) {
                    $i++;
                    $message .= $b;
                    continue;
                }
                if ($i == $expectedLength && ! $receivingLenghtMode && $transmissionOngoing) {
                    $i = 0;
                    $this->handleOrderFromActive($request, $message, $active_client_address, pfm: $pfm);
                    $message = '';
                    $transmissionOngoing = false;
                }
            }
        } catch (\Throwable $th) {
            info('catch_passiveSessionWorker $th '.$th->getMessage().' '.$th->getFile().' '.$th->getLine());
        }

        H::endInteralRequestReporting($internalRequestId);

        $diffSeconds = Carbon::parse($currentSessionStartedAt)->diffInSeconds(now());
        if ($diffSeconds < 1) {
            $activePeer = Peer::where('client_address', $active_client_address)->first();
            $activePeer->active_token = null;
            $activePeer->last_heartbeat_at = null;
            $activePeer->passive_failures_count++;
            $activePeer->save();
            return;
        }
   
        H::pfm('passiveSessionWorker end - new internal request', pfm: $pfm, log: true);
        info('passiveSessionWorker end - new internal request');
        
        if (! $startedAt) $startedAt = now()->toDateTimeString();
        
        $requestConfigSet = [];
        $requestConfigSet['passive_token'] = $passive_token;
        $requestConfigSet['active_client_address'] = $active_client_address;
        $requestConfigSet['startedAt'] = $startedAt;
        $requestConfigSet['from_browser'] = false;

        H::dispatchInternalAsync('passive_session_worker', $requestConfigSet);
    }

    protected function initializePassiveSession($passive_token, $active_client_address, $site_ids, $pfm = false): ResultContainer 
    {
        info('initializePassiveSession');
        $rc = new ResultContainer();
        $rc->operation_successful = false;

        $client = H::setupClient(false);
        $form_params  = [        
            'client_address' => H::getAppUrl(),
            'site_ids_json' => json_encode($site_ids), 
            'passive_token' => $passive_token,
        ];

        $url = $active_client_address.'p2p_api/v1/handle_passive_client_initiating_session';
        
        $options = [
            'timeout' => 10,
            'read_timeout' => 10,
            'stream' => true,
            'form_params' => $form_params,
            'prepare_ip' => true,
        ];

        H::prepareOptions($options, $url);

        try {
            $initialResponse = $client->post($url, $options);
        } catch (\Throwable $th) {
            H::pfm('Error when requesting token: '.$th->getMessage(), pfm: $pfm);
            info('Error when requesting token: '.$th->getMessage());
            $rc->error_message = 'Error when requesting token: '.$th->getMessage();
            return $rc;
        }
        
        /* Testing Streaming capabilites  - as it will be required */
        $body = $initialResponse->getBody();
        $message = '';
        
        while (! $body->eof()) {
            $b = $body->read(1);
            $message .= $b;
        }
        $decodedBody = json_decode($message);

        if ($decodedBody->success) {
            $data = $decodedBody->data;
            $passive_token = $data->passive_token;
            
            $request = new Request();
            
            $request->merge(['passive_token' => $passive_token]);
            try {
                $request->validate([
                    'passive_token' => ['required', 'alpha_dash:ascii', 'max:1024', 'min:16'],
                ]);
            } catch (\Throwable $th) {
                info($th->getMessage().' '.$th->getFile().' '.$th->getLine());
                $rc->error_message = 'Passive_token received is not valid';
                return $rc;
            }

            $rc->operation_successful = true;
            $rc->data = $data;
            
            $activePeer = Peer::where('client_address', $active_client_address)->first();
            $activePeer->active_token = $passive_token;
            $activePeer->last_heartbeat_at = now();
            $activePeer->save();
            info('initializePassiveSession active_client_address: '.$active_client_address.' passive_token: '.$passive_token);
            H::pfm('Pasive token from active peer: '.$passive_token, pfm: $pfm);

            return $rc;
        } else {
            H::pfm('Error from active: '.$decodedBody->message, pfm: $pfm);
            info('Error: '.$decodedBody->message);
            $rc->error_message = $decodedBody->message;
            return $rc;
        }
        return $rc;
    }
    
    protected function handleOrderFromActive($request, $message, $active_client_address, $pfm): void 
    {
        $action_order_json = $message;
        info('$action_order_json');
        info($action_order_json);

        if ($action_order_json === '' || !$action_order_json) {
            info('$action_order_json is empty or null');
            return;
        }

        info('$action_order_json has data');
        $action_order = json_decode($action_order_json);
        $type = $action_order->type;
        H::pfm('type: '.$type.' '.Str::limit($action_order_json, 140), pfm: $pfm);
        
        $requestConfigSet = [];
        $requestConfigSet['action_order_json'] = $action_order->action_order_json;
        $requestConfigSet['type'] = $type;
        $requestConfigSet['active_client_address'] = $active_client_address;

        H::dispatchInternalAsync('response_to_actives_action', $requestConfigSet);
    }

    public function responseToActivesAction() 
    {
        info('responseToActivesAction start');
        $requestConfigSet = H::unwrapRequestConfigSet();

        if (! H::isInternalCallCurrent($requestConfigSet)) {
            info('Outdated or spoofed internal request');
            return;
        }

        $internalRequestId = H::startInteralRequestReporting(__FUNCTION__);

        $action_order_json = $requestConfigSet['action_order_json'];
        $type = $requestConfigSet['type'];
        $active_client_address = $requestConfigSet['active_client_address'];
        // $request = request();
        info('responseToActivesAction start');
        $action_order = json_decode($action_order_json);     
        info('responseToActivesAction $type: '.$type);
        $endpoint = '';
        $output_form_params = [];
        switch ($type) {
            case ActionTypes::action_order_crowd_query :

                $request = new Request();
                $q = new \stdClass();
                $q->query_parameters = $action_order->queryParameters;
                $j = json_encode($q);
                $request->setMethod('POST');
                $request->merge(json_decode($j, true));
                $request->merge(['site_id' => $action_order->site_id]);

                $request->merge(['envelope_only' => true]);
                $request->merge(['response_as_result_container' => false]);
                $rc = (new QueryController)->queryEndpoint($request);
 
                if (! $rc->operation_successful) {
                    info('responseToActivesAction queryEndpoint failed');
                    return;
                }

                $crowdQueryResult = new CrowdQueryResult();
                $crowdQueryResult->query_id = $action_order->query_id;
                $crowdQueryResult->fulfiller_id = 'Passive Peer by order_crowd_query '.H::getAppUrl();
                $crowdQueryResult->remote_peer_id = H::getSettVal(SettingIds::peer_random_id);
                $crowdQueryResult->payload = $rc->data;

                info('responseToActivesAction action_order_crowd_query '.$action_order->query_id);
                $output_form_params = [
                    'data' => json_encode($crowdQueryResult),
                ];
                $endpoint = 'p2p_api/v1/receive_fulfilled_crowd_query';

            break;
            
            case ActionTypes::action_order_retrieval :

                $retrieval = $action_order;
                $request = new Request();
                $request->merge([
                    'sha256' => $retrieval->sha256,
                    'site_id' => $retrieval->site_id,
                    'action_type' => $retrieval->action_type,
                    'retrieval_id' => $retrieval->retrieval_id,
                ]);
        
                $responseWithData = (new ClientController())->handlePeerRequestingData($request);

                $dataRequestingResult = $responseWithData->getData();
                if (! $dataRequestingResult->success) {
                    info('dataRequestingResult Failure '.$dataRequestingResult->message);
                    return;
                }

                $output_form_params = [
                    'data' => json_encode($dataRequestingResult->data),
                    'fulfiller_id' => 'fulfiller_id: passive',
                ];
                info('responseToActivesAction action_order_retrieval '.$retrieval->retrieval_id);
                $endpoint = 'p2p_api/v1/receive_fulfilled_retrieval';

            break;

            case ActionTypes::action_send_p2p_message :

                $request = new Request();
                $request->merge([
                    'message_json' => $action_order->message_json,
                ]);
                (new ClientController())->processNewMessage($request);
                return;

            break;
        }
        
        $url = $active_client_address.$endpoint;

        $client = H::setupClient(false);
        $options = [
            'timeout' => 10,
            'form_params' => $output_form_params,
            'prepare_ip' => true,
        ];
        H::prepareOptions($options, $url);
        $response = $client->post($url, $options);

        $body = (string) $response->getBody();

        H::endInteralRequestReporting($internalRequestId);

        return $this->return_success($body);
    }
}
