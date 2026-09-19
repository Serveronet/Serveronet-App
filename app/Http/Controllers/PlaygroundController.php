<?php

namespace App\Http\Controllers;

use App\Dicts\PublishedVersionsChannels;
use App\Dicts\PublishedVersionsTypes;
use App\Dicts\SettingIds;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\PortForwardController;
use App\Http\H;
use App\Models\Peer;
use App\Models\ServeronetVersion;
use App\Models\SiteDefinition;
use App\Models\VisitorResource;
use App\Services\PQCryptoService;
use App\Services\SiteDatabaseService;
use App\Services\TorrentService;
use App\Support\ContentTypeResolver;
use Base32\Base32;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Spatie\Url\Url;

class PlaygroundController extends Controller
{
    public function test(Request $request) 
    {

    }
}
