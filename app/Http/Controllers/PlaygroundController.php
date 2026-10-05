<?php

namespace App\Http\Controllers;

use App\Dicts\CachePrefixes;
use App\Dicts\PublishedVersionsChannels;
use App\Dicts\PublishedVersionsTypes;
use App\Dicts\SettingIds;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\PortForwardController;
use App\Http\H;
use App\Models\Peer;
use App\Models\ServeronetVersion;
use App\Models\Site;
use App\Models\SiteDefinition;
use App\Models\VisitorRecord;
use App\Models\VisitorResource;
use App\Services\PeerMixService;
use App\Services\PQCryptoService;
use App\Services\SiteDatabaseService;
use App\Services\TorrentService;
use App\Support\ContentTypeResolver;
use Base32\Base32;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Url\Url;
use Illuminate\Support\Facades\DB;

class PlaygroundController extends Controller
{
    public function test(Request $request) 
    {
       

    }
}
