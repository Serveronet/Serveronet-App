<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class VisitorRecord extends Model
{
    use SoftDeletes;

    public static $publicProperties = [
        'entity_id',
        'site_id',
        'visitor_id',
        'visitor_verification_key_base64',
        'signer',
        'signer_verification_key_base64',
        'grantee_visitor_id',

        'entity_created',
        'entity_updated',
        'entity_deleted',

        'created_at',
        'updated_at',
        'deleted_at',

        'record_json',
        'signature',
    ];


    public function grant_record(): HasOne
    {
        return $this->hasOne(VisitorRecord::class, 'grantee_visitor_id', 'visitor_id')
            ->select('record_json', 'signature', 'grantee_visitor_id', 'visitor_id', 'signer_verification_key_base64')
            ->orderByDesc('entity_updated')->whereNull('entity_deleted');
    }
}
