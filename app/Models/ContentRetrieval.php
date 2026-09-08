<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentRetrieval extends Model
{
    public static $publicProperties = [
        'retrieval_id',
        'action_type',
        'sha256',
        'mime_type',
        'site_id',
        'file_size',
        'ipfs_hash',
        'ttl',
    ];
}
