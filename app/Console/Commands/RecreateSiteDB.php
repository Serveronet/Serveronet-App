<?php

namespace App\Console\Commands;

use App\Services\SiteDatabaseService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class RecreateSiteDB extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sn:recreate-site-db {site_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'RecreateSiteDB sn:recreate-site-db {site_id}';

    /**
     * php artisan sn:recreate-site-db site_id
     */
    public function handle()
    {
        $request = new Request();
        $request->merge([
            'site_id' => $this->argument('site_id'),
        ]);
        $rc = (new SiteDatabaseService)->recreateSiteDb($request);
        dd($rc);
    }
}
