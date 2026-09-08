<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocalTrackerInfoHashPeer extends Model
{
    public $fillable = [
        'info_hash',
        'ip',
        'port',
        'peer_url',
        'peer_id',
        'last_announce',
        'created_at',
        'updated_at',
        'archived_at',
        'last_event',
        'downloaded',
        'uploaded',
        'left',
    ];
}
