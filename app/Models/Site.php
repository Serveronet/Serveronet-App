<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    protected $fillable = [
        'site_id',
        'published_at',
        'created_at',
        'updated_at',
        'description',
    ];

    public static $publicProperties = [
        'site_id',
        'published_at',
        'created_at',
        'updated_at',
        'description',
    ];

    public function site_definitions()
    {
        return $this->hasMany(SiteDefinition::class, 'site_id', 'site_id');
    }

    public function list_entries()
    {
        return $this->hasMany(ListProviderEntry::class, 'content', 'site_id');
    }

    public function site_peers()
    {
        return $this->hasMany(SitePeer::class, 'site_id', 'site_id');
    }

    public function most_recent_site_definition()
    {
        return $this->hasOne(SiteDefinition::class, 'site_id', 'site_id')
            ->orderBy('download_state')
            ->orderByDesc('entity_created');
    }

    public function scopePublishedOnly($query)
    {
        return $query->where('is_published', 1);
    }

    public function scopeWhereSiteId($query, $site_id)
    {
        return $query->whereIn('site_id', (is_array($site_id) ? $site_id : [$site_id]));
    }
}
