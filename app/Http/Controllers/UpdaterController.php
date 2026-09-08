<?php

namespace App\Http\Controllers;

use App\Dicts\DocsMapping;
use App\Dicts\PublishedVersionsChannels;
use App\Dicts\PublishedVersionsPlatforms;
use App\Dicts\PublishedVersionsSources;
use App\Dicts\PublishedVersionsTypes;
use App\Dicts\SettingIds;
use App\Http\Consts;
use App\Http\H;
use App\Models\BackgroundSchedule;
use App\Services\PQCryptoService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use ZipArchive;

class UpdaterController extends Controller
{
    public static $updateVariants = [

        'Server: Central | Channel: Prod' => ['source' => PublishedVersionsSources::central_server, 'andUpdate' => true,
            'channel' => PublishedVersionsChannels::prod, 'css' => 'success'],

        'Server: Central | Channel: Dev' => ['source' => PublishedVersionsSources::central_server, 'andUpdate' => true,
            'channel' => PublishedVersionsChannels::dev, 'css' => 'primary'],

        'Server: Dev | Channel: Prod' => ['source' => PublishedVersionsSources::dev_server, 'andUpdate' => true,
            'channel' => PublishedVersionsChannels::prod, 'css' => 'light-outline opacity-25'],

        'Server: Dev | Channel: Dev' => ['source' => PublishedVersionsSources::dev_server, 'andUpdate' => true,
            'channel' => PublishedVersionsChannels::dev, 'css' => 'light-outline opacity-25'],
    ];

