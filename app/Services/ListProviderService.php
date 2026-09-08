<?php

namespace App\Services;

use App\Dicts\ListProviderTypes;
use App\Http\Controllers\Controller;
use App\Models\ListProviderEntry;
use App\Models\Site;
use Illuminate\Support\Collection;

class ListProviderService extends Controller
{
    public function getBannedSiteIds(): Collection
    {
        $authorityBannedSites = ListProviderEntry::where([
            ['data_type', ListProviderTypes::banned_sites],
        ])->select('content')->get();
        $authorityBannedSiteIDs = [];
        foreach ($authorityBannedSites as $key => $site) {
            array_push($authorityBannedSiteIDs, $site->content);
        }

        return collect($authorityBannedSiteIDs);
    }

    public function getBlockListSites(): Collection
    {
        $sites = Site::select('site_id')->publishedOnly()
            ->with('most_recent_site_definition')->get();
        $blockListSites = collect();

        $sites->each(function ($site) use ($blockListSites) {
            $site_List_Providers = json_decode($site->most_recent_site_definition?->site_config_json)
                ->list_Providers ?? null;
            if (! empty($site_List_Providers)) {
                foreach ($site_List_Providers as $key => $site_List_Provider) {
                    if (in_array($site_List_Provider->data_type, [ListProviderTypes::banned_sites])) {
                        $site->site_List_Providers = $site_List_Providers;
                        $blockListSites->push($site);
                    }
                }
            }
        });

        return $blockListSites;
    }
}
