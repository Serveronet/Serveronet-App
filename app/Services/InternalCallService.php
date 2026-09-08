<?php

namespace App\Services;

use App\Dicts\CachePrefixes;
use App\Dicts\DataTypes;
use App\Dicts\SettingIds;
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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Laravel\SerializableClosure\SerializableClosure;

class InternalCallService extends Controller
{
    function handleInternalClosure(Request $request): void 
    {
        $requestConfigSet = H::unwrapRequestConfigSet();

        if (! H::isInternalCallCurrent($requestConfigSet)) {
            info('Outdated or spoofed internal request');
            return;
        }
        $internalRequestId = H::startInteralRequestReporting(__FUNCTION__);

        $serialized = $requestConfigSet['serialized_closure'];

        $secret_key_internal_calls = H::getSettVal(SettingIds::secret_key_internal_calls);
        SerializableClosure::setSecretKey($secret_key_internal_calls);

        $closure = unserialize($serialized)->getClosure();

        $closure();
        
        H::endInteralRequestReporting($internalRequestId);
    }
}