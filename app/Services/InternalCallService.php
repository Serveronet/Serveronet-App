<?php

namespace App\Services;

use App\Dicts\CachePrefixes;
use App\Dicts\DataTypes;
use App\Dicts\SettingIds;
use App\Http\Consts;
use App\Http\H;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Controller;
use App\Models\CachedResource;
use App\Models\InternalRequest;
use App\Models\LocalTrackerInfoHashPeer;
use App\Models\Peer;
use App\Models\PeerReplicationSession;
use App\Models\ReplicationSession;
use App\Models\SiteDefinition;
use App\Models\SitePeer;
use App\Models\Tracker;
use App\Models\Visitor;
use Carbon\Carbon;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Laravel\SerializableClosure\SerializableClosure;
use Throwable;
use Illuminate\Support\Str;

class InternalCallService extends Controller
{
    public function handleInternalClosure(Request $request): void 
    {
        $requestConfigSet = InternalCallService::unwrapRequestConfigSet();

        if (! InternalCallService::isInternalClosureCacheKeyPresent($requestConfigSet)) {
            return;
        }

        if (! InternalCallService::isInternalCallCurrent($requestConfigSet)) {
            return;
        }

        $internalRequestId = InternalCallService::startInteralRequestReporting(__FUNCTION__);
        
        $serialized = $requestConfigSet['serialized_closure'];

        $secret_key_internal_calls = H::getSettVal(SettingIds::secret_key_internal_calls);
        SerializableClosure::setSecretKey($secret_key_internal_calls);

        $closure = unserialize($serialized)->getClosure();

        $closure();
        
        InternalCallService::endInteralRequestReporting($internalRequestId);
    }

    public static function unwrapRequestConfigSet(): array
    {
        try {
            request()->validate([
                'requestConfigSet' => ['required', 'string', 'max:'.Consts::recordJsonMaxSizeBytes],
            ]);
        } catch (Throwable $th) {
            info('unwrapRequestConfigSet Validation Failed: '.$th->getMessage());
            abort(400);
        }

        try {
            $requestConfigSet = json_decode(decrypt(request()->requestConfigSet), true);
        } catch (Throwable $th) {
            info('unwrapRequestConfigSet Unwrap Failed: '.$th->getMessage());
            abort(400);
        }

        return $requestConfigSet;
    }

    /* Dispatches by route path after prefix internal/ */
    public static function dispatchInternalAsync($route_path, $requestConfigSet, $timeout = 0.15): void
    {
        info('dispatchInternalAsync '.$route_path);

        $internalClosureExecutionId = Str::random(40);
        Cache::put(CachePrefixes::internal_closure_.$internalClosureExecutionId, "", 10);
        $requestConfigSet['internalClosureExecutionId'] = $internalClosureExecutionId;

        $requestConfigSet['ts'] = now()->toDateTimeString();

        try {
            $internalUrl = H::a(config('app.url')).'internal/'.$route_path;
            info('$internalUrl '.$internalUrl);

            $client = H::setupClient(false);
            $options = [
                'timeout' => $timeout,
                'connect_timeout' => 1,
                'form_params' => ['requestConfigSet' => encrypt(json_encode($requestConfigSet))],
            ];
            H::prepareOptions($options, $internalUrl);

            $client->post($internalUrl, $options);

        } catch (ConnectException $e) {
            info('dispatchInternalAsync ConnectException '.$route_path.' '.Str::limit($e->getMessage(), 40));
        } catch (Throwable $th) {
            info('dispatchInternalAsync $th '.$th->getMessage().' '.$th->getFile().' '.$th->getLine());
        }
    }

    public static function dispatchInternalAsyncClosureWrapper($closure): void
    {
        info('dispatchInternalAsyncClosureWrapper');

        $secret_key_internal_calls = H::getSettVal(SettingIds::secret_key_internal_calls);
        SerializableClosure::setSecretKey($secret_key_internal_calls);

        $serialized_closure = serialize(new SerializableClosure($closure));
        
        $requestConfigSet = [];
        $requestConfigSet['serialized_closure'] = $serialized_closure;
        
        InternalCallService::dispatchInternalAsync('handle_internal_closure', $requestConfigSet);
    }

    public static function isInternalCallCurrent(array $requestConfigSet): bool
    {
        $ts = $requestConfigSet['ts'] ?? null;

        if (! $ts) {
            info('Internal request in missing a timestamp');
            return false;
        }

        $ts = Carbon::parse($ts);

        if ($ts->diffInSeconds(now()) > 10 || $ts->diffInSeconds(now()) < -10) {
            info('Internal request is not current');
            return false;
        }

        return true;
    }

    public static function isInternalClosureCacheKeyPresent($requestConfigSet): bool
    {
        $internalClosureExecutionId = $requestConfigSet['internalClosureExecutionId'] ?? null;
        if (! $internalClosureExecutionId)
            return false;

        if (! Cache::has(CachePrefixes::internal_closure_.$internalClosureExecutionId)) {
            info('No relevant cache key for this internal closure');
            return false;
        } else {
            Cache::forget(CachePrefixes::internal_closure_.$internalClosureExecutionId);
            return true;
        }
    }

    public static function startInteralRequestReporting($routeName): string
    {
        $internalRequestId = Str::random();
        $internalRequest = new InternalRequest;
        $internalRequest->route = $routeName;
        $internalRequest->request_id = $internalRequestId;
        $internalRequest->started_at = now();
        $internalRequest->save();

        return $internalRequestId;
    }

    public static function endInteralRequestReporting($internalRequestId): void
    {
        $internalRequest = InternalRequest::where('request_id', $internalRequestId)->first();
        if (! $internalRequest) {
            return;
        }

        $internalRequest->ended_at = now();
        $taken_seconds = Carbon::parse($internalRequest->started_at)->diffInSeconds(now());
        $internalRequest->taken_seconds = round($taken_seconds, 2);
        $internalRequest->save();

        $oldInternalRequests = InternalRequest::where('started_at', '<', now()->subDay())->take(1000);
        $oldInternalRequests->delete();
    }
}