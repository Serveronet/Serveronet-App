<?php

namespace App\Http\Controllers;

use App\Dicts\CachePrefixes;
use App\Dicts\DataTypes;
use App\Dicts\ListProviderTypes;
use App\Http\Consts;
use App\Http\H;
use App\Dicts\MessageTypes;
use App\Dicts\ReplicationSessionStates;
use App\Dicts\SettingIds;
use App\Dicts\SysProps;
use App\Dicts\Tags;
use App\Models\CachedDomain;
use App\Models\ListProviderEntry;
use App\Models\Site;
use App\Models\SiteDefinition;
use App\Models\SitePeer;
use App\Models\VisitorRecord;
use App\Models\VisitorResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\BackgroundProcessingController;
use App\Models\LocalTrackerInfoHashPeer;
use App\Models\PeerReplicationSession;
use App\Models\ReplicationSession;
use App\Models\ResultContainer;
use App\Services\PeerMixService;
use App\Services\PermissionService;
use App\Services\SiteConfigService;
use App\Services\SiteDatabaseService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SiteManagerController extends Controller
{
    public function siteStateEdit(Request $request): ResultContainer
    {
        $rc = new ResultContainer();
        $rc->operation_successful = true;
        try {
            $site_id = $request->site_id;
            $ban_reason = $request->ban_reason;
            $scope = $request->scope;
            $site = Site::whereSiteId($site_id)->with('list_entries')->first();
    
            switch ($scope) {
                case Tags::banning: 

                    $is_banned = ! $site->list_entries->sum('is_manual_entry') > 0;
                    if ($is_banned == true) {
                        $listProviderEntry = ListProviderEntry::where([
                            ['data_type', ListProviderTypes::banned_sites],
                            ['content', $site_id],
                            ['is_manual_entry', true],
                        ])->first();
                        if (! $listProviderEntry) {
                            $listProviderEntry =  new ListProviderEntry();
                            $listProviderEntry->content = $site_id;
                            $listProviderEntry->data_type = ListProviderTypes::banned_sites;
                            $listProviderEntry->is_manual_entry = true;
                            $listProviderEntry->reason = $ban_reason;
                            $listProviderEntry->save();
                        }
                        
                        $site->is_to_be_hosted = false;
                        $site->ban_reason = $ban_reason;
                        $site->save();
                    } elseif ($is_banned == false) {
                        $listProviderEntry = ListProviderEntry::where([
                            ['data_type', ListProviderTypes::banned_sites],
                            ['content', $site_id],
                            ['is_manual_entry', true],
                        ])->delete();
                    }
                    
                break;

                case Tags::hosting:

                    $is_to_be_hosted = $site->is_to_be_hosted;
                    if ($is_to_be_hosted == true) {

                        $listProviderEntry = ListProviderEntry::where([
                            ['data_type', ListProviderTypes::banned_sites],
                            ['content', $site_id],
                            ['is_manual_entry', true],
                        ])->delete();
    
                        $site->is_hosted = false;
                        $site->is_to_be_hosted = false;
    
                    } elseif ($is_to_be_hosted == false) {
    
                        $site->is_to_be_hosted = true;
                                            
                    }
                    $site->save();

                break;
    
                case Tags::pinning:

                    $is_pinned = ! $site->is_pinned;
                    $site->is_pinned = $is_pinned;
                    $site->save();

                break;
    
                case Tags::site_deleting:

                    $rcPurge = $this->purgeSite($request);
                    if (! $rcPurge->operation_successful) {
                        return $rcPurge;
                    } else {
                        $rc->operation_successful = false;
                        $rc->error_message = 'Site Purged';
                        $rc->data = 'Site Purged.';
                        return $rc;
                    }
    
                break;
    
                case Tags::site_updating:

                    $this->updateSite($request);

                    $rc->operation_successful = false;
                    $rc->error_message = 'Update is scheduled';
                    $rc->data = 'Updating will be conducted in the backgroud.';
                    return $rc;

                break;
    
                case Tags::site_republish:

                    $request->validate(['site_id' => Consts::siteIdValidationRule]);
                    $site_id = $request->site_id;

                    $closure = function() use ($site_id) {
                        (new SiteManagerController())->republishSite($site_id);
                    };

                    H::dispatchInternalAsyncClosureWrapper($closure);
                    info('Completed site_republish');

                break;

                case Tags::debug_toggle_site_hosted:

                    $site = Site::whereSiteId($site_id)->first();
                    $site->is_hosted = ! $site->is_hosted;
                    $site->save();

                break;
    
                case Tags::site_reset_visitor_data_replication_state:
                    $this->resetVisitorDataReplicationState($request);
                break;
    

                case Tags::site_recreate_db:
                    (new SiteDatabaseService)->recreateSiteDb($request);
                    return $rc;
                break;
    
                case Tags::site_announce:
                    $rc->data = (new TorrentTrackersController())->announceOrGetPeers($site_id, $site->is_hosted);
                break;               

                default:

                break;
            }


        } catch (\Throwable $th) {
            $rc->operation_successful = false;
            $rc->error_message = $th->getMessage();
            $rc->data = $th->getMessage().' '.$th->getFile().' '.$th->getLine();
            return $rc;
        }
        
        return $rc;
    }

    public function purgeSite(Request $request): ResultContainer 
    {
        $request->validate(['site_id' => Consts::siteIdValidationRule]);
        $site_id = $request->site_id;

        $rc = new ResultContainer();
        $rc->operation_successful = true;

        $enabledLists = json_decode(H::getSettVal(SettingIds::enabled_list_providers)) ?? [];

        foreach ($enabledLists as $key => $listId) {
            $listId = explode('@', $listId);
            $list_provider_site_address = $listId[1];
            if ($list_provider_site_address === $site_id) {
                $rc->operation_successful = false;
                $rc->error_message = 'Can\'t delete as is in use';
                $rc->data = 'Site used as an enabled List Provider. Disable in Control Panel before deleting.';
                return $rc;
            }
        }

        CachedDomain::where([
            ['site_id', $site_id],
            ['is_persistent', 0],
        ])->delete(); 

        $site = Site::whereSiteId($site_id)->first();

        if ($site->is_hosted)
        (new TorrentTrackersController())->announceOrGetPeers(site_id: $site_id, is_hosting: false, returnPeersAsap: false);

        SiteDefinition::whereSiteId($site_id)->delete();

        SitePeer::whereSiteId($site_id)->delete();

        $site->delete();
        
        VisitorRecord::withTrashed()->where('site_id', $site_id)->forceDelete();

        VisitorResource::withTrashed()->where('site_id', $site_id)->forceDelete();

        $dbFile = 'sites_databases/'.$site_id.'/'.$site_id.'.sqlite';

        if (Storage::disk('local')->exists($dbFile)) {
            Storage::disk('local')->delete($dbFile);
            Storage::disk('local')->deleteDirectory('sites_databases/'.$site_id);
        }

        $info_hash = sha1($site_id);

        $siteHashPeers = LocalTrackerInfoHashPeer::where('info_hash', $info_hash)->get();
        foreach ($siteHashPeers as $key => $siteHashPeer) {
            $siteHashPeer->delete();
        }

        Cache::forget(CachePrefixes::sd_entity_created_.$site_id);
        Cache::forget(CachePrefixes::site_.$site_id);
        Cache::forget(CachePrefixes::site_with_most_recent_site_definition_.$site_id);
        Cache::forget(CachePrefixes::banned_sites_.$site_id);
        Cache::forget(CachePrefixes::site_config_.$site_id);
        Cache::forget(CachePrefixes::site_peer_client_address_.$site_id);
        Cache::forget(CachePrefixes::domain_.$site_id);

        $rc->operation_successful = true;
        $rc->error_message = 'Deleted';
        $rc->data = 'Site Deleted.';

        return $rc;
    }

    public function updateSite(Request $request)
    {
        $request->validate(['site_id' => Consts::siteIdValidationRule]);
        $site_id = $request->site_id;
        $closure = function() use ($site_id) {
            (new BackgroundProcessingController())->hostedSiteInitHostingActions($site_id);
        };

        H::dispatchInternalAsyncClosureWrapper($closure);
    }

    /* Re-Publish of visitor records and network records functionality - for offline clients */
    function republishSite(string $site_id) 
    {
        $site = Site::whereSiteId($site_id)->with('most_recent_site_definition')->first();

        H::enqueueP2pMessage(
            MessageTypes::site_definition,
            payload: $site->most_recent_site_definition->only('record_json', 'signature', 'signer_verification_key_base64'),
            site_id: $site_id,
            priority: 110,
        );

        $siteDefinition = SiteDefinition::find($site->most_recent_site_definition)->first();
        $siteDefinition->is_pending_distribution = true;
        $siteDefinition->save();

        $visitorRecords = VisitorRecord::where([
            ['site_id', $site->site_id],
        ])->withTrashed()
        ->with('grant_record')
        ->select('record_json', 'signature', 'grantee_visitor_id', 'visitor_id')
        ->get();

        foreach ($visitorRecords as $key => $visitorRecord) {
            H::enqueueP2pMessage(
                MessageTypes::visitor_record, 
                payload: $visitorRecord->only('record_json', 'signature', 'grantee_visitor_id', 'visitor_id', 'signer_verification_key_base64'),
                site_id: $site_id,
                priority: 120,
                andSend: false,
            );
        }

        $visitorResources = VisitorResource::where([
            ['site_id', $site->site_id],
        ])->withTrashed()
        ->with('grant_record')
        ->select('record_json', 'signature', 'grantee_visitor_id', 'visitor_id')
        ->get();

        foreach ($visitorResources as $key => $visitorResource) {
            H::enqueueP2pMessage(
                MessageTypes::visitor_resource, 
                payload: $visitorResource->only('record_json', 'signature', 'grantee_visitor_id', 'visitor_id', 'signer_verification_key_base64'),
                site_id: $site_id,
                priority: 130,
                andSend: false,
            );
        }

        BackgroundProcessingController::sendPendingMessages($site_id);

        PublishSitesController::processSiteDefinitionsPendingDistribution($site_id);
        
        (new PublishSitesController())->prepareResourcesRepublish($site_id);

        BackgroundProcessingController::processPendingActions($site_id);
    }

    function resetVisitorDataReplicationState(Request $request)
    {
        $request->validate(['site_id' => Consts::siteIdValidationRule]);
        $site_id = $request->site_id;
        $site = Site::whereSiteId($site_id)->first();
        $site->visitor_records_replication_end_ts = Consts::startOfServeronet;
        $site->visitor_resources_replication_end_ts = Consts::startOfServeronet;
        $site->save();

        $replicationSessions = ReplicationSession::where('site_id', $site_id)->get();
        foreach ($replicationSessions as $key => $replicationSession) {
            $peerReplicationSessions = PeerReplicationSession::where('session_id', $replicationSession->session_id)->get();
            foreach ($peerReplicationSessions as $key => $peerReplicationSession) {
                $peerReplicationSession->delete();
            }
            $replicationSession->delete();
        }
    }

    public function showSiteHostingOverview(Request $request)
    {
        $request->merge(['site_id' => $request->site_id]);
        try {
            $request->validate([
                'site_id' => Consts::siteIdValidationRule
            ]);
        } catch (\Throwable $th) {
            return $this->return_failure($th->getMessage());
        }
        $site_id = $request->site_id; 

        return view('site_hosting_overview', compact('site_id'));
    }

    public function siteVisitorResourcesOverview(Request $request)
    {
        $request->merge(['site_id' => $request->site_id]);
        try {
            $request->validate([
                'site_id' => Consts::siteIdValidationRule
            ]);
        } catch (\Throwable $th) {
            return $this->return_failure($th->getMessage());
        }
        $site_id = $request->site_id;
        
        $site = Site::whereSiteId($site_id)->with('most_recent_site_definition')->first();

        return view('site_visitor_resources_overview', compact('site'));
    }

    public function showSitePeersOverview(Request $request)
    {
        $request->validate(['site_id' => Consts::siteIdValidationRule]);
        $site_id = $request->site_id;

        $site = Site::whereSiteId($site_id)
        ->with('most_recent_site_definition')->first();

        $sitePeers = SitePeer::whereSiteId($site_id)->get();

        $peerMix = (new PeerMixService)->getPeerMix(site_id: $site_id);

        return view('site_peers_overview', compact('site', 'sitePeers', 'peerMix'));
    }

    public function showSiteStatusOverview(Request $request)
    {
        $request->validate(['site_id' => Consts::siteIdValidationRule]);
        $site_id = $request->site_id;

        $site = Site::whereSiteId($site_id)->with('most_recent_site_definition')->first();
        $siteConfig = (new SiteConfigService)->getSiteConfig($site);
        $siteConfigJson = json_encode($siteConfig, JSON_PRETTY_PRINT);
        
        $replicationCodesMap = [
            'SD' => DataTypes::site_definitions,
            'SP' => DataTypes::site_peers,
            'V-Rec' => DataTypes::visitor_records,
            'V-Res' => DataTypes::visitor_resources,
        ];

        foreach ($replicationCodesMap as $key => $data_type) {
            $replicationSession = ReplicationSession::where([
                ['data_type', $data_type],
                ['site_id', $site_id],
            ])->whereNull('archived_at')->whereIn('state', [ReplicationSessionStates::created, ReplicationSessionStates::ongoing])->first();
            if (! $replicationSession) {
                $replicationCodesMap[$key] = null;
            }
        }

        $ongoingReplications = implode(' ', Arr::whereNotNull($replicationCodesMap));

        return view('site_status_overview', compact('site', 'siteConfigJson', 'ongoingReplications') );
    }

    public function resetNotToBeHostedSitesDatabase($pfm = false)
    {
        $notToBeHostedSites = Site::where([
            ['is_to_be_hosted', 0],
        ])->get();

        foreach ($notToBeHostedSites as $key => $site) {
            $site = Site::whereSiteId($site->site_id)->with('most_recent_site_definition')->first();
            if (! $site->most_recent_site_definition) {
                H::pfm('No site definition '.$site->site_id, pfm: $pfm);
                continue;
            } else {
                H::pfm('Site definition present '.$site->site_id);
            }
            $siteConfig = (new SiteConfigService)->getSiteConfig($site);

            $site_Has_Database = $siteConfig->site_Has_Database;
            if ($site_Has_Database) {
                $result = (new SiteDatabaseService)->resetSiteDatabase($site->site_id);
                H::pfm(json_encode($result), pfm: $pfm);
            }
        }
    }

    public function onSiteMaintenance($site_id = null, $pfm = false)
    {
        $request = new Request();
        
        $request->merge(['site_id' => $site_id, 'pfm' => $pfm]);
        $request->validate([
            'site_id' => 'nullable|'.Consts::notRequiredSiteIdValidationRule,
            'pfm' => ['boolean'],
        ]);

        /* MAINTENANCE Begin */

        $recordsPurgingEnabled = false;

        /* Visitor Records Clean Up */
        if ($recordsPurgingEnabled) {
            $readyToPurgeVisitorRecords = VisitorRecord::withTrashed()->where([
                ['deleted_at', '<', now()->subDays(30)->toDateTimeString()],
            ]);
            foreach ($readyToPurgeVisitorRecords as $key => $readyToPurgeVisitorRecord) {
                H::pfm('Purging readyToPurgeVisitorRecord: '.$readyToPurgeVisitorRecord->entity_id, pfm: $pfm);
                $readyToPurgeVisitorRecord->forceDelete();
            }
        }

        /* Visitor Resources Clean Up */
        if ($recordsPurgingEnabled) {
            $readyToPurgeVisitorResources = VisitorResource::withTrashed()->where([
                ['deleted_at', '<', now()->subDays(30)->toDateTimeString()],
            ]);
            foreach ($readyToPurgeVisitorResources as $key => $readyToPurgeVisitorResource) {
                H::pfm('Purging readyToPurgeVisitorResource: '.$readyToPurgeVisitorResource->entity_id, pfm: $pfm);
                $readyToPurgeVisitorResource->forceDelete();
            }
        }


        /* Sites Pending Maintenance */
        $sitesPendingMaintenanceBuilder = Site::where('last_maintenance_at', '<', now()->subHours(1)->toDateTimeString())
        ->orWhereNull('last_maintenance_at')->publishedOnly()->orderBy('last_maintenance_at')->take(3);
        
        if ($site_id)
        $sitesPendingMaintenanceBuilder->whereSiteId($site_id);

        $sitesPendingMaintenance = $sitesPendingMaintenanceBuilder->get();
        
        foreach ($sitesPendingMaintenance as $key => $sitePendingMaintenance) {
            H::pfm('sitePendingMaintenance: '.$sitePendingMaintenance->site_id, pfm: $pfm);

            $site_id = $sitePendingMaintenance->site_id;

            $isBanned = $sitePendingMaintenance->list_entries->where('data_type', ListProviderTypes::banned_sites)->first();

            if ($isBanned) {
                $request->merge(['site_id' => $site_id]);
                (new SiteManagerController())->purgeSite($request);
                continue;
            }

            $sitePendingMaintenance->last_maintenance_at = now();
            $sitePendingMaintenance->save();

            H::pfm('site_id: ' .$site_id, pfm: $pfm);

            $site = Site::whereSiteId($site_id)->with('most_recent_site_definition')->first();

            if (! $site->most_recent_site_definition)
            continue;

            $siteConfig = (new SiteConfigService)->getSiteConfig($site);
            
            if ($siteConfig->site_Has_Database) {
                (new SiteDatabaseService)->backupSiteDatabase($site_id);
                (new SiteDatabaseService)->prepareSiteDatabase($site_id);
                (new SiteDatabaseService)->maintainDatabase($site_id);
            }

            /* Visitor Records Validation */
            $visitorRecords = VisitorRecord::where([
                ['site_id', $site_id],
            ])
            ->where(function ($query) {
                $query->where('validated_at', '<', now()->subHours(24))
                ->orWhereNull('validated_at');
            })
            ->get();
    
            foreach ($visitorRecords as $key => $visitorRecord) {
                H::pfm('Validate visitorRecord: '.$visitorRecord->entity_id, pfm: $pfm);
                $siteConfig = (new SiteConfigService)->getSiteConfig($site);
                $recordAsObject = json_decode($visitorRecord->record_json);
                $hasRightsRC = PermissionService::hasSignerRightsToTable(
                signer: $recordAsObject->signer ?? $recordAsObject->{SysProps::_sn_signer}, table: $recordAsObject->{SysProps::_sn_table}, 
                siteConfig: $siteConfig);

                if (! $hasRightsRC->operation_successful)
                continue;
                

                if (! $hasRightsRC->can_post_to_table) {

                    H::pfm('hasSignerRightsToTable: false - deleting '.$visitorRecord->entity_id, pfm: $pfm);
                    $visitorRecord->delete();

                    (new SiteDatabaseService)->prepareSiteDatabase($site_id);

                    DB::connection($site_id)->table($visitorRecord->table)
                    ->where([
                        ['id', $visitorRecord->entity_id],
                    ])
                    ->update(
                        ['deleted_at' => now()]
                    );

                    continue;
                }

                $visitorRecord->validated_at = now();
                $visitorRecord->save();
            }

            /* Visitor Resources Validation */
            $visitorResources = VisitorResource::where([
                ['site_id', $site_id],
            ])
            ->where(function ($query) {
                $query->where('validated_at', '<', now()->subHours(24))
                ->orWhereNull('validated_at');
            })
            ->get();

            $site = Site::whereSiteId($site_id)->with('most_recent_site_definition')->first();

            foreach ($visitorResources as $key => $visitorResource) {
                H::pfm('Validate visitorResource: '.$visitorResource->entity_id, pfm: $pfm);


                $siteConfig = (new SiteConfigService)->getSiteConfig($site);
                
                $signer = $visitorResource->signer;
                
                $hasRightsResult = (new BackendController())->hasVisitorRightsToUpload($signer, $siteConfig);

                if ($hasRightsResult->operation_successful) {
                    info('Failed Check hasVisitorRightsToUpload '.$signer.' '.$hasRightsResult->error_message);
                    continue;
                }
                
                if (! $hasRightsResult->can_upload_files) {
                    H::pfm('hasVisitorRightsToUpload: false - deleting '.$visitorResource->entity_id, pfm: $pfm);
                    $visitorResource->delete();
                    continue;
                }

                $visitorResource->validated_at = now();
                $visitorResource->save();
            }
        }
    }
}
