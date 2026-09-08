<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tracker extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'trackers';

    /**
     * The database primary key value.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'url',
        'last_connected_at',
        'last_error'
    ];
}