    public function checkAndUpdateClientFromCentral($request = null)
    {
        $request = $request ?? request();
        $request->validate([
            'channel' => 'string|min:3|max:255',
            'source' => 'string|min:3|max:255',
            'andUpdate' => 'boolean',
            'forceUpdate' => 'boolean',
            'pfm' => 'boolean',
        ]);

        $pfm = $request->pfm ?? false;

        $source = $request->source ?? PublishedVersionsSources::central_server;

        $channel = $request->channel ?? H::getSettVal(SettingIds::client_automatic_update_channel);

        $andUpdate = $request->andUpdate ?? true;
        $forceUpdate = $request->forceUpdate ?? false;

        H::echoHeaderWithViewport($pfm);

        if ($this->isUnupdatableClient($request)) {
            H::pfm('Unupdatable Client', pfm: $pfm);

            return;
        }

        $sendApiToken = false;

        switch ($source) {
            case PublishedVersionsSources::dev_server:
                $sendApiToken = true;
                $dev_custom_central_server_address = H::getSettVal(SettingIds::dev_custom_central_server_address);
                if (! $dev_custom_central_server_address) {
                    H::pfm('Custom Central Server Address not defined', pfm: $pfm);

                    return;
                }
                $centralServerAddress = $dev_custom_central_server_address;

                break;

            case PublishedVersionsSources::central_server:
                $centralServerAddress = Consts::centralServerAddress;
                break;

            default:
                $centralServerAddress = Consts::centralServerAddress;
                break;
        }

        $url = H::a($centralServerAddress).'api/v1/list_versions_endpoint';
        $client = H::setupClient(false);
        $options = [
            'timeout' => H::timeoutAdjust(false, 60),
            'prepare_ip' => true,
        ];
        H::prepareOptions($options, $url);

        $response = $client->get($url, $options);
        $body = (string) $response->getBody();
        $decodedBody = json_decode($body);
        $success = $decodedBody->success;
        if ($success) {
            $versions = $decodedBody->data;
            $mostRecentVersion = (collect($versions))->where('channel', $channel)
                ->where('type', PublishedVersionsTypes::client_update)->sortByDesc('version')->first();
            $mostRecentVersionNum = $mostRecentVersion->version;
            $ver = H::ver();

            $clientVersion = $ver;

            H::pfm('Client Version: '.$clientVersion, pfm: $pfm);
            H::pfm('Most Recent Version: '.$mostRecentVersionNum, pfm: $pfm);
            H::pfm('Source: '.$source.' | Channel: '.$channel.' | And Updating: '.tfyn($andUpdate).' | forceUpdate: '.tfyn($forceUpdate), pfm: $pfm);
            if ($clientVersion == $mostRecentVersionNum) {
                H::pfm('Version up to date', pfm: $pfm);
                H::pfm('Current version: '.H::ver(), pfm: $pfm);
                H::setSettingValue(SettingIds::newer_client_version_detected, false);
            } elseif ($clientVersion > $mostRecentVersionNum) {
                H::pfm('Client has newer version then server!', pfm: $pfm);
                H::pfm('Current version: '.H::ver(), pfm: $pfm);
                H::setSettingValue(SettingIds::newer_client_version_detected, false);
            } elseif ($clientVersion < $mostRecentVersionNum) {
                H::pfm('Upgrade possible. Initial version: '.$clientVersion, pfm: $pfm);
                H::setSettingValue(SettingIds::newer_client_version_detected, true);

                if (! $andUpdate) {
                    H::pfm('Not updating - Check only', pfm: $pfm);

                    return;
                }

                $effectiveUpdate = H::getSettVal(SettingIds::automatic_update_enabled) || $forceUpdate;

                if (! $effectiveUpdate) {
                    H::pfm('Automatic Update not enabled.', pfm: $pfm);

                    return;
                }
                H::pfm('Checking signer...', pfm: $pfm);

                $record_json = $mostRecentVersion->record_json;
                $signature = $mostRecentVersion->signature ?? null;

                /* Might be needed to be disabled with Github actions */
                $signerCheckEnabled = false;

                $properSignerMatched = false;
                if ($signerCheckEnabled) {
                    foreach (Consts::allowedVersionSignersVerificationKeysBase64 as $key => $verificationKeyBase64) {
                        $properSignerMatched = (new PQCryptoService)->isCryptoCorrectVerKey(
                            $verificationKeyBase64,
                            $record_json,
                            $signature
                        );
                        if ($properSignerMatched) {
                            break;
                        }
                    }

                    if (! $properSignerMatched) {
                        H::pfm('Signature verification failure.', pfm: $pfm);

                        return;
                    }
                }

                H::pfm('Starting update to version '.$mostRecentVersionNum, pfm: $pfm);

                H::pfm('Updating from Central server...', pfm: $pfm);

                if ($sendApiToken) {
                    $dev_api_token = config('sn.dev_api_token');
                } else {
                    $dev_api_token = null;
                }

                $url = $centralServerAddress.'download/'.$mostRecentVersion->file_name
                .'?api_token='.$dev_api_token;

                H::pfm('Url: '.$url, pfm: $pfm);
                $newVersionFilePath = base_path().'/new_version.zip';
                H::forceFlush();

                ini_set('max_execution_time', 600);
                H::pfm('Downloading', pfm: $pfm);

                $this->downloadFile($url, $newVersionFilePath);

                H::pfm('Downloaded', pfm: $pfm);

                $downloadedHash = hash_file('sha256', $newVersionFilePath);

                $expectedHash = $mostRecentVersion->sha256;
                H::pfm('Expected Hash: '.$expectedHash, pfm: $pfm);
                H::pfm('Hash of Downloaded: '.$downloadedHash, pfm: $pfm);
                if ($expectedHash !== $downloadedHash) {
                    H::pfm('Hash mismatch', pfm: $pfm);
                } else {
                    $this->commonClientUpdateActions($newVersionFilePath, pfm: $pfm);
                }
            }
        } else {
            H::pfm('No "success" response from the central server was received', pfm: $pfm);
        }
    }

    public function isUnupdatableClient(): bool
    {
        return (bool) config('sn.is_off_network_node');
    }

