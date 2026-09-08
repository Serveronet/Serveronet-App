<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteDefinition extends Model
{
    use HasFactory;

    public static $publicProperties = [
        'site_id',
        'signer_verification_key_base64',
        'title',
        'site_config_json',
        'description',
        'file_listing_json',
        'entity_created',
        'files_count',
        'files_size',
        'record_json',
        'signature',
    ];

    protected $fillable = [
        'entity_created',
        'download_state',
        'title',
        'description',
        'files_count',
        'files_size',
        'visitor_records_preliminary_total_size',
        'visitor_resources_preliminary_total_size',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id', 'site_id');
    }

    public function scopeWhereSiteId($query, $site_id)
    {
        return $query->where('site_id', $site_id);
    }
}
