<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CachedDomain extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain',
        'site_id',
    ];
}
