<?php

namespace App\Services;

use App\Dicts\SysProps;
use App\Http\Consts;
use App\Http\Controllers\Controller;
use App\Http\H;
use Carbon\Carbon;
use Throwable;

class IntegrityService extends Controller
{
    /**
     * Performs various checks:
     * - json parsable
     * - Size
     * - entity_id present
     * - visitor_id prop match record id visitor - maybe
     * - created at from record id correct date
     * - created_at present
     * - updated date past creation date from record id
     * - updated date past creation date
     * - grants have grantee_visitor_id
     */
    public function isRecordConsistent(string $record_json, bool $isVisitors, string $signer_verification_key_base64): bool
    {
        try {
            if (strlen($record_json) > Consts::recordJsonMaxSizeBytes) {
                return false;
            }

            $incomingRecord = json_decode($record_json);

            if ($isVisitors) {

                /* Entity Id missing */
                if (! property_exists($incomingRecord, SysProps::_sn_entity_id)) {
                    /* Entity Id in incorrect format */
                    info('isRecordConsistent _sn_entity_id missing');

                    return false;
                }

                $entity_id_array = explode('_', $incomingRecord->{SysProps::_sn_entity_id});

                $createdFromEntityId = $entity_id_array[0];
                // $createdFromEntityId = explode('_', $incomingRecord->{SysProps::_sn_entity_id})[0];

                /* Visitor ID exists */
                $visitor_id = $entity_id_array[1];

                if (! $visitor_id) {
                    info('isRecordConsistent No visitor_id');

                    return false;
                }

                if (! H::isValidHashedId($visitor_id)) {
                    info('Visitor ID is not a valid Hashed ID');

                    return false;
                }

                /* Created matches createdFromEntityId */
                if ($createdFromEntityId === $incomingRecord->{SysProps::_sn_entity_created}) {
                    info('isRecordConsistent Created matches createdFromEntityId');

                    return false;
                }


                /* Created String not a correct date */
                try {
                    Carbon::createFromFormat(Consts::visitorDataDateIdFormat, $createdFromEntityId);
                } catch (Throwable $th) {
                    info('isRecordConsistent Created String not a correct date');

                    return false;
                }

                /* Missing Updated */
                if (! property_exists($incomingRecord, SysProps::_sn_entity_updated)) {
                    info('isRecordConsistent Missing Updated');

                    return false;
                }



                /* Created after updated */
                if ($createdFromEntityId > $incomingRecord->{SysProps::_sn_entity_updated} - 1000) {
                    info('isRecordConsistent Created after updated');

                    return false;
                }

                /* Modified String not a correct date */
                try {
                    Carbon::createFromFormat(Consts::visitorDataDateIdFormat, $incomingRecord->{SysProps::_sn_entity_updated});
                } catch (Throwable $th) {
                    info('isRecordConsistent Modified String not a correct date');

                    return false;
                }

                /* Modified String not in future */
                $nowMicroTimeString = now()->format(Consts::visitorDataDateIdFormat);
                if ($nowMicroTimeString < $incomingRecord->{SysProps::_sn_entity_updated} - 1000) {
                    info('isRecordConsistent Modified String not in future');

                    return false;
                }

                /* Created String not in future */
                if ($nowMicroTimeString < $createdFromEntityId - 1000) {
                    info('isRecordConsistent Created String not in future');

                    return false;
                }

                /* Grant has to have grantee_visitor_id */
                if ($incomingRecord->{SysProps::_sn_is_grant_record} ?? false) {
                    if (! property_exists($incomingRecord, SysProps::_sn_grantee_visitor_id)) {
                        info('isRecordConsistent Grant record is missing grantee_visitor_id');

                        return false;
                    }
                }

                /* Signer Id and Signer Verification Key mismatch */
                $signer_verification_key_bytes = base64_decode($signer_verification_key_base64);

                $signer = H::verKeyToHashedId($signer_verification_key_bytes);
                if ($signer !== $incomingRecord->{SysProps::_sn_signer}) {
                    info('isRecordConsistent Signer\'s Id and Signer Verification Key mismatch ');

                    return false;
                }

            } else {

                $site_id = $incomingRecord->site_id;

                if (! H::isValidHashedId($site_id)) {
                    info('Site ID is not a valid Hashed ID');

                    return false;
                }

                /* Site Definition - Hashed ID and Signer Verification Key mismatch */
                $signer_verification_key_bytes = base64_decode($signer_verification_key_base64);
                $site_id_from_hash = H::verKeyToHashedId($signer_verification_key_bytes);
                if ($site_id_from_hash !== $site_id) {
                    info('isRecordConsistent Hashed ID and Signer Verification Key mismatch');

                    return false;
                }
            }

        } catch (Throwable $th) {
            info($th->getLine().' '.$th->getFile().' '.$th->getMessage().' '.$record_json);
            info('isRecordConsistent Throwable');

            return false;
        }

        return true;
    }
}
