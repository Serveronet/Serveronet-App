<?php

namespace App\Services;

use App\Dicts\CachePrefixes;
use App\Dicts\SysProps;
use App\Http\Controllers\QueryController;
use App\Http\H;
use App\Models\ResultContainer;
use App\Models\ResultContainerCanPostToTable;
use App\Models\ResultContainerIsSiteAdmin;
use App\Models\ResultContainerSignerRights;
use App\Models\Site;
use App\Models\VisitorRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PermissionService
{
    public static function hasSignerRightsToTable($signer, $table, $siteConfig): ResultContainerCanPostToTable
    {

        $rc = new ResultContainerCanPostToTable;
        $rc->operation_successful = true;

        $site_id = $siteConfig->_sn_site_id;
        if ($site_id === $signer) {
            $rc->can_post_to_table = true;
            $rc->posts_to_table_reason = 'Signer is a Site Owner';

            return $rc;
        }

        $site_Admin_Signers = preg_split('/[ :;,]+/', $siteConfig->site_Admin_Signers, -1, PREG_SPLIT_NO_EMPTY);

        if (in_array($signer, $site_Admin_Signers)) {
            $rc->can_post_to_table = true;
            $rc->posts_to_table_reason = 'Signer is a Site Admin';

            return $rc;
        }

        $rcGrant = PermissionService::getGrantRecord($signer, $table, $site_id);

        if (! $rcGrant->operation_successful) {
            $rc->can_post_to_table = false;
            $rc->posts_to_table_reason = 'Failed to get grant record. '.$rcGrant->error_message;
            $rc->error_message = $rcGrant->error_message;

            return $rc;
        }

        if ($rcGrant->data->first()) {
            $rc->can_post_to_table = true;
            $rc->posts_to_table_reason = 'Grant record for Signer found for Records';

            return $rc;
        }

        $rc->can_post_to_table = false;
        $rc->posts_to_table_reason = 'Signer has no rights to the table';

        return $rc;
    }

    public static function hasSignerRightsToVisitorRecord($incomingRecord, $siteConfig): ResultContainerSignerRights
    {
        $rc = new ResultContainerSignerRights;
        $rc->operation_successful = true;
        $site_id = $siteConfig->_sn_site_id;
        $idVisitorId = explode('_', $incomingRecord->{SysProps::_sn_entity_id})[1];
        if ($idVisitorId === $incomingRecord->{SysProps::_sn_signer}) {
            $rc->has_signer_rights = true;
            $rc->has_signer_rights_reason = 'Signer is the original visitor';

            return $rc;
        }

        if ($site_id === $incomingRecord->{SysProps::_sn_signer}) {
            $rc->has_signer_rights = true;
            $rc->has_signer_rights_reason = 'Signer is a Site Owner';

            return $rc;
        }

        $site_Admin_Signers = preg_split('/[ :;,]+/', $siteConfig->site_Admin_Signers, -1, PREG_SPLIT_NO_EMPTY);

        if (in_array($incomingRecord->{SysProps::_sn_signer}, $site_Admin_Signers)) {
            $rc->operation_successful = true;
            $rc->has_signer_rights = true;
            $rc->has_signer_rights_reason = 'Signer explicit in Admins';

            return $rc;
        }

        $rc->has_signer_rights = false;
        $rc->has_signer_rights_reason = 'No rights to record';

        return $rc;
    }

    public static function isSiteAdmin(string $visitor_id, object $siteConfig): ResultContainerIsSiteAdmin
    {
        $rc = new ResultContainerIsSiteAdmin;
        $rc->operation_successful = true;
        $rc->is_site_admin = false;
        $site_id = $siteConfig->_sn_site_id;

        if ($site_id === $visitor_id) {
            $rc->is_site_admin = true;
            $rc->is_site_admin_reason = 'Visitor is a Site Owner';

            return $rc;
        }

        $site_Admin_Signers = preg_split('/[ :;,]+/', $siteConfig->site_Admin_Signers, -1, PREG_SPLIT_NO_EMPTY);

        if (in_array($visitor_id, $site_Admin_Signers)) {
            $rc->is_site_admin = true;
            $rc->is_site_admin_reason = 'Visitor explicitly a site admin';

            return $rc;
        }

        return $rc;
    }

    public static function hasSignerRightsToVisitorResource($incomingRecord, $siteConfig): ResultContainerSignerRights
    {
        info('hasSignerRightsToVisitorResource');
        $rc = new ResultContainerSignerRights;
        $rc->operation_successful = true;

        try {
            $idVisitorId = explode('_', $incomingRecord->{SysProps::_sn_entity_id})[1];
        } catch (\Throwable $th) {
            $rc->operation_successful = false;
            $rc->error_message = 'Invalid entity_id';

            return $rc;
        }

        if ($idVisitorId === $incomingRecord->{SysProps::_sn_signer}) {
            $rc->has_signer_rights = true;
            $rc->has_signer_rights_reason = 'Signer is a Record Creator';

            return $rc;
        }

        $site_id = $siteConfig->_sn_site_id;

        if ($site_id === $incomingRecord->{SysProps::_sn_signer}) {
            $rc->has_signer_rights = true;
            $rc->has_signer_rights_reason = 'Signer is a Site Owner';

            return $rc;
        }

        $site_Admin_Signers = preg_split('/[ :;,]+/', $siteConfig->site_Admin_Signers, -1, PREG_SPLIT_NO_EMPTY);
        if (! empty($site_Admin_Signers)) {
            if (in_array($incomingRecord->{SysProps::_sn_signer}, $site_Admin_Signers)) {
                $rc->has_signer_rights = true;
                $rc->has_signer_rights_reason = 'Signer is explicitly a Site Admin';

                return $rc;
            }
        }

        $rc->has_signer_rights = false;

        return $rc;
    }

    public static function getGrantRecord($visitor_id, $table, $site_id): ResultContainer
    {
        $rc = new ResultContainer;
        $rc->operation_successful = true;

        $builder = VisitorRecord::where([
            ['site_id', $site_id],
            ['is_grant_record', true],
            ['grantee_visitor_id', $visitor_id],
            ['table', $table],
        ])->orderByDesc('entity_updated')->select(VisitorRecord::$publicProperties);

        $grantVisitorRecord = H::getCached('grantVisitorRecord'.$site_id.'_'.$visitor_id.'_'.$table,
            $builder
        );

        $site = H::getCached(CachePrefixes::site_.$site_id, Site::whereSiteId($site_id));
        $is_to_be_hosted = $site->is_to_be_hosted;

        if (! $site->is_to_be_hosted) {
            $grantVisitorRecordCachedTSKey = CachePrefixes::ts_grant_visitor_record_.$site_id.'_'.$visitor_id.'_'.$table;
            $seconds = 60 * 60;
            $grantVisitorRecordCachedTS = Carbon::parse(Cache::remember($grantVisitorRecordCachedTSKey, $seconds, function () {
                return now()->toDateTimeString();
            }));
            $isGrantOld = $grantVisitorRecordCachedTS->diffInMinutes(now()) > 60;

            if ($isGrantOld && ! $is_to_be_hosted) {
                $g = $builder->first();
                if ($g)
                $g->delete();
            }
        }

        if ($grantVisitorRecord) {
            $rc->data = $grantVisitorRecord;

            return $rc;
        }

        $grantWhere = [
            ['site_id', '=', $site_id],
            ['is_grant_record', '=', true],
            ['grantee_visitor_id', '=', $visitor_id],
            ['table', '=', $table],
        ];

        $request = new Request;
        $q = new \stdClass;
        $q->query_parameters = [
            'in_visitor_records_table' => true,
            'table' => $table,
            'where' => $grantWhere,
            'debug' => true,
        ];
        $j = json_encode($q);
        $request->setMethod('POST');
        $request->merge(json_decode($j, true));
        $request->merge(['site_id' => $site_id]);

        $request->merge(['envelope_only' => false]);
        $request->merge(['response_as_result_container' => true]);
        $rc = ((new QueryController)->queryEndpoint($request));

        return $rc;
    }
}
