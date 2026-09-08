<?php

namespace App\Http\Controllers;

use App\Dicts\ActionTypes;
use App\Dicts\PublishedVersionsChannels;
use App\Dicts\PublishedVersionsSources;
use App\Dicts\SettingIds;
use App\Http\Consts;
use App\Http\H;
use App\Models\Admin;
use App\Models\BackgroundSchedule;
use App\Models\CachedDomain;
use App\Models\ContentRetrieval;
use App\Models\Owner;
use App\Models\Peer;
use App\Models\Site;
use App\Models\SitePeer;
use App\Models\Tracker;
use App\Services\AdminUiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use PDO;
use Pdp\Domain;
use Pdp\TopLevelDomains;
use Spatie\Url\Url;
use Throwable;
use Illuminate\Validation\Rules\Password;

class FirstRunController extends Controller
{
    public function onFirstRunNotCompleted(Request $request): string|View
    {
        $first_run_completed = H::isFirstRunCompleted();
        if ($first_run_completed) {
            return 'First Run already completed. | <a  href="/">Back</a>';
        }

        $client_address = H::a($request->schemeAndHttpHost());

        $url = Url::fromString($client_address);
        $currentSchemeHttp = true;
        if ($url->getScheme() == 'https') {
            $currentSchemeHttp = false;
            $client_address_https = $client_address;
            $client_address = H::a($url->withScheme('http')->__toString());
        } else {
            $client_address_https = H::a($url->withScheme('https')->__toString());
        }

        if (in_array($client_address, ['http://127.0.0.1:15080/', 'http://snet.localhost:15080/'])) {
            $client_address_https = 'https://snet.localhost:15443/';
        } elseif (in_array($client_address_https, ['http://127.0.0.1:15443/', 'http://snet.localhost:15443/'])) {
            $client_address = 'http://snet.localhost:15080/';
        } else {
            $path = '../../frankenphp/Caddyfile';
            $CaddfileExists = File::exists($path);
            if ($CaddfileExists) {
                $httpPort = null;
                $httpsPort = null;
                $c = File::get($path);

                if (preg_match('/http_port\s+(\d+)/', $c, $m)) {
                    $httpPort = (int) $m[1];
                }

                if (preg_match('/https_port\s+(\d+)/', $c, $m)) {
                    $httpsPort = (int) $m[1];
                }
                if ($currentSchemeHttp) {
                    $client_address_https = H::a($url->withPort($httpsPort)->withScheme('https'));
                } else {
                    $client_address = H::a($url->withPort($httpPort)->withScheme('http'));
                }
            }
        }

        static $initialTrackersJson = json_encode(Consts::initialTrackers);

        $advancedAddressesExamples = [
            'https://yourdomain.com/',
            'http://your-v3-tor-address.onion/',
        ];

        return view('first_run', [
            'client_address' => $client_address,
            'client_address_https' => $client_address_https,
            'first_run_completed' => false,
            'advancedAddressesExamples' => $advancedAddressesExamples,
            'initialTrackersJson' => $initialTrackersJson,
        ]);
    }

