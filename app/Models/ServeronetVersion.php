<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServeronetVersion extends Model
{
    public static $publicProperties = [
        'version',
        'major',
        'minor',
        'file_name',
        'record_json',
        'sha256',
        'type',
        'channel',
        'signature',
    ];
}