    public function checkBundleUpdateFromCentral($request = null)
    {
        $request = $request ?? request();
        $request->validate([
            'channel' => 'string|min:3|max:255',
            'source' => 'string|min:3|max:255',
            'pfm' => 'boolean',
        ]);

        $pfm = $request->pfm ?? false;

        H::echoHeaderWithViewport($pfm);

        $bundle_version = H::getBundleVersion();

        if (! $bundle_version) {
            H::pfm('Not a client bundle', pfm: $pfm);

            return;
        }

        $source = $request->source ?? PublishedVersionsSources::central_server;
        $channel = $request->channel ?? H::getSettVal(SettingIds::client_automatic_update_channel);

        switch ($source) {
            case PublishedVersionsSources::dev_server:

                $dev_custom_central_server_address = H::getSettVal(SettingIds::dev_custom_central_server_address);
                if (! $dev_custom_central_server_address) {
                    H::pfm('Custom Central Server Address not defined', pfm: $pfm);

                    return;
                }
                $centralServerAddress = $dev_custom_central_server_address;
                break;

            case PublishedVersionsSources::central_server:
                $centralServerAddress = Consts::centralServerAddress;
                break;

            default:
                $centralServerAddress = Consts::centralServerAddress;
                break;
        }

        $url = H::a($centralServerAddress).'api/v1/list_versions_endpoint';
        $client = H::setupClient(false);
        $options = [
            'timeout' => H::timeoutAdjust(false, 60),
            'prepare_ip' => true,
        ];
        H::prepareOptions($options, $url);
        $response = $client->get($url, $options);
        $body = (string) $response->getBody();

        $decodedBody = json_decode($body);
        $success = $decodedBody->success;
        if ($success) {
            $versions = $decodedBody->data;
            $versions = collect($versions);

            switch ($bundle_version->platform) {
                case PublishedVersionsPlatforms::linux_and_mac:
                    $bundle = PublishedVersionsTypes::linux_and_mac_client_bundle;
                    break;

                case PublishedVersionsPlatforms::windows:
                    $bundle = PublishedVersionsTypes::windows_client_bundle;
                    break;

                default:
                    H::pfm('Unsupported platform', pfm: $pfm);

                    return;
                    break;
            }

            $versions = $versions->where('type', $bundle)->where('channel', $channel);

            $mostRecentVersion = $versions->sortByDesc('version')->first();
            if (! $mostRecentVersion) {
                H::pfm('No new version to compare found', pfm: $pfm);

                return;
            }

            $mostRecentVersionTag = $mostRecentVersion->version;
            $clientBundleVersion = $bundle_version->version;
            H::pfm('Client Bundle Version: '.$clientBundleVersion.' | Most Recent Version: '.$mostRecentVersionTag, pfm: $pfm);
            if ($clientBundleVersion == $mostRecentVersionTag) {
                H::pfm('Bundle version up to date', pfm: $pfm);
                H::setSettingValue(SettingIds::newer_bundle_version_detected, false);
            } elseif ($clientBundleVersion > $mostRecentVersionTag) {
                H::pfm('Client has newer bundle version then server!', pfm: $pfm);
                H::setSettingValue(SettingIds::newer_bundle_version_detected, false);
            } elseif ($clientBundleVersion < $mostRecentVersionTag) {
                H::pfm('New version available '.$mostRecentVersionTag.' | Currently: '.$clientBundleVersion, pfm: $pfm);
                H::setSettingValue(SettingIds::newer_bundle_version_detected, true);

                $url = Consts::centralServerAddress."download/$mostRecentVersion->file_name";

                H::pfm('Download URL:', pfm: $pfm);
                H::pfm($url, pfm: $pfm);
                H::pfm('Update instructions:', pfm: $pfm);
                H::pfm(route('documentation', ['doc_tag' => DocsMapping::client_administration]), pfm: $pfm);
            }
        }
    }

