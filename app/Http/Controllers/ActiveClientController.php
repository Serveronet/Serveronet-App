<?php

namespace App\Http\Controllers;

use App\Dicts\ActionTypes;
use App\Dicts\RecordStates;
use App\Http\H;
use App\Models\ActivesActionRequest;
use App\Models\CachedResource;
use App\Models\ContentRetrieval;
use App\Models\CrowdQuery;
use App\Models\P2pMessage;
use App\Models\PassiveSession;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ActiveClientController extends Controller
{
    protected function handlePassiveAwaitingRequests(Request $request) 
    {
        Log::debug('handlePassive passive_token: '.$request->passive_token);
        try {
            $request->validate([
                'passive_token' => ['required', 'string', 'max:1024'],
                'site_ids_json' => ['required', 'string', 'max:10024'],
            ]);
        } catch (\Throwable $th) {
            info('handlePassiveAwaitingRequests Validation error '.$th->getMessage());
            return $this->return_failure($th->getMessage());
        }
        $passive_token = $request->passive_token;
        Log::debug('handlePassiveAwaitingRequests $passive_token '.json_encode($passive_token));

        $passiveSession = PassiveSession::where('passive_token', $passive_token)->first();
        if (! $passiveSession)
        return $this->return_failure('No session for Passive Token');

        $site_ids = json_decode($request->site_ids_json);
        $startedAt = now();

        $seenRetrievalIds = [];
        $seenCrowdQueryIds = [];
        $seenMessageIds = [];

        return response()->stream(
            function() use ($seenRetrievalIds, $seenCrowdQueryIds, $seenMessageIds, $passive_token, $site_ids, $startedAt) {
                while (ob_get_level()) {
                    ob_end_clean();
                }

                if (connection_aborted()) {
                    return;
                }
                H::forceFlush();

                $i = 0;
                Log::debug('recurrCheck before begin');
                function recurrCheck($i, $seenRetrievalIds, $seenCrowdQueryIds, $seenMessageIds, $passive_token, $site_ids, $startedAt) {
                    $i++;
                    if ($i % 10 === 0)
                    Log::debug('recurrCheck: '.$i);

                    if (connection_aborted()) {
                        return;
                    }

                    if ($i % 20 === 0) {
                        echo str_pad('|', 4096, " ");
                        $passiveSession = PassiveSession::where('passive_token', $passive_token)->first();
                        Log::debug('active heartbeat should_stop: '.tfyn($passiveSession->should_stop));
                        if ($passiveSession->should_stop) {
                            $passiveSession->delete();
                            return;
                        }
                        $passiveSession->last_heartbeat_at = now();
                        $passiveSession->save();
                    }

                    foreach ($site_ids as $key => $site_id) {

                        if (ActiveClientController::checkPassiveTrigger($site_id, $passive_token)) {
                            Log::debug('trigger '.$site_id.' '.$passive_token);
                            ActiveClientController::echoActionRequests($site_id, $seenRetrievalIds, $seenCrowdQueryIds, $seenMessageIds, $passive_token);
                        }
                        
                    }

                    H::forceFlush();

                    sleep(1);

                    if ($startedAt->diffInSeconds(now()) < H::getMaxExecutionTime()-1) {
                        recurrCheck($i, $seenRetrievalIds, $seenCrowdQueryIds, $seenMessageIds, $passive_token, $site_ids, $startedAt);
                    } else {
                        return;
                    }
                }

                recurrCheck($i, $seenRetrievalIds, $seenCrowdQueryIds, $seenMessageIds, $passive_token, $site_ids, $startedAt);

                H::forceFlush();
            }
            , 200, 
            [
                'Cache-Control' => 'no-cache',
                'Content-Type' => 'text/plain' 
            ]
        );
    }

    public static function checkPassiveTrigger($site_id, $passive_token): bool 
    {
        $file_name = $site_id.'_'.$passive_token;
        if (Storage::disk('passive_triggers')->exists($file_name.'.txt')) {
            Log::debug('checkPassiveTrigger exists, deleting');
            Storage::disk('passive_triggers')->delete($file_name.'.txt');
            return true;
        }
        return false;
    }

    protected static function echoActionRequests($site_id, &$seenRetrievalIds, &$seenCrowdQueryIds, &$seenMessageIds, $passive_token): void 
    {
        $contentRetrievals = ContentRetrieval::where('site_id', $site_id)
        ->whereNotIn('retrieval_id', $seenRetrievalIds)
        ->whereNotNull('retrieval_id')
        ->whereNotIn('state', [RecordStates::retrieval_completed, RecordStates::retrieval_failed])
        ->where('created_at', '>', now()->subMinutes(60))
        ->select(ContentRetrieval::$publicProperties)
        ->get();

        $crowdQueries = CrowdQuery::where('site_id', $site_id)
        ->whereNotIn('query_id', $seenCrowdQueryIds)
        ->whereNotNull('query_id')
        ->whereNotIn('state', [RecordStates::query_completed, RecordStates::query_consumed, RecordStates::query_failed])
        ->where('created_at', '>', now()->subMinutes(1))
        ->select(CrowdQuery::$publicProperties)
        ->get();
        Log::debug('$crowdQueries count: '.count($crowdQueries));

        $p2pMessages = P2pMessage::where('site_id', $site_id)
        ->whereNotIn('message_id', $seenMessageIds)
        ->whereNotNull('message_id')
        ->where('created_at', '>', now()->subMinutes(1))
        ->select(P2pMessage::$publicProperties)
        ->get();

        $retrieval_ids = $contentRetrievals->pluck('retrieval_id')->toArray();
        $query_ids = $crowdQueries->pluck('query_id')->toArray();
        $message_ids = $p2pMessages->pluck('message_id')->toArray();

        foreach ($retrieval_ids as $key => $value) {
            array_push($seenRetrievalIds, $value);
        }
        foreach ($query_ids as $key => $value) {
            array_push($seenCrowdQueryIds, $value);
        }
        foreach ($message_ids as $key => $value) {
            array_push($seenMessageIds, $value);
        }

        $originator_peer_id = 'Active with token: '.$passive_token;

        $actionRequests = [];

        foreach ($contentRetrievals as $key => $retrieval) {
            $form_params = [
                'site_id' => $retrieval->site_id,
                'retrieval_id' => $retrieval->retrieval_id,
                'ttl' => $retrieval->ttl,
                'sha256' => $retrieval->sha256,
                'entity_id' => $retrieval->entity_id,
                'originator_peer_id' => $originator_peer_id,
                'action_type' => $retrieval->action_type,
            ];
            $actionRequest = new ActivesActionRequest();
            $actionRequest->type = ActionTypes::action_order_retrieval;
            $actionRequest->action_order_json = json_encode($form_params);
            array_push($actionRequests, $actionRequest);
        }
        foreach ($crowdQueries as $key => $crowdQuery) {
            $form_params = [
                'queryParameters' => $crowdQuery->query_parameters,
                'site_id' => $crowdQuery->site_id,
                'query_id' => $crowdQuery->query_id,
                'ttl' => $crowdQuery->ttl,
                'originator_peer_id' => $crowdQuery->originator_peer_id,
                'debug' => true,
            ];
            $actionRequest = new ActivesActionRequest();
            $actionRequest->type = ActionTypes::action_order_crowd_query;
            $actionRequest->action_order_json = json_encode($form_params);
            array_push($actionRequests, $actionRequest);
        }
        foreach ($p2pMessages as $key => $p2pMessage) {
            $form_params = [
                'message_json' => json_encode($p2pMessage),
            ];
            $actionRequest = new ActivesActionRequest();
            $actionRequest->type = ActionTypes::action_send_p2p_message;
            $actionRequest->action_order_json = json_encode($form_params);
            array_push($actionRequests, $actionRequest);
        }

        foreach ($actionRequests as $key => $actionRequest) {
            $actionRequestJson = json_encode($actionRequest);

            /* Echoing action requests */
            $output = hex2bin('02') . strlen($actionRequestJson) . hex2bin('03') . $actionRequestJson;
            echo str_pad($output, 4096, " ");
            echo " ";
        }
    }
    
    protected function handlePassiveClientInitiatingSession(Request $request) 
    {
        Log::debug('handlePassiveClientInitiatingSession start');

        try {
            $request->validate([
                'site_ids_json' => ['nullable', 'string', 'max:10024'],
                'client_address' => ['required', 'string', 'max:1024'],
                'passive_token' => ['nullable', 'alpha_dash:ascii', 'max:1024'],
            ]);
        } catch (\Throwable $th) {
            Log::debug($th->getMessage().' '.$th->getFile().' '.$th->getLine());
            return $this->return_failure($th->getMessage());
        }

        $outdatedPassiveSessions = PassiveSession::
        where('last_heartbeat_at', '<', now()->subMinutes(1)->toDateTimeString())->get();
        $outdatedPassiveSessions->each(function ($passiveSession, $key) {
            $passiveSession->delete();
        });

        $files = Storage::disk('passive_triggers')->allFiles();
        
        $cutoffTime = Carbon::now()->subMinute()->timestamp;
        $oldFiles = [];

        foreach ($files as $file) {
            $lastModified = Storage::disk('passive_triggers')->lastModified($file);
            if ($lastModified < $cutoffTime) {
                $oldFiles[] = $file;
            }
        }
        
        foreach ($oldFiles as $key => $name) {
            Storage::disk('passive_triggers')->delete($name);
        }

        $totalPassiveSessions = PassiveSession::get();
        if ($totalPassiveSessions->count() > 20)
        return $this->return_failure('Too many sessions already');

        $passive_client_address = $request->client_address;

        $passive_remote_addr = $_SERVER['REMOTE_ADDR']; 

        $site_ids = json_decode($request->site_ids_json) ?? [];

        $passive_token = $request->passive_token ?? Str::random();
        Log::debug('passive_token '.json_encode($passive_token));
        Log::debug('handlePassiveClientInitiatingSession passive_token: '.$passive_token.' '.$passive_remote_addr.' '.$passive_client_address);

        $passiveSession = PassiveSession::where('passive_token', $passive_token)->first();
        
        if (! $passiveSession)
        $passiveSession = new PassiveSession();
        
        $passiveSession->passive_token = $passive_token;
        $passiveSession->remote_ip = $passive_remote_addr;
        $passiveSession->passive_client_address = $passive_client_address;
        $passiveSession->last_heartbeat_at = now();
        $passiveSession->site_ids_json = json_encode($site_ids);
        $passiveSession->save();

        $max_execution_time = H::getMaxExecutionTime();
        Log::debug('handlePassiveClientInitiatingSession max_execution_time: '.$max_execution_time);
        return $this->return_success(['passive_token' => $passive_token, 'max_execution_time' => $max_execution_time]);
    }
}
