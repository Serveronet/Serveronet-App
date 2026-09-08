<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BackgroundScheduleExecution extends Model
{
    use HasFactory;

    protected $fillable = [
        'tag',
        'created_at',
        'started_at',
        'ended_at',
        'taken_seconds'
    ];
}