    public function commonClientUpdateActions($newVersionFilePath, $pfm = false)
    {
        ini_set('max_execution_time', 600);
        $zip = new ZipArchive;
        if ($zip->open($newVersionFilePath) === true) {

            H::pfm('Count of files to be extracted: '.$zip->numFiles, pfm: $pfm);

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $fullPath = base_path().'/'.$zip->getNameIndex($i);
                $isFileWritableText = is_writable($fullPath) ? 'True' : 'False';

                $file_or_dir_exists = file_exists($fullPath);

                if ($file_or_dir_exists) {
                    $isFileWritable = is_writable($fullPath);
                } else {
                    $isFileWritable = true;
                }

                if (! $isFileWritable) {
                    H::pfm('isFileWritable: '.$isFileWritableText, pfm: $pfm);
                    H::pfm('File not writable by '.get_current_user(), pfm: $pfm);
                    H::pfm('On *buntu try: '.'sudo chown '.get_current_user().':'.posix_getgrgid(getmygid())['name'].' '.$fullPath, pfm: $pfm);
                    H::pfm('Or: '.'sudo chown -R '.get_current_user().':'.posix_getgrgid(getmygid())['name'].' '.base_path(), pfm: $pfm);
                    H::pfm('And: '.'sudo chmod 755 '.$fullPath, pfm: $pfm);
                    H::pfm('Or: '.'sudo chmod -R 755 '.base_path(), pfm: $pfm);
                    H::pfm($fullPath, pfm: $pfm);
                    H::pfm('/documentation/advanced_deployments', pfm: $pfm);

                    return;
                }
            }

            H::pfm('All upgradable files correctly writable', pfm: $pfm);
            for ($i = 0; $i < $zip->numFiles; $i++) {
                try {
                    $zip->extractTo(base_path(), [$zip->getNameIndex($i)]);
                } catch (\Throwable $th) {

                    $ignoredIssues = ['Operation failed: Operation not permitted'];
                    if (! Str::endsWith($th->getMessage(), $ignoredIssues)) {
                        dump($th);
                        throw $th;
                    }
                }
            }

            $zip->close();

            unlink($newVersionFilePath);

        } else {
            H::pfm('Downloaded file open failed', pfm: $pfm);
        }

        clearstatcache(true);

        try {
            Process::timeout(10)->run('sync');
        } catch (\Throwable $th) {
        }

        $pathsPendingDelete = [
            'app/Http/Controllers/zdel_OldController.php',
        ];
        foreach ($pathsPendingDelete as $key => $path) {
            $fullPath = base_path($path);
            if (File::exists($fullPath)) {
                File::delete($fullPath);
            }
        }

        $packages_path = base_path('bootstrap/cache/packages.php');
        if (File::exists($packages_path)) {
            File::delete($packages_path);
        }

        try {
            $optimize = App::isProduction() ? '--optimize' : '';
            $base_path = base_path();
            Process::timeout(10)->run('cd "'.$base_path.'" && export COMPOSER_HOME="$HOME/.config/composer" && composer dump-autoload '.$optimize);
        } catch (\Throwable $th) {
        }

        try {
            Artisan::call('migrate');
        } catch (\Throwable $th) {
        }

        self::upgradeSchedules();

        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        Artisan::call('optimize');

        H::pfm('Automatic Update Channel: '.H::getSettVal(SettingIds::client_automatic_update_channel), pfm: $pfm);
        H::pfm('Current version: '.H::ver(), pfm: $pfm);

