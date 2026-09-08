<?php

namespace App\Services;

use App\Dicts\DataTypes;
use App\Dicts\SettingDataTypes;
use App\Dicts\SettingIds;
use App\Http\H;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Controller;
use App\Models\CachedResource;
use App\Models\InternalRequest;
use App\Models\LocalTrackerInfoHashPeer;
use App\Models\Peer;
use App\Models\PeerReplicationSession;
use App\Models\ReplicationSession;
use App\Models\Site;
use App\Models\SiteConfig;
use App\Models\SiteDefinition;
use App\Models\SitePeer;
use App\Models\Tracker;
use App\Models\Visitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use stdClass;

class SiteConfigService extends Controller
{
    public function getExampleSiteConfig(Request $request)
    {
        $siteConfigShell = (array) new SiteConfig();
        $siteConfig = new \stdClass;

        foreach ($siteConfigShell as $key => $value) {
            $siteConfig->$key = $value['exampleValue'];
        }

        return response()->json($siteConfig, 200, [], JSON_PRETTY_PRINT);
    }

    public static function getDefaultSiteConfig(): object
    {
        $siteConfigShell = (array) new SiteConfig();
        $siteConfig = new stdClass();

        foreach ($siteConfigShell as $key => $value) {
            $siteConfig->$key = $value['defaultValue'];
        }

        return $siteConfig;
    }

    public static function getUpdatedSiteConfig(array $siteConfig, $returnAsArray = false)
    {
        $updatedSiteConfig = (new SiteConfigService)->getDefaultSiteConfig();
        
        foreach ($updatedSiteConfig as $key => $value) {
            if (isset($siteConfig[$key]))
            $updatedSiteConfig->$key = $siteConfig[$key];
            
            if (gettype($updatedSiteConfig->$key) == SettingDataTypes::string)
            $updatedSiteConfig->$key = trim($updatedSiteConfig->$key);
        }

        return json_decode(json_encode($updatedSiteConfig), $returnAsArray);
    }

    /**
    * Fills with defaults and sets _sn_site_id 
    */
    public static function getSiteConfig(Site $site): object|null
    {
        if (! $site->most_recent_site_definition)
        return null;

        $siteConfigJson = $site->most_recent_site_definition->site_config_json;
        
        $siteConfig = json_decode($siteConfigJson, true);
        $siteConfig = (new SiteConfigService)->getUpdatedSiteConfig($siteConfig);

        $siteConfig->_sn_site_id = $site->site_id;

        return $siteConfig;
    }
}