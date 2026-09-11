<?php

namespace App\Services;

use App\Dicts\CachePrefixes;
use App\Dicts\DataTypes;
use App\Dicts\SettingIds;
use App\Http\H;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ClientController;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AdminUiService extends Controller
{
    function destroyById(Request $request) 
    {
        $request->validate([
            'ids' => ['required', 'array'], 
            'scope' => ['required', 'string']
        ]);
        info('destroyById validated '.$request->scope.' '.json_encode($request->ids));

        $ids = $request->ids;
        $scope = $request->scope;

        try {
            switch ($scope) {    
                case DataTypes::cached_resources:
                    foreach ($ids as $key => $id) {
                        $cr = CachedResource::find($id);
                        if ($cr->ipfs_hash) {
                            $isIpfsAvailable = false;
                            $isIpfsReadOnly = H::getSettVal(SettingIds::ipfs_read_only);
                            if (! $isIpfsReadOnly) {
                                (new AdminController())->testIpfs();
                                $isIpfsAvailable = IPFSService::getIsIpfsAvailable();
                                if ($isIpfsAvailable) {
                                    $ipfs = IPFSService::getIpfsClient(H::getSettVal(SettingIds::ipfs_address), 5);
                                    $ipfs->unpin($cr->ipfs_hash);
                                }
                            }
                        }
                        Storage::disk('cached_resources')->delete($cr->file_name);
                        $cr->delete();
                    }
                break;
    
                case DataTypes::peers:
                    $peers = Peer::find($request->ids);
                    foreach ($peers as $key => $peer) {
                        $sitePeers = SitePeer::where('client_address', $peer->client_address)->get();
                        foreach ($sitePeers as $key => $sitePeer) {
                            $sitePeer->delete();
                        }
                        $peer->delete();
                    }
                break;
    
                case DataTypes::site_peers:
                    SitePeer::find($request->ids)->each(fn($o) => $o->delete());
                break;

                case DataTypes::trackers:
                    Tracker::find($request->ids)->each(fn($o) => $o->delete());
                break;
                
                case DataTypes::visitors:
                    Visitor::find($request->ids)->each(fn($o) => $o->delete());
                break;
    
                case DataTypes::site_definitions:
                    SiteDefinition::find($request->ids)->each(fn($o) => $o->delete());
                break;

                case DataTypes::internal_requests:
                    InternalRequest::find($request->ids)->each(fn($o) => $o->delete());
                break;

                case DataTypes::local_tracker_info_hash_peers:
                    LocalTrackerInfoHashPeer::find($request->ids)->each(fn($o) => $o->delete());
                break;

                case DataTypes::replication_sessions:
                    ReplicationSession::find($request->ids)->each(fn($o) => $o->delete());
                break;

                case DataTypes::peer_replication_sessions:
                    PeerReplicationSession::find($request->ids)->each(fn($o) => $o->delete());
                break;
                
                default:
                    return $this->return_success();
                break;
            }
        } catch (\Throwable $th) {

            return $this->return_failure($th->getMessage());
        }

        return $this->return_success('deleted');
    }

    public function saveEnabledLists(Request $request)
    {
        $lists = collect($request->input());
        $lists = $lists->except('_token');
        H::setSettingValue(SettingIds::enabled_list_providers, $lists->keys());

        (new ClientController)->refreshListsCache();

        return redirect()->back()->with('status', 'Saved');
    }

    public function setAppUrl(Request $request)
    {
        $request->validate([
            'app_url' => ['required', 'url'],
        ]);

        $app_url = $request->app_url;

        Artisan::call("env:set APP_URL $app_url");
        Artisan::call("config:clear");

        Artisan::call("config:cache");

        return redirect()->back()->withFragment('app-url')
        ->with('status', 'Saved!');
    }

    public function setUiAddresses(Request $request)
    {
        Log::debug('setUiAddresses');
        $request->validate([
            'ui_addresses' => ['required', 'max:101024'],
        ]);

        $ui_addresses = $request->ui_addresses;
        $ui_address_file_path = storage_path('app/ui_addresses.txt');
        File::put($ui_address_file_path, $ui_addresses);

        Cache::forget(CachePrefixes::ui_addresses);

        return redirect()->back()->withFragment('ui-addresses')
        ->with('status', 'Saved');
    }

    public function downloadLog(Request $request)
    {
        return response()->download(storage_path('logs/laravel.log'));
    }

    public function checkInternalCall(Request $request)
    {
        $request->validate([
            'app_url' => ['required', 'url'],
        ]);

        $app_url = $request->app_url;

        $internalTestAddress = H::a($app_url).'internal/handle_internal_call_verification';
        $url = $internalTestAddress;
        $client = H::setupClient(H::isTorAddress($url));
        $requestConfigSet = [];
        $requestConfigSet['empty_payload'] = 'empty_payload';
        $requestConfigSet['ts'] = now()->toDateTimeString();

        try {
            $options = [
                'timeout' => H::timeoutAdjust(H::isTorAddress($internalTestAddress), 10),
                'form_params' => ['requestConfigSet' => encrypt(json_encode($requestConfigSet)) ],
            ];
            H::prepareOptions($options, $url);
            $response = $client->post($url, $options);
    
            $response = json_decode((string) $response->getBody());
            $ts = Carbon::parse($response->data->ts); 
            $success = $response->success == true && $ts < now() && $ts->diffInMinutes(now()) < 2;
            $resultMessage = 'Tested: ' . ($success ? 'Successfully connected internally' : 'Failure');
        } catch (\Throwable $th) {
            $resultMessage = 'Tested: Failure. '. $th->getMessage();
        }

        return redirect()->back()->withFragment('app-url')
        ->with('status', $resultMessage);
    }

    public function updatePublicAddress(Request $request)
    {
        $request->validate([
            'publicSelfAddress' => ['required_if:isPublicSelfAddressOverriddenInput,on', 'url'],
        ]);

        $publicSelfAddress = $request->publicSelfAddress;

        $isPublicSelfAddressOverridden = (bool) $request->isPublicSelfAddressOverriddenInput;

        // dd($isPublicSelfAddressOverridden, $publicSelfAddress);

        H::setSettingValue(SettingIds::is_public_self_address_overridden, $isPublicSelfAddressOverridden);
        H::setSettingValue(SettingIds::static_public_self_address, H::a($publicSelfAddress));
  
        return redirect()->back()->with('success', 'Modified')->withFragment('update_public_address');
    }

    
}