<?php

namespace App\Models;

use App\Http\H;
use Illuminate\Database\Eloquent\Model;

/** Does not use soft deletes */
class Peer extends Model
{
    public static $publicProperties = [
        'client_address',
    ];

    public $fillable = [
        'client_address',
        'is_passive_client',
    ];

    public function scopeWithoutSelf($query)
    {
        return $query->whereNotIn('client_address', H::getSelfAddresses());
    }

    public function scopeWithoutSender($query, $sender_address)
    {
        return $query->where('client_address', '<>', $sender_address);
    }

    public function scopeOrderByLastConnectedDesc($query)
    {
        return $query->orderByDesc('last_connected_at');
    }

    public function scopeOrderByReputationDesc($query)
    {
        return $query->orderByDesc('reputation');
    }
}
