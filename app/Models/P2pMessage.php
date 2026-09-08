<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class P2pMessage extends Model
{
    use HasFactory;

    public $sender_address;

    public static $publicProperties = [
        'message_id',
        'type',
        'json_payload',
        'site_id',
        'sender_address',
    ];

    protected $fillable = [
        'message_id',
        'type',
        'json_payload',
    ];
}
