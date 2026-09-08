<?php

namespace App\Http\Controllers;

use App\Dicts\MessageTypes;
use App\Dicts\SSETypes;
use App\Dicts\SysProps;
use App\Facades\SiteSSEFacade;
use App\Http\Consts;
use App\Http\H;
use App\Models\ResultContainer;
use App\Models\Site;
use App\Models\VisitorRecord;
use App\Services\PermissionService;
use App\Services\PQCryptoService;
use App\Services\SiteConfigService;
use App\Services\SiteDatabaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class UpsertController extends Controller
{
    /**
     * Visitor Record Create (API Token)
     *
     * See regular Upsert Endpoint for details
     *
     * @authenticated
     *
     * @group Api Token Endpoints
     *
     * @header Accept application/json
     *
     * @bodyParam api_token string required Example: your_visitor_api_token
     * @bodyParam _sn_record_json string required Example: {"title":"My title", "_sn_table":"posts"}
     *
     * @response 200 {
     *    "success": true,
     *    "data": [
     *       {
     *           "entity_id": "2026...",
     *       }
     *     ]
     * }
     */
    public function createEndpointApi(Request $request)
    {
        return $this->createEndpoint($request);
    }

    /**
     * Visitor Record Create
     *
     * Receives a json body of a record to be created (in _sn_record_json parameters)
     * Json requires target table (_sn_table) and site's database fields
     *
     * @authenticated
     *
     * @header Accept application/json
     *
     * @group Records and Resources
     *
     * @bodyParam _sn_record_json string required Example: {"title":"My title", "_sn_table":"posts"}
     *
     * @response 200 {
     *    "success": true,
     *    "data": [
     *       {
     *           "entity_id": "2026...",
     *       }
     *     ]
     * }
     */
    public function createEndpoint(Request $request)
    {
        info('upsertEndpoint Start');

        if (! $request->_sn_record_json) {
            $request->merge(['_sn_record_json' => $request->getContent()]);
        }
        try {
            $request->validate([
                '_sn_record_json' => ['required', 'json', 'max:'.(Consts::recordJsonMaxSizeBytes)],
            ]);
        } catch (Throwable $th) {
            return $this->return_failure($th->getMessage());
        }

        $site_id = $request->site_id;
        $record_json = $request->{SysProps::_sn_record_json};

        H::siteEndpointsCommonResolve($request, $site_id, $domain);

        if ($invalidResult = (new UtilsController)->validatedReturnSiteId($request, $site_id)) {
            return $invalidResult;
        }

        $visitor = Auth::guard('visitor')->user() ?? Auth::guard('visitor_api')->user();
        $visitor_id = $visitor->visitor_id;
        $authenticated_visitor_verification_key_base64 = $visitor->verification_key_base64;
        $signer = $visitor->visitor_id;
        $signer_verification_key_base64 = $authenticated_visitor_verification_key_base64;

        $site = Site::whereSiteId($site_id)->with('most_recent_site_definition')->first();

        /* Site not known, ignore inputs */
        if (! $site) {
            return $this->return_failure('Site not known');
        }

        $siteConfig = (new SiteConfigService)->getSiteConfig($site);

        /* Site has No database, ignore inputs */
        if (! $siteConfig->site_Has_Database) {
            return $this->return_failure('Site has no database');
        }

        $incomingRecord = json_decode($record_json);

        $visitorRecordCreateRequiredFields = [
            SysProps::_sn_table,
        ];

        foreach ($visitorRecordCreateRequiredFields as $key => $field) {
            try {
                $table = $incomingRecord->{$field};
            } catch (Throwable $th) {
                return $this->return_failure('Required field is missing: '.$field);
            }
        }

        $newRecordTS = now();
        $nowMicroTimeString = $newRecordTS->format(Consts::visitorDataDateIdFormat);
        $newId = $newRecordTS->format(Consts::visitorDataDateIdFormat).'_'.$visitor_id;
        $originatorVisitorId = $visitor_id;

        $incomingRecord->{SysProps::_sn_entity_created} = $nowMicroTimeString;
        $incomingRecord->{SysProps::_sn_entity_id} = $newId;

        $newRecordCount = 1;

        $isSiteAdminRC = PermissionService::isSiteAdmin($signer, $siteConfig);
        if (! $isSiteAdminRC->operation_successful) {
            return $this->return_failure('Failed to check the is site admin status. '.$isSiteAdminRC->error_message);
        }

        if ($incomingRecord->{SysProps::_sn_is_grant_record} ?? false) {

            if (! $isSiteAdminRC->is_site_admin) {
                return $this->return_failure('Signer is not a Site Admin.'.$isSiteAdminRC->error_message);
            }

        } else {
            /* Regular record - Checking permissions */
            $rightsRC = PermissionService::hasSignerRightsToTable(signer: $signer, siteConfig: $siteConfig, table: $table);

            if (! $rightsRC->operation_successful) {
                return $this->return_failure($rightsRC->error_message);
            }

            if (! $rightsRC->can_post_to_table) {
                return $this->return_failure($rightsRC->posts_to_table_reason);
            }
        }

        $newNowString = now()->toDateTimeString();

        $visitorRecord = new VisitorRecord;
        $createdAt = $newNowString;

        $visitorRecord->entity_updated = $nowMicroTimeString;

        $recordAsArrayForJson = (array) $incomingRecord;
        $recordAsArrayForJson[SysProps::_sn_entity_id] = $incomingRecord->{SysProps::_sn_entity_id};
        $recordAsArrayForJson[SysProps::_sn_site_id] = $site_id;
        $recordAsArrayForJson[SysProps::_sn_signer] = $visitor_id;

        $recordAsArrayForJson[SysProps::_sn_visitor_id] = $originatorVisitorId;

        $recordAsArrayForJson[SysProps::_sn_is_site_admin_locked] = false;

        $recordAsArrayForJson[SysProps::_sn_entity_created] = $incomingRecord->{SysProps::_sn_entity_created} ?? $nowMicroTimeString;
        $recordAsArrayForJson[SysProps::_sn_entity_updated] = $nowMicroTimeString;

        $recordAsArrayForJson[SysProps::_sn_entity_deleted] = null;

        /* Needed for Sync */
        $visitorRecord->created_at = $createdAt;
        $visitorRecord->updated_at = $newNowString;
        $visitorRecord->deleted_at = null;

        $visitorRecord->entity_created = $incomingRecord->{SysProps::_sn_entity_created} ?? $nowMicroTimeString;
        $visitorRecord->entity_deleted = null;

        $visitorRecord->entity_id = $incomingRecord->{SysProps::_sn_entity_id};
        $visitorRecord->is_grant_record = $incomingRecord->{SysProps::_sn_is_grant_record} ?? null;
        $visitorRecord->grantee_visitor_id = $incomingRecord->{SysProps::_sn_grantee_visitor_id} ?? null;

        $visitorRecord->signer_verification_key_base64 = $signer_verification_key_base64;
        $visitorRecord->signer = $visitor_id;

        $visitorRecord->visitor_id = $originatorVisitorId;
        $visitorRecord->is_site_admin_locked = $incomingRecord->{SysProps::_sn_is_site_admin_locked} ?? false;

        $visitorRecord->site_id = $site_id;

        $record_json = json_encode($recordAsArrayForJson);
        $recordAsArrayForJson[SysProps::_sn_record_json] = $record_json;

        $visitorRecord->record_json = $record_json;
        $visitorRecord->table = $table;

        $signature = (new PQCryptoService)->generateSignature($visitor, $record_json);
        $visitorRecord->signature = $signature;

        $visitorRecord->validated_at = now();
        $visitorRecord->save();

        if (! $visitorRecord->is_grant_record) {

            $siteDbRC = new ResultContainer;
            $siteDbRC = (new SiteDatabaseService)->parseUpsertIncomingVisitorRecord($visitorRecord, $site_id);

            if (! $siteDbRC->operation_successful) {
                return $this->return_failure('Upsert to Site DB failed. '.$siteDbRC->error_message);
            }
        }

        $visitorRecord->load('grant_record');

        H::enqueueP2pMessage(
            MessageTypes::visitor_record,
            payload: $visitorRecord->only('record_json', 'signature', 'signer_verification_key_base64'),
            site_id: $site_id,
            priority: 20,
        );

        H::savePassiveTriggersForSite($site_id);

        $message = $visitorRecord->record_json;

        $type = SSETypes::created_visitor_record;

        SiteSSEFacade::notify(message: $message, type: $type, event: 'message', site_id: $site_id);

        $result = [
            'insertedCount' => (int) ($newRecordCount ?? 0),
            'updatedCount' => (int) ($updatedRecordCount ?? 0),
            'deletedCount' => (int) ($deletedRecordCount ?? 0),
        ];

        $siteDbRC = $request->debug ? ($siteDbRC ?? []) : [];

        return $this->return_success(data: [SysProps::_sn_entity_id => $incomingRecord->{SysProps::_sn_entity_id}],
            metadata: $result, debug_data: $siteDbRC ?? []);
    }

    /**
     * Visitor Record Update (API Token)
     *
     * See regular Update Endpoint for details
     *
     * @authenticated
     *
     * @group Api Token Endpoints
     *
     * @header Accept application/json
     *
     * @bodyParam api_token string required Example: your_visitor_api_token
     * @bodyParam _sn_record_json string required Example: {"title":"My title", "_sn_table":"posts"}
     *
     * @response 200 {
     *    "success": true,
     *    "data": [
     *       {
     *           "entity_id": "2026...",
     *       }
     *     ]
     * }
     */
    public function updateEndpointApi(Request $request)
    {
        return $this->updateEndpoint($request);
    }

    /**
     * Visitor Record Update
     *
     * Receives a json body of a record to be updated (in _sn_record_json parameters)
     * Json requires target table (_sn_table), entity id to be updated (_sn_entity_id)
     * and site's database fields
     *
     * @authenticated
     *
     * @header Accept application/json
     *
     * @group Records and Resources
     *
     * @bodyParam _sn_record_json string required Example: {"title":"My title", "_sn_table":"posts"}
     *
     * @response 200 {
     *    "success": true,
     *    "data": [
     *       {
     *           "entity_id": "2026...",
     *       }
     *     ]
     * }
     */
    public function updateEndpoint(Request $request)
    {
        info('upsertEndpoint Start');

        if (! $request->_sn_record_json) {
            $request->merge(['_sn_record_json' => $request->getContent()]);
        }
        try {
            $request->validate([
                '_sn_record_json' => ['required', 'json', 'max:'.(Consts::recordJsonMaxSizeBytes)],
            ]);
        } catch (Throwable $th) {
            return $this->return_failure($th->getMessage());
        }

        $site_id = $request->site_id;
        $record_json = $request->{SysProps::_sn_record_json};

        H::siteEndpointsCommonResolve($request, $site_id, $domain);

        if ($invalidResult = (new UtilsController)->validatedReturnSiteId($request, $site_id)) {
            return $invalidResult;
        }

        $visitor = Auth::guard('visitor')->user() ?? Auth::guard('visitor_api')->user();
        $visitor_id = $visitor->visitor_id;
        $authenticated_visitor_verification_key_base64 = $visitor->verification_key_base64;
        $signer = $visitor->visitor_id;
        $signer_verification_key_base64 = $authenticated_visitor_verification_key_base64;

        $site = Site::whereSiteId($site_id)->with('most_recent_site_definition')->first();

        /* Site not known, ignore inputs */
        if (! $site) {
            return $this->return_failure('Site not known');
        }

        $siteConfig = (new SiteConfigService)->getSiteConfig($site);
        /* Site has No database, ignore inputs */
        if (! $siteConfig->site_Has_Database) {
            return $this->return_failure('Site has no database');
        }

        $incomingRecord = json_decode($record_json);

        $incomingRecord->{SysProps::_sn_signer} = $visitor_id;

        $_sn_mark_as_deleted = $incomingRecord->{SysProps::_sn_mark_as_deleted} ?? false;

        $visitorRecordUpdateRequiredFields = [
            SysProps::_sn_table,
            SysProps::_sn_entity_id,
        ];

        foreach ($visitorRecordUpdateRequiredFields as $key => $field) {
            if (! isset($incomingRecord->{$field})) {
                return $this->return_failure('Required field is missing: '.$field);
            }
        }
        $table = $incomingRecord->{SysProps::_sn_table};

        $newRecordTS = now();
        $nowMicroTimeString = $newRecordTS->format(Consts::visitorDataDateIdFormat);

        $originatorVisitorId = $visitor_id;

        if ($incomingRecord->{SysProps::_sn_entity_deleted} ?? null) {
            $deletedRecordCount = 1;
        } else {
            $updatedRecordCount = 1;
        }
        $originatorVisitorId = explode('_', $incomingRecord->{SysProps::_sn_entity_id})[1];

        $isSiteAdminRC = PermissionService::isSiteAdmin($signer, $siteConfig);
        if (! $isSiteAdminRC->operation_successful) {
            return $this->return_failure('Failed to check the is site admin status. '.$isSiteAdminRC->error_message);
        }

        if ($incomingRecord->{SysProps::_sn_is_grant_record} ?? false) {

            if (! $isSiteAdminRC->is_site_admin) {
                return $this->return_failure('Signer is not a Site Admin.'.$isSiteAdminRC->error_message);
            }

        } else {
            /* Regular record - Checking permissions */
            $rightsRC = PermissionService::hasSignerRightsToTable(signer: $signer, siteConfig: $siteConfig, table: $table);

            if (! $rightsRC->operation_successful) {
                return $this->return_failure($rightsRC->error_message);
            }

            if (! $rightsRC->can_post_to_table) {
                return $this->return_failure($rightsRC->posts_to_table_reason);
            }

            $signerRightsRC = PermissionService::hasSignerRightsToVisitorRecord($incomingRecord, $siteConfig);
            if (! $signerRightsRC->operation_successful) {
                return $this->return_failure('Failed to check signer rights. '
                .$signerRightsRC->error_message);
            } else {
                if (! $signerRightsRC->has_signer_rights) {
                    return $this->return_failure('No rights to this record. '
                    .$signerRightsRC->error_message);
                }
            }

        }

        $visitorRecord = VisitorRecord::where([
            ['entity_id', $incomingRecord->{SysProps::_sn_entity_id}],
            ['site_id', $site_id],
        ])->withTrashed()->first();

        $newNowString = now()->toDateTimeString();
        if (! $visitorRecord) {

            /* New */
            $visitorRecord = new VisitorRecord;

        } else {

            /* Updating */
            $createdAt = $visitorRecord->created_at;
        }

        if ($visitorRecord->is_site_admin_locked && ! $isSiteAdminRC->is_site_admin) {
            info('Reject as record is locked by the Site Admin. '.$incomingRecord->{SysProps::_sn_entity_id});

            return $this->return_failure('Reject as record is locked by the Site Admin.');
        }

        $visitorRecord->entity_updated = $nowMicroTimeString;

        $recordAsArrayForJson = (array) $incomingRecord;
        $recordAsArrayForJson[SysProps::_sn_entity_id] = $incomingRecord->{SysProps::_sn_entity_id};
        $recordAsArrayForJson[SysProps::_sn_site_id] = $site_id;
        $recordAsArrayForJson[SysProps::_sn_signer] = $visitor_id;

        $recordAsArrayForJson[SysProps::_sn_visitor_id] = $originatorVisitorId;

        $recordAsArrayForJson[SysProps::_sn_is_site_admin_locked] = $incomingRecord->{SysProps::_sn_is_site_admin_locked} ?? false;

        $recordAsArrayForJson[SysProps::_sn_entity_created] = $incomingRecord->{SysProps::_sn_entity_created} ?? $nowMicroTimeString;
        $recordAsArrayForJson[SysProps::_sn_entity_updated] = $nowMicroTimeString;

        $recordAsArrayForJson[SysProps::_sn_entity_deleted] = $_sn_mark_as_deleted ? $nowMicroTimeString : null;

        unset($recordAsArrayForJson[SysProps::_sn_mark_as_deleted]);

        /* Needed for Sync */
        $visitorRecord->created_at = $createdAt;
        $visitorRecord->updated_at = $newNowString;
        $visitorRecord->deleted_at = $_sn_mark_as_deleted ? $newNowString : null;

        $visitorRecord->entity_created = $incomingRecord->{SysProps::_sn_entity_created} ?? $nowMicroTimeString;
        $visitorRecord->entity_deleted = $_sn_mark_as_deleted ? $nowMicroTimeString : null;

        $visitorRecord->entity_id = $incomingRecord->{SysProps::_sn_entity_id};
        $visitorRecord->is_grant_record = $incomingRecord->{SysProps::_sn_is_grant_record} ?? null;
        $visitorRecord->grantee_visitor_id = $incomingRecord->{SysProps::_sn_grantee_visitor_id} ?? null;
        $visitorRecord->signer = $visitor_id;
        $visitorRecord->signer_verification_key_base64 = $signer_verification_key_base64;

        $visitorRecord->visitor_id = $originatorVisitorId;
        $visitorRecord->is_site_admin_locked = $incomingRecord->{SysProps::_sn_is_site_admin_locked} ?? false;

        $visitorRecord->site_id = $site_id;

        $record_json = json_encode($recordAsArrayForJson);
        $recordAsArrayForJson[SysProps::_sn_record_json] = $record_json;

        $visitorRecord->record_json = $record_json;
        $visitorRecord->table = $table;

        $signature = (new PQCryptoService)->generateSignature($visitor, $record_json);
        $visitorRecord->signature = $signature;

        $visitorRecord->validated_at = now();
        $visitorRecord->save();

        if (! $visitorRecord->is_grant_record) {

            $siteDbRC = new ResultContainer;
            $siteDbRC = (new SiteDatabaseService)->parseUpsertIncomingVisitorRecord($visitorRecord, $site_id);

            if (! $siteDbRC->operation_successful) {
                return $this->return_failure('Upsert to Site DB failed. '.$siteDbRC->error_message);
            }
        }

        if (! empty($incomingRecord->deleted_at) || $_sn_mark_as_deleted) {
            $deletedRecordCount = 1;
        }

        $visitorRecord->load('grant_record');

        H::enqueueP2pMessage(
            MessageTypes::visitor_record,
            payload: $visitorRecord->only('record_json', 'signature', 'signer_verification_key_base64'),
            site_id: $site_id,
            priority: 20,
        );

        H::savePassiveTriggersForSite($site_id);

        $message = $visitorRecord->record_json;
        if (! empty($incomingRecord->deleted_at) || $_sn_mark_as_deleted) {
            $type = SSETypes::deleted_visitor_record;
        } else {
            $type = SSETypes::updated_visitor_record;
        }
        SiteSSEFacade::notify(message: $message, type: $type, event: 'message', site_id: $site_id);

        $result = [
            'insertedCount' => (int) ($newRecordCount ?? 0),
            'updatedCount' => (int) ($updatedRecordCount ?? 0),
            'deletedCount' => (int) ($deletedRecordCount ?? 0),
        ];

        $siteDbRC = $request->debug ? $siteDbRC : [];

        return $this->return_success(data: [SysProps::_sn_entity_id => $incomingRecord->{SysProps::_sn_entity_id}],
            metadata: $result, debug_data: $siteDbRC ?? []);
    }
}