        H::setSettingValue(SettingIds::newer_client_version_detected, false);
    }

    public static function mapMethodToCron($method_name)
    {

        switch ($method_name) {
            case 'everyMinute':
                $cron = '* * * * *';
                break;

            case 'everyTwoMinutes':
                $cron = '*/2 * * * *';
                break;

            case 'everyFourMinutes':
                $cron = '*/3 * * * *';
                break;

            case 'everyFiveMinutes':
                $cron = '*/5 * * * *';
                break;

            case 'everyTenMinutes':
                $cron = '*/10 * * * *';
                break;
            case 'everyThirtyMinutes':
                $cron = '*/30 * * * *';
                break;

            case 'hourlyAt':
                $randMinute = rand(0, 59);
                $cron = $randMinute.' * * * *';
                break;

            case 'dailyAt':
                $randHour = rand(0, 23);
                $randMinute = rand(0, 59);
                $cron = $randMinute.' '.$randHour.' * * *';
                break;

            default:
                $cron = '* * * * *';
                break;
        }

        return $cron;
    }

    public static function upgradeSchedules()
    {
        $dbSchedules = BackgroundSchedule::where('is_one_time', false)->get();
        foreach (BackgroundProcessingController::$schedulesTable as $key => $scheduleEntry) {
            $dbSchedule = $dbSchedules->where('tag', $scheduleEntry['tag'])->first();
            if (! $dbSchedule) {
                $randHour = rand(0, 23);
                $randMinute = rand(0, 59);
                $scheduleEntry['execution_hour'] = $randHour;
                $scheduleEntry['execution_minute'] = $randMinute;

                $dbSchedule = new BackgroundSchedule;
                $dbSchedule->tag = $scheduleEntry['tag'];
                $dbSchedule->ensure_daily_execution = $scheduleEntry['ensure_daily_execution'];
                $dbSchedule->method_name = $scheduleEntry['method_name'];
                $dbSchedule->cron = self::mapMethodToCron($scheduleEntry['method_name']);
                $dbSchedule->execution_hour = $scheduleEntry['execution_hour'];
                $dbSchedule->execution_minute = $scheduleEntry['execution_minute'];
                $dbSchedule->is_one_time = $scheduleEntry['is_one_time'];
                $dbSchedule->save();
                $dbSchedules->push($dbSchedule);
            }
            $dbSchedule->needed = true;
        }

        $dbSchedules = $dbSchedules->whereNull('needed');
        foreach ($dbSchedules as $key => $dbSchedule) {
            $dbSchedule->delete();
        }
    }

    public function checkClientUpdateAvailableOrUpdate(bool $andUpdate = false, bool $forceUpdate = false): void
    {
        $clientAutomaticUpdateChannel = H::getSettVal(SettingIds::client_automatic_update_channel);
        $clientAutomaticUpdateSource = H::getSettVal(SettingIds::client_automatic_update_source);

        switch ($clientAutomaticUpdateSource) {
            case 'remote':
                $clientAutomaticUpdateSource = PublishedVersionsSources::central_server;
                break;

            case 'local':
                $clientAutomaticUpdateSource = PublishedVersionsSources::dev_server;
                break;
        }

        $request = new Request;
        $request->merge([
            'source' => $clientAutomaticUpdateSource,
            'andUpdate' => $andUpdate,
            'channel' => $clientAutomaticUpdateChannel,
            'forceUpdate' => $forceUpdate,
            'pfm' => true,
        ]);

        (new UpdaterController)->checkAndUpdateClientFromCentral(request: $request);
    }

    public function updateClient(): void
    {
        (new UpdaterController)->checkClientUpdateAvailableOrUpdate(andUpdate: true);
    }

    public function checkBundleUpdateAvailable(): void
    {
        $clientAutomaticUpdateChannel = H::getSettVal(SettingIds::client_automatic_update_channel);
        $clientAutomaticUpdateSource = H::getSettVal(SettingIds::client_automatic_update_source);

        switch ($clientAutomaticUpdateSource) {
            case 'remote':
                $clientAutomaticUpdateSource = PublishedVersionsSources::central_server;
                break;

            case 'local':
                $clientAutomaticUpdateSource = PublishedVersionsSources::dev_server;
                break;
        }

        $request = new Request;
        $request->merge([
            'source' => $clientAutomaticUpdateSource,
            'channel' => $clientAutomaticUpdateChannel,
            'pfm' => true,
        ]);

        (new UpdaterController)->checkBundleUpdateFromCentral(request: $request);
    }

    public function downloadFile($url, $filepath)
    {
        $client = H::setupClient(is_tor_address: false);
        $options = [
            'sink' => $filepath,
            'timeout' => H::timeoutAdjust(false, 60),
            'prepare_ip' => true,
        ];
        H::prepareOptions($options, $url);
        
        try {
            $response = $client->request('GET', $url, $options);
        } catch (\Throwable $th) {
            H::pfm('Problem while downloading a file: '.$th->getMessage(), log: true);
            die;
        }

        if ($response->getStatusCode() !== 200) {
            return false;
        }

        return (filesize($filepath) > 0) ? true : false;
    }
}
