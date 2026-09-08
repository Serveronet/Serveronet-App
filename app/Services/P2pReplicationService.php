<?php

namespace App\Services;

use App\Dicts\DataTypes;
use App\Dicts\SSETypes;
use App\Dicts\SysProps;
use App\Facades\SiteSSEFacade;
use App\Http\Consts;
use App\Http\Controllers\BackendController;
use App\Http\Controllers\BackgroundProcessingController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SitesVisitorController;
use App\Http\Controllers\UtilsController;
use App\Http\H;
use App\Models\Peer;
use App\Models\Site;
use App\Models\SiteDefinition;
use App\Models\SiteDefinitionEnvelope;
use App\Models\SitePeer;
use App\Models\SitePeerRecordEnvelope;
use App\Models\VisitorRecord;
use App\Models\VisitorRecordEnvelope;
use App\Models\VisitorResource;
use App\Models\VisitorResourceEnvelope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class P2pReplicationService extends Controller
{

    /* Return value is ignored */
    public static function handleIncomingRecords($data_type, $incomingJson, $forceSave = false, $pfm = false): bool
    {
        info('$handleIncomingRecords');
        info('$incomingJson '.$data_type.' | forceSave: '.tfyn($forceSave));
        info(Str::limit($incomingJson, 50));
        H::pfm('handleIncomingRecords '.$data_type, pfm: $pfm);
        try {
            $p2pRecordsCollectionOrObject = json_decode($incomingJson);
            $a = [];
            if (! is_array($p2pRecordsCollectionOrObject)) {
                array_push($a, $p2pRecordsCollectionOrObject);
                $p2pRecordsCollection = $a;
            } else {
                $p2pRecordsCollection = $p2pRecordsCollectionOrObject;
            }
        } catch (Throwable $th) {
            info($th->getMessage().' '.$th->getFile().' '.$th->getLine());

            return false;
        }

        switch ($data_type) {
            case DataTypes::site_peers:
                H::pfm('site_peers'.' '.json_encode($p2pRecordsCollection), pfm: $pfm);

                foreach ($p2pRecordsCollection as $incomingRecord) {

                    try {
                        $recordEnvelope = new SitePeerRecordEnvelope;
                        $recordEnvelope->client_address = $incomingRecord->client_address;
                        $recordEnvelope->site_id = $incomingRecord->site_id;
                    } catch (Throwable $th) {
                        continue;
                    }
                    (new P2pReplicationService)->handleIncomingSitePeer(incomingRecordEnvelope: $recordEnvelope);

                }

                return true;
                break;

            case DataTypes::site_definitions:
                $envelopesCollection = $p2pRecordsCollection;
                foreach ($envelopesCollection as $incomingRecordEnvelope) {
                    try {
                        $recordEnvelope = new SiteDefinitionEnvelope($incomingRecordEnvelope);
                    } catch (Throwable $th) {
                        info('Malformed envelope received - ignoring. '.$th->getMessage());

                        continue;
                    }

                    (new P2pReplicationService)->handleIncomingSiteDefinition(incomingRecordEnvelope: $recordEnvelope, pfm: $pfm);

                }

                return true;
                break;

            case DataTypes::visitor_records:
                $envelopesCollection = $p2pRecordsCollection;
                foreach ($envelopesCollection as $incomingRecordEnvelope) {
                    try {
                        $recordEnvelope = new VisitorRecordEnvelope($incomingRecordEnvelope);
                    } catch (Throwable $th) {
                        info('Malformed envelope received - ignoring. '.$th->getMessage());

                        continue;
                    }
                    (new P2pReplicationService)->handleIncomingVisitorRecord($recordEnvelope, forceSave: $forceSave, pfm: $pfm);
                }

                return true;
                break;

            case DataTypes::visitor_resources:
                $envelopesCollection = $p2pRecordsCollection;
                foreach ($envelopesCollection as $incomingResourceEnvelope) {
                    try {
                        $recordEnvelope = new VisitorResourceEnvelope($incomingResourceEnvelope);
                    } catch (Throwable $th) {
                        info('Malformed envelope received - ignoring. '.$th->getMessage());

                        continue;
                    }
                    (new P2pReplicationService)->handleIncomingVisitorResource($recordEnvelope, forceSave: $forceSave, pfm: $pfm);
                }

                return true;
                break;

            default:
                return false;
                break;
        }
    }

    public function handleIncomingSitePeer(SitePeerRecordEnvelope $incomingRecordEnvelope, $criteria = []): bool
    {
        info('handleIncomingSitePeer');
        $client_address = H::a($incomingRecordEnvelope->client_address);
        if (in_array($client_address, H::getSelfAddresses())) {
            return false;
        }

        if (filter_var($client_address, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $site_peer = SitePeer::where([
            ['site_id', $incomingRecordEnvelope->site_id],
            ['client_address', $client_address],
        ])
            ->withoutSelf()
            ->first();

        if (! $site_peer) {
            if (H::checkIpIsPublic($client_address)) {

                $site_peer = new SitePeer;

                $site_peer->client_address = $client_address;
                $site_peer->site_id = $incomingRecordEnvelope->site_id;
                $site_peer->source = 'replication';
                $site_peer->save();

                $peer = Peer::where('client_address', $client_address)->first();
                if (! $peer) {
                    $peer = new Peer;
                    $peer->client_address = $client_address;
                    $peer->save();
                }
            }
        }
        foreach ($criteria as $key => $criterion) {
            if ($site_peer->{$key} !== $criterion) {
                return false;
            }
        }

        return true;
    }

    public function handleIncomingSiteDefinition(SiteDefinitionEnvelope $incomingRecordEnvelope, $criteria = [], $pfm = false): bool
    {
        info('handleIncomingSiteDefinition');
        $incomingRecord = json_decode($incomingRecordEnvelope->record_json);

        $record_json = $incomingRecordEnvelope->record_json;

        if (! (new PQCryptoService)->isCryptoCorrectVerKey(
            $incomingRecordEnvelope->signer_verification_key_base64,
            $incomingRecordEnvelope->record_json,
            $incomingRecordEnvelope->signature,
        )) {
            info('SD Crypto INCORRECT: '.$incomingRecord->entity_created);

            return false;
        } else {
            info('SD Crypto Correct: '.$incomingRecord->entity_created);
        }
        $isRecordConsistent = (new IntegrityService)->isRecordConsistent(
            record_json: $record_json,
            isVisitors: false,
            signer_verification_key_base64: $incomingRecordEnvelope->signer_verification_key_base64
        );

        if (! $isRecordConsistent) {
            H::pfm('Record Inconsistent.', pfm: $pfm);
            info('Record Inconsistent.');

            return false;
        } else {
            H::pfm('Record Consistent: '.$incomingRecord->entity_created, pfm: $pfm);
        }

        $siteDefinition = SiteDefinition::where([
            ['site_id', $incomingRecord->site_id],
            ['entity_created', $incomingRecord->entity_created],
        ])->first();

        /* We don't have this version - saving */
        if (! $siteDefinition) {
            info('Storing new SiteDefinition for '.$incomingRecord->site_id);

            $siteDefinition = new SiteDefinition;

            $siteDefinition->site_id = $incomingRecord->site_id;
            $siteDefinition->signer_verification_key_base64 = $incomingRecordEnvelope->signer_verification_key_base64;
            $siteDefinition->entity_created = $incomingRecord->entity_created;
            $siteDefinition->title = $incomingRecord->title;
            $siteDefinition->description = $incomingRecord->description;
            $siteDefinition->site_config_json = $incomingRecord->site_config_json;
            $siteDefinition->file_listing_json = $incomingRecord->file_listing_json;
            $siteDefinition->signature = $incomingRecordEnvelope->signature;
            $siteDefinition->record_json = $incomingRecordEnvelope->record_json;
            $siteDefinition->files_count = $incomingRecord->files_count ?? 0;
            $siteDefinition->files_size = $incomingRecord->files_size ?? 0;
            $siteDefinition->visitor_records_preliminary_total_size
                = $incomingRecord->visitor_records_preliminary_total_size ?? null;
            $siteDefinition->visitor_resources_preliminary_total_size
                = $incomingRecord->visitor_resources_preliminary_total_size ?? null;

            $siteDefinition->created_at = now();
            $siteDefinition->updated_at = now();

            $siteDefinition->save();

            $message = $siteDefinition->record_json;

            SiteSSEFacade::notify(message: $message, type: SSETypes::created_site_definition,
                event: 'message', site_id: $siteDefinition->site_id);

            $site_id = $siteDefinition->site_id;

            $closure = function () use ($site_id) {

                BackgroundProcessingController::prepareMissingSiteDefinitionsRetrievals($site_id);

                SitesVisitorController::processContentRetrievals($site_id, 10);

                BackgroundProcessingController::schedulePendingDownloads($site_id);

                BackgroundProcessingController::processPendingDownloads($site_id);

                BackgroundProcessingController::checkHostedPromotableSiteDefinitions(site_id: $site_id);

                (new BackgroundProcessingController)->deleteSiteDefinitionsWhenNewerPresent($site_id);

            };

            H::dispatchInternalAsyncClosureWrapper($closure);

        }

        foreach ($criteria as $key => $criterion) {
            if ($siteDefinition->{$key} !== $criterion) {
                return false;
            }
        }

        return true;

    }

    public function handleIncomingVisitorResource(VisitorResourceEnvelope $incomingRecordEnvelope, $forceSave = false, $criteria = [], $pfm = false)
    {
        info('handleIncomingVisitorResource | forceSave: '.tfyn($forceSave));
       

        $isCryptoCorrect =  (new PQCryptoService)->isCryptoCorrectVerKey(
            $incomingRecordEnvelope->signer_verification_key_base64,
            $incomingRecordEnvelope->record_json,
            $incomingRecordEnvelope->signature,
        );
        $incomingRecord = json_decode($incomingRecordEnvelope->record_json);
        $record_json = $incomingRecordEnvelope->record_json;

        if (! $isCryptoCorrect) {
            H::pfm('Crypto INCORRECT: '.$incomingRecord?->{SysProps::_sn_entity_id}, pfm: $pfm);
            info('VR Crypto INCORRECT: '.$incomingRecord->{SysProps::_sn_entity_id});

            return false;
        }

        $site_id = $incomingRecord->{SysProps::_sn_site_id};
        $request = new Request;
        $request->merge(['site_id' => $site_id]);

        $invalidResult = (new UtilsController)->validatedReturnSiteId($request, $site_id);
        if ($invalidResult) {
            info('validatedReturnSiteId fail');
            return false;
        }

        $site = Site::whereSiteId($site_id)->first();
        if (! $site) {
            info('No such a Site');
            return false;
        }

        if (! $site?->is_to_be_hosted && ! $forceSave) {
            info('Not saving records as not hosted and not forced');
            return false;
        }

        $isRecordConsistent = (new IntegrityService)->isRecordConsistent(
            record_json: $record_json,
            isVisitors: true,
            signer_verification_key_base64: $incomingRecordEnvelope->signer_verification_key_base64
        );

        info('isRecordConsistent: '.$isRecordConsistent);
        if (! $isRecordConsistent) {
            H::pfm('Record Inconsistent: ', pfm: $pfm);
            info('VRes Record Inconsistent: ');

            return false;
        } else {
            H::pfm('Record Consistent: '.$incomingRecord->{SysProps::_sn_entity_id}, pfm: $pfm);
            info('VRES Record Consistent: '.$incomingRecord->{SysProps::_sn_entity_id});
        }

        /* Grant record */
        if ($incomingRecordEnvelope->grant_record ?? null) {
            $attachedRecord = $incomingRecordEnvelope->grant_record;
            info('Handling attached grant json of record: '.$incomingRecord->{SysProps::_sn_entity_id});
            try {
                $recordEnvelope = new VisitorRecordEnvelope($attachedRecord);
                $this->handleIncomingVisitorRecord($recordEnvelope, forceSave: $forceSave, pfm: $pfm);
            } catch (\Throwable $th) {
                info('Malformed envelope received - ignoring. '.$th->getMessage());
            }
        } else {
                info('No Grant Record attached to Visitor Resource');
        }

        $signer = $incomingRecord->{SysProps::_sn_signer};

        $siteConfig = (new SiteConfigService)->getSiteConfig($site);

        $canUploadRC = (new BackendController)->hasVisitorRightsToUpload($signer, $siteConfig);

        if (! $canUploadRC->operation_successful) {
            info('Failed Check getCanUploadState '.$incomingRecord->{SysProps::_sn_entity_id}.' '.$canUploadRC->error_message);

            return false;
        }

        if (! $canUploadRC->can_upload_files) {
            info('Reject getCanUploadState '.$incomingRecord->{SysProps::_sn_entity_id}.' '.$canUploadRC->upload_files_reason);

            return false;
        }

        $signerRightsRC = PermissionService::hasSignerRightsToVisitorResource($incomingRecord, $siteConfig);
        info('handle inc visitor_resources');
        if (! $signerRightsRC->operation_successful) {
            info('Check Failure hasSignerRightsToVisitorResource '.$incomingRecord->{SysProps::_sn_entity_id});

            return false;
        }
        if (! $signerRightsRC->has_signer_rights) {
            info('Reject hasSignerRightsToVisitorResource '.$incomingRecord->{SysProps::_sn_entity_id});

            return false;
        }

        $isSiteAdminRC = PermissionService::isSiteAdmin($incomingRecord->{SysProps::_sn_signer}, $siteConfig);
        H::pfm($isSiteAdminRC, pfm: $pfm);
        if (! $isSiteAdminRC->operation_successful) {
            return false;
        }

        $dirtyFlag = false;
        $createdNew = false;

        $visitorResource = VisitorResource::where([
            ['entity_id', $incomingRecord->{SysProps::_sn_entity_id}],
            ['site_id', $site_id],
        ])->withTrashed()->first();

        
        if (! $visitorResource) {
            $visitorResource = new VisitorResource();
            $dirtyFlag = true;
            $createdNew = true;
            H::pfm('New resource', pfm: $pfm);
        }

        if ($visitorResource->is_site_admin_locked && ! $isSiteAdminRC->is_site_admin) {
            info('Reject as is site admin locked: '.$incomingRecord->{SysProps::_sn_entity_id});

            return false;
        }

        H::pfm('Dates '.$visitorResource->entity_updated.' '.$incomingRecord->{SysProps::_sn_entity_updated}, pfm: $pfm);
        if ($incomingRecord->{SysProps::_sn_entity_updated} > $visitorResource->entity_updated) {
            $dirtyFlag = true;
            info('Updating: '.$incomingRecord->{SysProps::_sn_entity_updated}.' '.$visitorResource->entity_updated);
        }

        if ($dirtyFlag) {
            $visitorResource->entity_id = $incomingRecord->{SysProps::_sn_entity_id};
            $visitorResource->site_id = $site_id;
            $visitorResource->sha256 = $incomingRecord->sha256 ?? null;
            $visitorResource->original_file_name = $incomingRecord->original_file_name ?? null;
            $visitorResource->visitor_id = explode('_', $incomingRecord->{SysProps::_sn_entity_id})[1];
            $visitorResource->is_site_admin_locked = $incomingRecord->{SysProps::_sn_is_site_admin_locked} ?? false;
            $visitorResource->file_size = $incomingRecord->file_size ?? 0;
            $visitorResource->chunks_json = $incomingRecord->chunks_json ?? null;
            $visitorResource->signer_verification_key_base64 = $incomingRecordEnvelope->signer_verification_key_base64;
            $visitorResource->record_json = $incomingRecordEnvelope->record_json;
            $visitorResource->signature = $incomingRecordEnvelope->signature;
            $visitorResource->entity_created = $incomingRecord->{SysProps::_sn_entity_created};
            $visitorResource->entity_updated = $incomingRecord->{SysProps::_sn_entity_updated};
            $visitorResource->entity_deleted = $incomingRecord->{SysProps::_sn_entity_deleted} ?? null;
            $derrivedCreatedAt = Carbon::createFromFormat(Consts::visitorDataDateIdFormat,
                $incomingRecord->{SysProps::_sn_entity_created});
            $visitorResource->updated_at = now();
            $visitorResource->created_at = $derrivedCreatedAt;
            $visitorResource->deleted_at = self::deriveDate($incomingRecord->{SysProps::_sn_entity_deleted} ?? null);
            $visitorResource->validated_at = now();
            $visitorResource->save();

            H::pfm('saving '.$visitorResource->entity_id, pfm: $pfm);
        }

        if ($dirtyFlag) {
            $type = SSETypes::updated_visitor_resource;
            
            if (! empty($incomingRecord->{SysProps::_sn_entity_deleted})) {
                $type = SSETypes::deleted_visitor_resource;
            } elseif ($createdNew) {
                $type = SSETypes::created_visitor_resource;
            }

            $message = $visitorResource->record_json;
            SiteSSEFacade::notify(message: $message, type: $type, event: 'message', site_id: $site_id);
        }

        foreach ($criteria as $key => $criterion) {
            if ($visitorResource->{$key} !== $criterion) {
                return false;
            }
        }

        return true;
    }

    public function handleIncomingVisitorRecord(VisitorRecordEnvelope $incomingRecordEnvelope, $forceSave = false, $criteria = [], $pfm = false)
    {
        info('handleIncomingVisitorRecord | forceSave: '.tfyn($forceSave));
        $isCryptoCorrect = (new PQCryptoService)->isCryptoCorrectVerKey(
            $incomingRecordEnvelope->signer_verification_key_base64,
            $incomingRecordEnvelope->record_json,
            $incomingRecordEnvelope->signature,
        );

        $incomingRecord = json_decode($incomingRecordEnvelope->record_json);
        $record_json = $incomingRecordEnvelope->record_json;

        if (! $isCryptoCorrect) {
            H::pfm('Crypto INCORRECT: '.$incomingRecord?->{SysProps::_sn_entity_id}, pfm: $pfm);
            info('VR Crypto INCORRECT: '.$incomingRecord->{SysProps::_sn_entity_id});

            return false;
        }

        $site_id = $incomingRecord->{SysProps::_sn_site_id};
        $request = new Request;
        $request->merge(['site_id' => $site_id]);

        $invalidResult = (new UtilsController)->validatedReturnSiteId($request, $site_id);
        if ($invalidResult) {
            info('validatedReturnSiteId fail');
            return false;
        }

        $site = Site::whereSiteId($site_id)->with('most_recent_site_definition')->first();
        if (! $site) {
            info('No such a Site');
            return false;
        }

        if (! $site?->is_to_be_hosted && ! $forceSave) {
            info('Not saving records as not hosted and not forced');
            return false;
        }

        $isRecordConsistent = (new IntegrityService)->isRecordConsistent(
            record_json: $record_json,
            isVisitors: true,
            signer_verification_key_base64: $incomingRecordEnvelope->signer_verification_key_base64
        );

        if (! $isRecordConsistent) {
            H::pfm('Record Inconsistent: ', pfm: $pfm);
            info('VRec Record Inconsistent: ');

            return false;
        } else {
            H::pfm('Record Consistent: '.$incomingRecord->{SysProps::_sn_entity_id}, pfm: $pfm);
            info('VRec Record Consistent: '.$incomingRecord->{SysProps::_sn_entity_id});
        }

        $signer = $incomingRecord->{SysProps::_sn_signer};
        $table = $incomingRecord->{SysProps::_sn_table};

        $siteConfig = (new SiteConfigService)->getSiteConfig($site);

        if ($incomingRecordEnvelope->grant_record ?? null) {
            $attachedRecord = $incomingRecordEnvelope->grant_record;
            info('Handling attached grant json of record: '.$incomingRecord->{SysProps::_sn_entity_id});

            try {
                $recordEnvelope = new VisitorRecordEnvelope($attachedRecord);
                $this->handleIncomingVisitorRecord($recordEnvelope, forceSave: $forceSave, pfm: $pfm);
            } catch (\Throwable $th) {
                info('Malformed envelope received - ignoring. '.$th->getMessage());
            }
        }

        $isSiteAdminRC = PermissionService::isSiteAdmin($incomingRecord->{SysProps::_sn_signer}, $siteConfig);
        H::pfm($isSiteAdminRC, pfm: $pfm);
        if (! $isSiteAdminRC->operation_successful) {
            return false;
        }

        if ($incomingRecord->{SysProps::_sn_is_grant_record} ?? false) {
            H::pfm('handleIncomingVisitorRecord is_grant_record Yes '.$incomingRecord->{SysProps::_sn_entity_id}, pfm: $pfm);

            if (! $isSiteAdminRC->is_site_admin) {
                return false;
            }

        } else {
            $resultContainer = PermissionService::hasSignerRightsToTable(
                signer: $signer, siteConfig: $siteConfig, table: $table
            );

            if (! $resultContainer->operation_successful) {
                info('Failure hasSignerRightsToTable '.$incomingRecord->{SysProps::_sn_entity_id}.' '.$resultContainer->error_message);

                return false;
            }

            if (! $resultContainer->can_post_to_table) {
                info('Reject hasSignerRightsToTable '.$incomingRecord->{SysProps::_sn_entity_id}.' '.$resultContainer->posts_to_table_reason);

                return false;
            }

            if (! PermissionService::hasSignerRightsToVisitorRecord($incomingRecord, $siteConfig)) {
                info('Reject hasSignerRightsToVisitorRecord '.$incomingRecord->{SysProps::_sn_entity_id});

                return false;
            }
        }

        $dirtyFlag = false;
        $createdNew = false;

        $visitorRecord = VisitorRecord::where([
            ['entity_id', $incomingRecord->{SysProps::_sn_entity_id}],
            ['site_id', $site_id],
        ])->withTrashed()->first();

        if (! $visitorRecord) {
            $visitorRecord = new VisitorRecord();
            $dirtyFlag = true;
            $createdNew = true;
            H::pfm('New Record', pfm: $pfm);
        }

        if ($visitorRecord->is_site_admin_locked && ! $isSiteAdminRC->is_site_admin) {
            info('Reject is_site_admin_locked True '.$incomingRecord->{SysProps::_sn_entity_id});

            return false;
        }

        H::pfm('Dates '.$visitorRecord->entity_updated.' '.$incomingRecord->{SysProps::_sn_entity_updated}, pfm: $pfm);

        if ($incomingRecord->{SysProps::_sn_entity_updated} > $visitorRecord->entity_updated && ! $createdNew) {
            $dirtyFlag = true;
            H::pfm('Updating incomming record ', pfm: $pfm);
        }

        if ($dirtyFlag) {
            $visitorRecord->entity_id = $incomingRecord->{SysProps::_sn_entity_id};
            $visitorRecord->is_grant_record = $incomingRecord->{SysProps::_sn_is_grant_record} ?? null;
            $visitorRecord->grantee_visitor_id = $incomingRecord->_sn_grantee_visitor_id ?? null;
            $visitorRecord->site_id = $site_id;
            $visitorRecord->table = $incomingRecord->{SysProps::_sn_table};
            $visitorRecord->is_site_admin_locked = $incomingRecord->{SysProps::_sn_is_site_admin_locked} ?? false;
            $visitorRecord->visitor_id = explode('_', $incomingRecord->{SysProps::_sn_entity_id})[1];

            $visitorRecord->signer_verification_key_base64 = $incomingRecordEnvelope->signer_verification_key_base64;
            $visitorRecord->signature = $incomingRecordEnvelope->signature;
            $visitorRecord->record_json = $record_json;

            $visitorRecord->entity_created = $incomingRecord->{SysProps::_sn_entity_created};
            $visitorRecord->entity_updated = $incomingRecord->{SysProps::_sn_entity_updated};
            $visitorRecord->entity_deleted = $incomingRecord->{SysProps::_sn_entity_deleted};

            /* Needed for Sync */
            $derrivedCreatedAt = Carbon::createFromFormat(Consts::visitorDataDateIdFormat,
                $incomingRecord->{SysProps::_sn_entity_created});

            $visitorRecord->updated_at = now();
            $visitorRecord->created_at = $derrivedCreatedAt;
            $visitorRecord->deleted_at = self::deriveDate($incomingRecord->{SysProps::_sn_entity_deleted} ?? null);

            $visitorRecord->validated_at = now();
            $visitorRecord->save();

            H::pfm('Saved '.$visitorRecord->entity_id, pfm: $pfm, log: true);
            

            if (($site->is_to_be_hosted || $forceSave) && ! $visitorRecord->is_grant_record) {
                H::pfm('Saving to Site DB '.$visitorRecord->entity_id, pfm: $pfm, log: true);
                (new SiteDatabaseService)->parseUpsertIncomingVisitorRecord($visitorRecord, $visitorRecord->site_id);

            } else {
                H::pfm('Not Saving to Site DB '.$visitorRecord->entity_id, pfm: $pfm, log: true);
            }
        } else {
            H::pfm('Record not modified or new '.$visitorRecord->entity_id, pfm: $pfm, log: true);
        }

        if ($dirtyFlag) {
            $type = SSETypes::updated_visitor_record;
            
            if (! empty($incomingRecord->{SysProps::_sn_entity_deleted})) {
                $type = SSETypes::deleted_visitor_record;
            } elseif ($createdNew) {
                $type = SSETypes::created_visitor_record;
            }

            $message = $visitorRecord->record_json;
            SiteSSEFacade::notify(message: $message, type: $type, event: 'message', site_id: $site_id);
        }

        return true;
    }

    public static function deriveDate(?string $timestampString)
    {
        if (! $timestampString) {
            return null;
        }

        return Carbon::createFromFormat(Consts::visitorDataDateIdFormat, $timestampString);
    }
}
