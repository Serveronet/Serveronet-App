<?php

namespace App\Console;

use App\Http\Controllers\BackgroundProcessingController;
use App\Http\Controllers\UpdaterController;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Http\Request;
use Throwable;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        foreach (BackgroundProcessingController::getBackgroundSchedulesConfig() as $key => $scheduleEntry) {
            $tag = $scheduleEntry->tag;
            $method_name = $scheduleEntry->method_name;
            $execution_hour = $scheduleEntry->execution_hour;
            $execution_minute = $scheduleEntry->execution_minute;
            if (! $scheduleEntry->cron) {
                $scheduleEntry->cron = UpdaterController::mapMethodToCron($scheduleEntry['method_name']);;
                $scheduleEntry->save();
            }
            $cron = $scheduleEntry->cron;
            
            if ($scheduleEntry->is_one_time && $execution_minute < now()->minute) {
                $scheduleEntry->delete();
            }

            $schedule->call(
                function () use ($tag) {
                    info('Schedule: '.$tag);        
                    $request = new Request();
                    $request->merge(['tag' => $tag, 'pfm' => false]);

                    try {
                        (new BackgroundProcessingController())->executeClientAction($request);
                    } catch (Throwable $th) {
                        info($th->getMessage().' '.$th->getFile().' '.$th->getLine());
                    }
                }
            )->cron($cron)->description($tag.' '.$method_name);
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
