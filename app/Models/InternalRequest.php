<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalRequest extends Model
{
    protected $fillable = [
        'route',
        'request_id',
        'metadata',
        'started_at',
        'ended_at',
        'taken_seconds',
    ];
}
