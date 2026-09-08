<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrowdQuery extends Model
{
    use HasFactory;

    public static $publicProperties = [
        'query_id',
        'action_type',
        'query_parameters',
        'site_id',
        'ttl',
    ];
}
