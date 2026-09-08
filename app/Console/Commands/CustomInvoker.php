<?php

namespace App\Console\Commands;

use App\Http\Controllers\CustomInvokerController;
use Illuminate\Console\Command;

class CustomInvoker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sn:custom-invoker {method_name?} {params?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Custom code invoker. Pass mathod name to execute. Place code in Controllers/custom_code.php.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $method_name = $this->argument('method_name') ?? 'invoke';
        $params = $this->argument('params');
        (new CustomInvokerController())->importCustomCode($method_name, $params);
    }
}
