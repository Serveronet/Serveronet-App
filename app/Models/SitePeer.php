<?php

namespace App\Models;

use App\Http\H;
use Illuminate\Database\Eloquent\Model;

/** Uses Archiving as soft deletes */
class SitePeer extends Model
{
    public static array $publicProperties = [
        'site_id',
        'client_address',
    ];

    public $fillable = [
        'client_address',
        'site_id',
        'source',
    ];

    public function peer()
    {
        return $this->hasOne(Peer::class, 'client_address', 'client_address');
    }

    public function scopeWhereSiteId($query, $site_id)
    {
        return $query->where('site_id', $site_id);
    }

    public function scopeWithoutSelf($query)
    {
        return $query->whereNotIn('client_address', H::getSelfAddresses());
    }

    public function scopeWithoutArchived($query)
    {
        return $query->whereNull('archived_at');
    }
}
