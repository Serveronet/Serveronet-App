<?php

namespace App\Http\Controllers;

use App\Dicts\SettingIds;
use App\Http\H;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ClientManagerController extends Controller
{
    public function signalMainLoopStop(Request $request)
    {
        try {
            $request->validate([
                'target' => 'nullable', 'string', 'max:1024',
            ]);
        } catch (Throwable $th) {
            $message = $th->getMessage();
            $status_code = 400;

            return response(view('conditionalXXX', compact('message', 'status_code')), status: $status_code);
        }
        $target = $request->target ?? 'admin_dashboard';
        H::setSettingValue(SettingIds::main_loop_stop_signaled, true);

        return redirect(domainRoute($target));
    }

    public function mainLoopRequester(Request $request)
    {
        try {
            $request->validate([
                'target' => 'nullable', 'string', 'max:1024',
            ]);
        } catch (Throwable $th) {
            $message = $th->getMessage();
            $status_code = 400;

            return response(view('conditionalXXX', compact('message', 'status_code')), status: $status_code);
        }
        H::setSettingValue(SettingIds::main_loop_stop_signaled, false);

        $target = domainRoute($request->target ?? 'admin_dashboard');

        return view('continue', ['target' => $target]);
    }

    public function startMainLoopIfRequired()
    {
        $main_loop_stop_signaled = H::getSettVal(SettingIds::main_loop_stop_signaled);
        if ($main_loop_stop_signaled) {
            H::pfm('Not starting as stop is signaled');
            return;
        }

        $last_heartbeat_ts = H::getSettVal(SettingIds::last_heartbeat_ts);
        $last_heartbeat_ts = Carbon::parse($last_heartbeat_ts);

        if ($last_heartbeat_ts->diffInMinutes(now()) <= 2) {
            H::pfm('Last alive stamp in range  - not starting. Last_heartbeat_ts: '.$last_heartbeat_ts);
            return;
        } else {
            H::pfm('Starting Loop thread. Please wait.');

            $requestConfigSet = [];
            $requestConfigSet['thread_id'] = Str::random(3);
            $requestConfigSet['maintainer_id'] = null;

            H::dispatchInternalAsync('loop_maintainer', $requestConfigSet, 2);
        }
    }

    public static function loopMaintainer()
    {
        $requestConfigSet = H::unwrapRequestConfigSet();
        if (! H::isInternalCallCurrent($requestConfigSet)) {
            info('Outdated or spoofed internal request');

            return;
        }
        $internalRequestId = H::startInteralRequestReporting(__FUNCTION__);

        $thread_id = $requestConfigSet['thread_id'];
        $maintainer_id = $requestConfigSet['maintainer_id'];

        if (empty($maintainer_id)) {
            $maintainer_id = Str::random(4);
        }

        if (H::getSettVal(SettingIds::main_loop_stop_signaled)) {
            info('main_loop_stop_signaled - ending');

            return 'end';
        }

        H::setSettingValue(SettingIds::last_heartbeat_ts, now());

        /* Sleep */
        info('Start Sleep for: 60');
        sleep(60);
        info('End Sleep for 60');

        /* Dispatch fork */
        info('loopMaintainer before Http');
        try {
            $requestConfigSet = [];
            $requestConfigSet['thread_id'] = $thread_id;
            $requestConfigSet['maintainer_id'] = $maintainer_id;
            H::dispatchInternalAsync('loop_maintainer', $requestConfigSet, 2);
        } catch (Throwable $th) {
        }
        info('loopMaintainer After Http');

        /* Execute actions - ensures that crashing actions won't break the loop */
        info('Start Artisan::call("schedule:run")');
        Artisan::call('schedule:run');
        info('End Artisan::call("schedule:run")');

        H::endInteralRequestReporting($internalRequestId);

        return;
    }

    public static function sqliteDatabaseBackup($pfm = false)
    {
        if (DB::getDefaultConnection() === 'sqlite') {
            $dbFile = database_path().'/database.sqlite';
            $dbBackupFile = database_path().'/Backup_database.sqlite';

            copy($dbFile, $dbBackupFile);

            H::pfm($dbBackupFile, pfm: $pfm);
        } else {
            H::pfm('Sqlite DB is not used. Backup manually.', pfm: $pfm);
        }
    }
}
