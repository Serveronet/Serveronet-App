<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CachedResource extends Model
{
    protected $fillable = [
        'sha256',

    ];

    public static $publicProperties = [
        'sha256',
        'ipfs_hash',
        'file_size',
    ];

    public function scopeWhereSha256($query, $sha256)
    {
        return $query->where('sha256', $sha256);
    }
}
