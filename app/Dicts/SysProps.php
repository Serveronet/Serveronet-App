<?php

namespace App\Dicts;

class SysProps
{
    const sys_props_hidden_from_site_db = [
        self::_sn_site_id,
        self::_sn_visitor_verification_key_base64,
        self::_sn_is_site_admin_locked,
        self::_sn_grantee_visitor_id,
        self::grantee_visitor_id,
        self::_sn_is_grant_record,
        self::is_grant_record,
    ];

    const sys_props_envelope = [
        self::_sn_record_json,
        self::_sn_signer_verification_key_base64,
        self::_sn_signature,
        self::record_json,
        self::signer_verification_key_base64,
        self::signature,
    ];

    const _sn_entity_id = '_sn_entity_id';

    const _sn_record_json = '_sn_record_json';

    const _sn_signer = '_sn_signer';

    const _sn_visitor_verification_key_base64 = '_sn_visitor_verification_key_base64';

    const _sn_signer_verification_key_base64 = '_sn_signer_verification_key_base64';

    const _sn_signature = '_sn_signature';

    const _sn_visitor_id = '_sn_visitor_id';

    const _sn_entity_created = '_sn_entity_created';

    const _sn_entity_updated = '_sn_entity_updated';

    const _sn_entity_deleted = '_sn_entity_deleted';

    const _sn_table = '_sn_table';

    const _sn_site_id = '_sn_site_id';

    const _sn_mark_as_deleted = '_sn_mark_as_deleted';

    const _sn_visitor_resource_upload = '_sn_visitor_resource_upload';

    const grantee_visitor_id = 'grantee_visitor_id';

    const _sn_grantee_visitor_id = '_sn_grantee_visitor_id';

    const is_grant_record = 'is_grant_record';

    const _sn_is_grant_record = '_sn_is_grant_record';

    const _sn_is_site_admin_locked = '_sn_is_site_admin_locked';

    const record_json = 'record_json';

    const signer_verification_key_base64 = 'signer_verification_key_base64';

    const signature = 'signature';
}