    public function completeFirstRun(Request $request)
    {
        $isDevNodeRequested = $request->has('isDevNode');

        $isDevNode = (bool) ($isDevNodeRequested || H::isDevNode());

        $is_off_network_node = config('sn.is_off_network_node');

        H::pfm('Is or should be a Dev Node: '.tfyn($isDevNode));

        if (H::isFirstRunCompleted()) {
            H::pfm('First Run already completed!', log: true);

            return redirect('./')->with('status', 'First Run already completed!');
        }
        /* Test write to log to check permissions */
        try {
            info('Test Write - First Run');
        } catch (Throwable $th) {
            $issueType = 'file_permissions';
            $message = $th->getMessage();

            return view('first_run_issues', compact('issueType', 'message'));
        }

        $passwordRules = Password::min(12)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->uncompromised();

        $request->validate([
            'client_address' => ['required', 'string', 'max:2048'],
            'client_address_https' => ['required', 'string', 'max:2048'],
            'initialTrackersJson' => ['required', 'string', 'max:10024'],
            'customCentralServer' => ['string', 'nullable', 'max:10024'],
            'password' => ['required', 'confirmed', $passwordRules],
            'DB_CONNECTION' => ['string', 'max:2048'],
            'DB_HOST' => ['string', 'max:2048'],
            'DB_PORT' => ['integer'],
            'DB_DATABASE' => ['string', 'max:2048'],
            'DB_USERNAME' => ['string', 'max:2048'],
            'DB_PASSWORD' => ['nullable', 'string', 'max:2048'],
            'DB_TABLE_PREFIX' => ['nullable', 'string', 'max:2048'],
            'siteDatabasesInMySql' => ['nullable', 'boolean'],
        ]);

        $DB_CONNECTION = $request->input('DB_CONNECTION');
        $DB_HOST = $request->input('DB_HOST');
        $DB_PORT = $request->input('DB_PORT');
        $DB_DATABASE = $request->input('DB_DATABASE');
        $DB_USERNAME = $request->input('DB_USERNAME');
        $DB_PASSWORD = $request->input('DB_PASSWORD') ?? "''";
        $DB_TABLE_PREFIX = $request->input('DB_TABLE_PREFIX') ?? "''";

        $config_DB_PASSWORD = $DB_PASSWORD === "''" ? null : $DB_PASSWORD;
        $config_DB_TABLE_PREFIX = $DB_TABLE_PREFIX === "''" ? null : $DB_TABLE_PREFIX;

        $siteDatabasesInMySql = $request->has('siteDatabasesInMySql') ? 'true' : 'false';

        $app_name = $request->input('app_name') ?? null;

        $client_address_http = $request->input('client_address');
        $client_address_https = $request->input('client_address_https');

        H::pfm('⏳ First Run Actions started please wait to complete.');
        H::pfm('Once completed you will redirected to the Dashboard.');

        Artisan::call('env:set APP_DEBUG false');

        Artisan::call('env:set APP_ENV prod');
        Artisan::call("env:set DB_DATABASE $DB_DATABASE");
        Artisan::call("env:set DB_CONNECTION $DB_CONNECTION");
        Artisan::call("env:set DB_HOST $DB_HOST");
        Artisan::call("env:set DB_PORT $DB_PORT");
        Artisan::call("env:set DB_DATABASE $DB_DATABASE");
        Artisan::call("env:set DB_USERNAME $DB_USERNAME");
        Artisan::call("env:set DB_PASSWORD $DB_PASSWORD");
        Artisan::call("env:set DB_TABLE_PREFIX $DB_TABLE_PREFIX");
        Artisan::call("env:set SITE_DATABASES_IN_MYSQL $siteDatabasesInMySql");
        Artisan::call("env:set APP_URL $client_address_http");
        Artisan::call('env:set SERVE_OWNER_ONLY true');

        // $isDevNode

        if (! $app_name) {
            $app_name = Str::limit($request->getHost(), limit: 6, end: '', preserveWords: true);
        }
        Artisan::call('env:set APP_NAME '.$app_name);

        if ($DB_CONNECTION == 'sqlite') {
            $DB_DATABASE = database_path('database.sqlite');
            if (! File::exists($DB_DATABASE)) {
                File::put($DB_DATABASE, '');
            }
            config(['database.connections.sqlite.database' => $DB_DATABASE]);
        } else {
            config(['database.connections.mysql.database' => $DB_DATABASE]);
            Config::set('database.connections.mysql', [
                'driver' => 'mysql',
                'host' => $DB_HOST,
                'port' => $DB_PORT,
                'database' => $DB_DATABASE,
                'username' => $DB_USERNAME,
                'password' => $config_DB_PASSWORD,
                'unix_socket' => '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => $config_DB_TABLE_PREFIX,
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
                'options' => extension_loaded('pdo_mysql') ? array_filter([
                    (defined('Pdo\\Mysql::ATTR_SSL_CA')
                    ? PDO\Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
                ]) : [],
            ]);
        }

        config(['database.default' => $DB_CONNECTION]);

        /* Clear Logs */
        $logPath = storage_path('logs/laravel.log');
        if (File::exists($logPath)) {
            File::put($logPath, '');
        }

        Storage::disk('local')->put('first_run_completed.php', 'true');

        try {
            $this->dropAllTables();
        } catch (Throwable $th) {
            Log::debug($th->getMessage());
            $issueType = 'other';
            $message = $th->getMessage();

            return view('first_run_issues', compact('issueType', 'message'));
        }

        DB::reconnect();

        try {
            Artisan::call('migrate --force');
        } catch (Throwable $th) {
        }

        $request->merge(['ui_addresses' => implode("\n", [$client_address_http, $client_address_https])]);

        (new AdminUiService)->setUiAddresses($request);

        $is_public_host_client_address_http = self::checkIsPublicHost($client_address_http);
        $is_public_host_client_address_https = self::checkIsPublicHost($client_address_https);

        if ($is_public_host_client_address_http) {
            H::setSettingValue(SettingIds::is_public_self_address_overridden, true);
            H::setSettingValue(SettingIds::static_public_self_address, H::a($client_address_http));
        }
        if ($is_public_host_client_address_https) {
            H::setSettingValue(SettingIds::is_public_self_address_overridden, true);
            H::setSettingValue(SettingIds::static_public_self_address, H::a($client_address_https, https: true));
        }

        $clientPortHttp = (new PortForwardController)->getPort($client_address_http);
        $clientPortHttps = (new PortForwardController)->getPort($client_address_https);

        H::setSettingValue(SettingIds::client_internal_port_http, $clientPortHttp);
        H::setSettingValue(SettingIds::client_internal_port_https, $clientPortHttps);

        /* Save default value on first read */
        H::pfm('Settings default for all settings', log: true);
        $settingIds = SettingIds::getConstants();
        foreach ($settingIds as $key => $value) {
            H::getSettVal($key);
        }

        if ($is_off_network_node) {
            H::setSettingValue(SettingIds::port_forwarding_enabled, false);
            H::setSettingValue(SettingIds::client_automatic_update_channel, PublishedVersionsChannels::dev);
            // $versionSource = PublishedVersionsSources::central_server;
            // if (class_exists(DevController::class)) {
            //     $versionSource = PublishedVersionsSources::dev_server;
            // } else {
            //     $versionSource = PublishedVersionsSources::central_server;
            // }
            H::setSettingValue(SettingIds::client_automatic_update_source, PublishedVersionsSources::central_server);
            H::setSettingValue(SettingIds::main_loop_bootstrap_enabled, false);
        }
        if ($isDevNode) {
            H::setSettingValue(SettingIds::port_forwarding_enabled, false);
        }

        $customCentralServer = $request->input('customCentralServer');
        if ($customCentralServer) {
            H::setSettingValue(SettingIds::dev_custom_central_server_address, H::a($customCentralServer));
        }

        H::pfm('Creating Admin and Owner', log: true);

        Admin::create([
            'name' => 'admin',
            'password' => Hash::make($request->password),
        ]);

        $auth_token = Str::random(40);
        Owner::create([
            'name' => 'owner',
            'auth_token' => $auth_token,
        ]);
        $ownerAuthCookie = cookie('owner_auth_token', Crypt::encryptString($auth_token),
            2147483647, '/', '.'.getDomain(), false, false);
        Cookie::queue($ownerAuthCookie);

        H::pfm('Creating Initial trackers', log: true);

        static $initialTrackers = json_decode($request->initialTrackersJson);

        foreach ($initialTrackers as $key => $value) {
            $priority = 2;
            $port = (new PortForwardController)->getPort($value);
            if (in_array($port, Consts::mostConnectablePorts)) {
                $priority = 1;
            }

            $tracker = Tracker::where('url', $value)->first();
            if (! $tracker) {
                $tracker = new Tracker;
                $tracker->url = $value;
                $tracker->priority = $priority ?? 2;
                $tracker->save();
            }
        }

        H::pfm('Creating snet domains', log: true);

        $staticDomains = Consts::defaultDomainsAndSites;
        foreach ($staticDomains as $key => $domain) {
            $domainToBeSaved = new CachedDomain($domain);
            $domainToBeSaved->domain = $domain['domain'];
            $domainToBeSaved->site_id = $domain['site_id'];
            $domainToBeSaved->is_persistent = 1;
            $domainToBeSaved->record_json = json_encode($domainToBeSaved);
            $domainToBeSaved->save();
        }

        H::pfm('Creating Background Schedules', log: true);

        foreach (BackgroundProcessingController::$schedulesTable as $key => $scheduleEntry) {
            $randHour = rand(0, 23);
            $randMinute = rand(0, 59);
            $scheduleEntry['execution_hour'] = $randHour;
            $scheduleEntry['execution_minute'] = $randMinute;

            $backgroundSchedule = new BackgroundSchedule;

            $backgroundSchedule->tag = $scheduleEntry['tag'];
            $backgroundSchedule->ensure_daily_execution = $scheduleEntry['ensure_daily_execution'];
            $backgroundSchedule->method_name = $scheduleEntry['method_name'];
            $backgroundSchedule->execution_hour = $scheduleEntry['execution_hour'];
            $backgroundSchedule->execution_minute = $scheduleEntry['execution_minute'];
            $backgroundSchedule->is_one_time = false;
            $backgroundSchedule->save();
        }

        try {
            Artisan::call('storage:unlink');
        } catch (Throwable $th) {
        }

        /* Clear Site's databases */
        Storage::disk('local')->deleteDirectory('sites_databases/');

        /*
         * - Enabled list providers
         * - tech-demo
         */
        H::pfm('Setting up initial sites and peers', log: true);
        $defaultDomainsAndSites = Consts::defaultDomainsAndSites;

        foreach ($defaultDomainsAndSites as $key => $defaultSite) {
            $sitePresent = Site::whereSiteId($defaultSite['site_id'])->first();
            if ($sitePresent) {
                continue;
            }
            $site = new Site;
            $site_id = $defaultSite['site_id'];
            $site->site_id = $site_id;
            $site->title = $defaultSite['title'];
            $site->domain = $defaultSite['domain'];
            $site->is_published = true;
            $site->visible_in_ui = true;
            $site->is_to_be_hosted = true;
            $site->save();

            $contentRetrieval = new ContentRetrieval;
            $contentRetrieval->retrieval_id = Str::random(40);
            $contentRetrieval->action_type = ActionTypes::retrieve_site_definition;
            $contentRetrieval->site_id = $site_id;
            $contentRetrieval->save();

            $defaultSitesClients = Consts::defaultSitesClients;

            foreach ($defaultSitesClients as $key => $client_address) {
                if (in_array($client_address, H::getSelfAddresses())) {
                    continue;
                }

                $sitePeer = new SitePeer;
                $sitePeer->client_address = H::a($client_address);
                $sitePeer->site_id = $site_id;
                $sitePeer->source = 'replication';
                $sitePeer->save();

                $peer = Peer::where('client_address', $client_address)->first();
                if (! $peer) {
                    $peer = new Peer;
                    $peer->client_address = H::a($client_address);
                    $peer->save();
                }
            }
        }

        if ($isDevNode) {
            Storage::disk('local')->put('isDevNode.php', ' ');
            Artisan::call('env:set SERVE_OWNER_ONLY false');
            Artisan::call('env:set APP_DEBUG true');
        }

        H::pfm('Caching optimize', log: true);

        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('route:clear');

        Artisan::call('optimize');

        H::pfm('Updating external IP', log: true);
        if (! $is_off_network_node) {
            $closure = function () {
                H::updateExternalIp(pfm: false);
            };

            H::dispatchInternalAsyncClosureWrapper($closure);
        }

        H::pfm('Updating external port and Forwarding', log: true);

        if (! $is_off_network_node) {
            $closure = function () {
                (new PortForwardController)->updateExternalPort(pfm: false);
            };
            H::dispatchInternalAsyncClosureWrapper($closure);
        }

        return redirect($request->getSchemeAndHttpHost().'/');
    }

    protected function dropAllTables()
    {
        $tables = [
            'migrations',
            'pending_actions',
            'sites',
            'peers',
            'site_peers',
            'p2p_messages',
            'settings',
            'pending_retrievals',
            'content_retrievals',
            'cached_resources',
            'visitors',
            'admins',
            'users',
            'password_resets',
            'site_sse_entries',
            'failed_jobs',
            'personal_access_tokens',
            'trackers',
            'opennic_name_servers',
            'cached_domains',
            'visitor_resources',
            'serveronet_versions',
            'visitor_records',
            'crowd_queries',
            'crowd_query_results',
            'background_schedules',
            'list_provider_entries',
            'owners',
            'background_schedule_executions',
            'site_definitions',
            'replication_sessions',
            'replication_session_peers',
            'peer_replication_sessions',
            'reporting_peer_edges',
            'sessions',
            'passive_sessions',
            'internal_requests',
            'local_tracker_info_hash_peers',

            'migrations',
        ];

        foreach ($tables as $key => $table) {
            try {
                if (Schema::hasTable($table)) {
                    Schema::drop($table);
                    H::pfm('Dropped table '.$table);
                } else {
                    H::pfm('Cant Drop table (hasTable) '.$table);
                }
            } catch (Throwable $th) {
                H::pfm($th->getMessage().' '.$th->getFile().' '.$th->getLine());
            }
        }
    }

    public function checkMysqlConnectivity(Request $request)
    {
        if (H::isFirstRunCompleted()) {
            return redirect(domainRoute('home'))->with('status', 'First Run already completed!');
        }

        try {
            $request->validate([
                'DB_HOST' => ['string', 'max:2048'],
                'DB_PORT' => ['integer'],
                'DB_DATABASE' => ['string', 'max:2048'],
                'DB_USERNAME' => ['string', 'max:2048'],
                'DB_PASSWORD' => ['nullable', 'string', 'max:2048'],
                'DB_TABLE_PREFIX' => ['nullable', 'string', 'max:2048'],
                'siteDatabasesInMySql' => ['nullable', 'boolean'],
            ]);
        } catch (Throwable $th) {
            return $this->return_failure($th->getMessage());
        }

        $DB_HOST = $request->input('DB_HOST');
        $DB_PORT = $request->input('DB_PORT');
        $DB_DATABASE = $request->input('DB_DATABASE');
        $DB_USERNAME = $request->input('DB_USERNAME');
        $DB_PASSWORD = $request->input('DB_PASSWORD');
        $DB_TABLE_PREFIX = $request->input('DB_TABLE_PREFIX');
        $siteDatabasesInMySql = $request->input('siteDatabasesInMySql');

        try {
            Config::set('database.connections.tmp', [
                'driver' => 'mysql',
                'host' => $DB_HOST ?? '127.0.0.1',
                'port' => $DB_PORT ?? '3306',
                'database' => $DB_DATABASE,
                'username' => $DB_USERNAME ?? 'root',
                'password' => $DB_PASSWORD ?? '',
                'unix_socket' => '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => $DB_TABLE_PREFIX ?? '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
                'options' => extension_loaded('pdo_mysql') ? array_filter([
                    (defined('Pdo\\Mysql::ATTR_SSL_CA')
                    ? PDO\Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
                ]) : [],
            ]);

            $tmp_table_name = $DB_TABLE_PREFIX.'tmp_'.Str::random(4);
            $createTableStatement = "CREATE TABLE `$tmp_table_name` (`Test_Field` INTEGER)";
            $insertStatement = "INSERT INTO `$tmp_table_name` values (0)";
            $updateStatement = "UPDATE `$tmp_table_name` set Test_Field = 1";
            $deleteStatement = "DELETE from `$tmp_table_name`";
            $dropTableStatement = "DROP table `$tmp_table_name`";

            DB::connection('tmp')->unprepared($createTableStatement);
            DB::connection('tmp')->unprepared($insertStatement);
            DB::connection('tmp')->unprepared($updateStatement);
            DB::connection('tmp')->unprepared($deleteStatement);
            DB::connection('tmp')->unprepared($dropTableStatement);

            if ($siteDatabasesInMySql) {

                $tmp_schema_name = $DB_TABLE_PREFIX.'tmp_'.Str::random(4);
                $creteSchemaStatement = "create schema `$tmp_schema_name`";
                $dropSchemaStatement = "drop schema `$tmp_schema_name`";

                DB::connection('tmp')->unprepared($creteSchemaStatement);
                DB::connection('tmp')->unprepared($dropSchemaStatement);

                $tmp_user_name = $DB_TABLE_PREFIX.'tmp_user_'.Str::random(4);
                $createUserStatement = "create user `$tmp_user_name`@`localhost`";
                $dropUserStatement = "drop user `$tmp_user_name`@`localhost`";

                DB::connection('tmp')->unprepared($createUserStatement);
                DB::connection('tmp')->unprepared($dropUserStatement);
            }

            return $this->return_success();
        } catch (Throwable $th) {
            return $this->return_failure($th->getMessage());
        }
    }

    protected static function checkIsPublicHost($client_address)
    {
        $urlComponents = parse_url(H::a($client_address, true));
        $host = $urlComponents['host'];

        $endings = ['localhost', 'test', 'example', 'invalid',
            'local', 'loc', 'lan', 'home', 'internal', 'corp',
            'localdomain', 'home.arpa', 'onion'];

        if (Str::endsWith($host, $endings) || ! Str::contains($host, '.')) {
            return false;
        }

        $topLevelDomains = TopLevelDomains::fromPath(resource_path('tlds-alpha-by-domain.txt'));
        $url = Url::fromString($client_address);
        $domain = Domain::fromIDNA2008($url->getHost());
        $result = $topLevelDomains->resolve($domain);

        return $result->suffix()->isIANA();
    }
}
