<?php

namespace App\Console\Commands;

use App\Http\Controllers\BackgroundProcessingController;
use Illuminate\Console\Command;

class ResourcesMaintenance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sn:resources-maintenance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add to cache existing files and remove resources without files stored';

    /**
     * php artisan sn:resources-maintenance
     */
    public function handle()
    {
        dump('Started...');
        (new BackgroundProcessingController)->resourcesMaintenance(pfm: true);
        dd('Completed');
    }
}
