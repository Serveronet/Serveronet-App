<?php

namespace App\Http\Controllers;

use App\Dicts\SettingIds;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\PortForwardController;
use App\Http\H;
use App\Models\Peer;
use App\Models\ServeronetVersion;
use App\Services\PQCryptoService;
use App\Services\SiteDatabaseService;
use App\Services\TorrentService;
use Base32\Base32;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class PlaygroundController extends Controller
{
    public function test(Request $request) 
    {

    }
}
