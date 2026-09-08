<?php

namespace App\Services;

use App\Dicts\CachePrefixes;
use App\Dicts\SysProps;
use App\Http\Consts;
use App\Http\Controllers\Controller;
use App\Http\Controllers\UtilsController;
use App\Http\H;
use App\Models\ResultContainer;
use App\Models\Site;
use App\Models\VisitorRecord;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use Throwable;

class SiteDatabaseService extends Controller
{
    public function getSiteDbPassword($site_id): ?string
    {
        $site = H::getCached(CachePrefixes::site_.$site_id, Site::whereSiteId($site_id));
        $siteDbPassword = $site->site_db_password;

        if (! $siteDbPassword) {
            $siteDbPassword = Str::random();
            $site->site_db_password = $siteDbPassword;
            $site->site_db_encrypted_password = encrypt($siteDbPassword);
            $site->save();
            Cache::forget(CachePrefixes::site_.$site_id);
        }

        return $siteDbPassword;
    }

    public function maintainDatabase(string $site_id)
    {
        if (config('sn.site_databases_in_mysql')) {
            $driver = 'mysql';
        } else {
            $driver = 'sqlite';
        }

        if ($driver === 'sqlite') {
            DB::connection($site_id)->statement('VACUUM');
        }
    }

    public function deleteSiteDatabase($site_id)
    {
        if (config('sn.site_databases_in_mysql')) {
            $shortSiteId = H::getShortSiteID($site_id, true);
            $dbName = 'sn_'.$shortSiteId;
            $dbUser = 'sn_user_'.$shortSiteId;

            $dropSiteDbAndUserStatementSchema = 'drop schema `'.$dbName.'`; ';
            $dropSiteDbAndUserStatementUser = 'drop USER `'.$dbUser.'`@`localhost` ';

            try {
                $r = DB::unprepared($dropSiteDbAndUserStatementSchema);
            } catch (Throwable $th) {
                // throw $th;
            }

            try {
                $r = DB::unprepared($dropSiteDbAndUserStatementUser);
            } catch (Throwable $th) {
                // throw $th;
            }

            // dd($r);

        } else {
            $dbFile = 'sites_databases/'.$site_id.'/'.$site_id.'.sqlite';
            $dbBackupFile = 'sites_databases/'.$site_id.'/Backup_'.$site_id.'.sqlite';

            Storage::copy($dbFile, $dbBackupFile);

            Storage::delete($dbFile);
        }

        $site = Site::whereSiteId($site_id)->first();
        $site->db_created = null;
        $site->db_schema_version = null;
        $site->save();

        Cache::forget(CachePrefixes::site_.$site_id);
        Cache::forget(CachePrefixes::site_with_most_recent_site_definition_.$site_id);
    }

    public function parseUpsertIncomingVisitorRecord(VisitorRecord $visitorRecord, string $site_id): ResultContainer
    {
        info('parseUpsertIncomingVisitorRecord '.$visitorRecord->entity_id);
        $resultContainer = new ResultContainer;
        $resultContainer->operation_successful = true;

        if ((new UtilsController)->validatedReturnSiteId(null, $site_id)) {
            $resultContainer->operation_successful = false;
            $resultContainer->error_message = 'Invalid Site ID';

            return $resultContainer;
        }

        $site = Site::whereSiteId($site_id)->with('most_recent_site_definition')->first();

        if (! $site) {
            info('parseUpsertIncomingVisitorRecord No Site');
            $resultContainer->operation_successful = false;
            $resultContainer->error_message = 'No Site';

            return $resultContainer;
        }
        $siteConfig = (new SiteConfigService)->getSiteConfig($site);

        /* Site has No database, ignore inputs */
        if (! $siteConfig->site_Has_Database) {
            info('parseUpsertIncomingVisitorRecord site_Has_Database');
            $resultContainer->operation_successful = false;
            $resultContainer->error_message = 'Site has no Database';
            $resultContainer->data = $resultContainer->error_message;

            return $resultContainer;
        }

        $incomingRecord = json_decode($visitorRecord->record_json);
        $incomingRecord->{SysProps::_sn_signer_verification_key_base64} = $visitorRecord->signer_verification_key_base64;
        $incomingRecord->{SysProps::_sn_signature} = $visitorRecord->signature;
        $incomingRecord->{SysProps::_sn_record_json} = $visitorRecord->record_json;

        $result = self::prepareSiteDatabase($site_id);

        if (! $result->operation_successful) {
            return $result;
        }

        unset($incomingRecord->{SysProps::_sn_site_id});

        $tableName = $incomingRecord->{SysProps::_sn_table};

        $insertOnly = false;
        if (! isset($incomingRecord->{SysProps::_sn_entity_id})) {
            $insertOnly = true;
        }

        $recordAsArray = (array) $incomingRecord;

        $presentRecord = null;
        if (! $insertOnly) {

            $presentRecord = DB::connection($site_id)->table($tableName)->where([
                [SysProps::_sn_entity_id, $recordAsArray[SysProps::_sn_entity_id]],
            ])->first();
            if (! $presentRecord) {
                $insertOnly = true;
            }

        }

        $columnsWithValues = [];
        foreach ($recordAsArray as $key => $val) {
            $columnsWithValues = Arr::add($columnsWithValues, $key, $val);
        }

        foreach (SysProps::sys_props_hidden_from_site_db as $key => $column) {
            unset($columnsWithValues[$column]);
        }

        $insertedCount = 0;
        $updatedCount = 0;
        if ($insertOnly) {
            /* INSERT */

            try {
                $insertedCount = DB::connection($site_id)->table($tableName)->insert(
                    $columnsWithValues
                );
            } catch (Throwable $th) {
                info($th->getMessage().' '.$th->getFile().' '.$th->getLine());
                $resultContainer->operation_successful = false;
                $resultContainer->error_message = $th->getMessage();

                return $resultContainer;
            }
        } else {

            /* UPDATE existing record if newer */
            if ($incomingRecord->{SysProps::_sn_entity_updated} > $presentRecord->{SysProps::_sn_entity_updated}) {

                try {
                    $updatedCount = DB::connection($site_id)->table($tableName)
                        ->where([
                            [SysProps::_sn_entity_id, $recordAsArray[SysProps::_sn_entity_id]],
                        ])
                        ->update(
                            $columnsWithValues
                        );
                } catch (Throwable $th) {
                    info($th->getMessage().' '.$th->getFile().' '.$th->getLine());
                    $resultContainer->operation_successful = false;
                    $resultContainer->error_message = $th->getMessage();

                    return $resultContainer;
                }
            }
            /* Ignoring update as we got newer version */

        }

        $resultContainer->data = ['insertedCount' => (int) $insertedCount, 'updatedCount' => (int) $updatedCount];

        return $resultContainer;
    }

    public function backupSiteDatabase($site_id)
    {
        $dbFile = 'sites_databases/'.$site_id.'/'.$site_id.'.sqlite';
        $dbBackupFile = 'sites_databases/'.$site_id.'/Backup_'.$site_id.'.sqlite';
        Storage::copy($dbFile, $dbBackupFile);
    }

    public function resetSiteDatabase($site_id): ResultContainer
    {
        $rc = new ResultContainer;
        $rc->operation_successful = true;

        $rcPrepareSiteDatabase = self::prepareSiteDatabase($site_id);
        if (! $rcPrepareSiteDatabase->operation_successful) {
            return $rcPrepareSiteDatabase;
        }

        $tables = Schema::connection($site_id)->getTables();

        $deletedStatistics = [];

        foreach ($tables as $key => $table) {
            $i = DB::connection($site_id)->table($table['name'])->delete();
            $deletedStatistics[$table['name']] = $i;
        }

        $rc->debug_data = $deletedStatistics;

        return $rc;
    }

    public function prepareSiteDatabase($site_id): ResultContainer
    {
        $rc = new ResultContainer;
        $rc->operation_successful = true;

        $site = H::getCached(CachePrefixes::site_with_most_recent_site_definition_.$site_id,
            Site::whereSiteId($site_id)->with('most_recent_site_definition')
        );

        $db_created = $site->db_created;

        $current_db_schema_version = $site->db_schema_version ?? -1;

        if (config('sn.site_databases_in_mysql')) {
            $driver = 'mysql';
        } else {
            $driver = 'sqlite';
        }

        if ($driver === 'sqlite') {
            $dbFile = 'sites_databases/'.$site_id.'/'.$site_id.'.sqlite';
            $path = Storage::path($dbFile);

            Config::set('database.connections.'.$site_id, [
                'driver' => 'sqlite',
                'url' => config('database.connections.sqlite.url'),
                'database' => $path,
                'prefix' => '',
                'foreign_key_constraints' => config('database.connections.sqlite.foreign_key_constraints'),
            ]);

            if (! $db_created) {
                if (! Storage::disk('local')->exists($dbFile)) {
                    Storage::disk('local')->put($dbFile, '');
                    $rcCreate = self::onCreateSiteDatabase($site_id, driver: $driver);
                    if (! $rcCreate->operation_successful) {
                        return $rcCreate;
                    }
                }
                $site->db_created = true;
                $site->save();
            }

        } elseif ($driver === 'mysql') {

            $siteDbPassword = (new SiteDatabaseService)->getSiteDbPassword($site_id);
            $shortSiteId = H::getShortSiteID($site_id, true);
            $dbName = 'sn_'.$shortSiteId;
            $dbUser = 'sn_user_'.$shortSiteId;

            Config::set('database.connections.'.$site_id, [
                'driver' => 'mysql',
                'url' => null,
                'host' => config('database.connections.mysql.host'),
                'port' => config('database.connections.mysql.port'),
                'database' => $dbName,
                'username' => $dbUser,
                'password' => $siteDbPassword,
                'unix_socket' => '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'prefix_indexes' => true,
                'strict' => true,
                'engine' => null,
                'options' => extension_loaded('pdo_mysql') ? array_filter([
                    (defined('Pdo\\Mysql::ATTR_SSL_CA')
                    ? PDO\Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
                ]) : [],
            ]);

            if (! $db_created) {
                $rcCreate = self::onCreateSiteDatabase($site_id, driver: $driver);

                if (! $rcCreate->operation_successful) {
                    return $rcCreate;
                }

                $site->db_created = true;
                $site->save();
            }
        }

        $siteConfig = (new SiteConfigService)->getSiteConfig($site);

        if (! $siteConfig->db_Schema_Versions) {
            $rc->operation_successful = false;
            $rc->error_message = 'Site has no schema versions';

            return $rc;
        }
        $db_Schema_Versions = $siteConfig->db_Schema_Versions;

        $versionNumbers = collect($db_Schema_Versions)->pluck('version_number');
        $versionNumbers = array_keys($db_Schema_Versions);
        $pendingVersionNumbers = array_filter($versionNumbers, function ($schemaVersion) use ($current_db_schema_version) {
            return $schemaVersion > $current_db_schema_version;
        });

        if (! empty($pendingVersionNumbers)) {
            $rcOnUpgradeSiteDatabase = $this->onUpgradeSiteDatabase($site_id, $driver);

            if (! $rcOnUpgradeSiteDatabase->operation_successful) {
                return $rcOnUpgradeSiteDatabase;
            }
        }

        return $rc;
    }

    public function onCreateSiteDatabase($site_id, $driver): ResultContainer
    {
        Log::debug('onCreateSiteDatabase');

        $rc = new ResultContainer;
        $rc->operation_successful = true;

        try {
            if ($driver === 'mysql') {
                $shortSiteId = H::getShortSiteID($site_id, true);
                $dbName = 'sn_'.$shortSiteId;
                $dbUser = 'sn_user_'.$shortSiteId;

                $siteDbPassword = (new SiteDatabaseService)->getSiteDbPassword($site_id);
                $prepareSiteDbAndUserStatementSchema = 'create schema `'.$dbName.'`; ';
                $prepareSiteDbAndUserStatementUser = 'CREATE USER `'.$dbUser.'`@`localhost` IDENTIFIED BY \''.$siteDbPassword.'\'; ';
                $prepareSiteDbAndUserStatementGrant = 'GRANT ALL PRIVILEGES ON '.$dbName.'.* TO `'.$dbUser.'`@`localhost` with grant option; ';

                DB::unprepared($prepareSiteDbAndUserStatementSchema);
                DB::unprepared($prepareSiteDbAndUserStatementUser);
                DB::statement($prepareSiteDbAndUserStatementGrant);

                DB::reconnect();
            }

            DB::connection($site_id)->reconnect();

            Cache::forget(CachePrefixes::site_.$site_id);
            Cache::forget(CachePrefixes::site_with_most_recent_site_definition_.$site_id);

        } catch (Throwable $th) {
            $rc->operation_successful = false;
            $rc->error_message = $th->getMessage();
            $rc->data = $th->getMessage().' '.$th->getFile().' '.$th->getLine();
            $rc->debug_data = $th->getMessage().' '.$th->getFile().' '.$th->getLine();

            return $rc;
        }

        $rcMandatoryFields = self::addMandatoryUserDataColumns($site_id, $driver, trigger: 'onCreateSiteDatabase');

        if (! $rcMandatoryFields->operation_successful) {
            Log::debug('onCreateSiteDatabase failed '.$rcMandatoryFields->error_message);
            return $rcMandatoryFields;
        }

        return $rc;
    }

    public static function onUpgradeSiteDatabase($site_id, $driver): ResultContainer
    {
        Log::debug('onUpgradeSiteDatabase');
        $rc = new ResultContainer;
        $rc->operation_successful = true;

        try {
            $site = Site::whereSiteId($site_id)->with('most_recent_site_definition')->first();
            $current_db_schema_version = $site->db_schema_version ?? -1;

            $siteConfig = (new SiteConfigService)->getSiteConfig($site);

            if (! $siteConfig->db_Schema_Versions) {
                $rc->operation_successful = false;
                $rc->error_message = 'No db_Schema_Versions';
                $rc->data = 'No db_Schema_Versions';

                return $rc;
            }
            $db_Schema_Versions = $siteConfig->db_Schema_Versions;

            $versionNumbers = collect($db_Schema_Versions)->pluck('version_number');

            $versionNumbers = array_keys($db_Schema_Versions);
            $versionNumbers = array_filter($versionNumbers, function ($schemaVersion) use ($current_db_schema_version) {
                return $schemaVersion > $current_db_schema_version;
            });

            $versionNumbers = Arr::sort($versionNumbers);

            foreach ($versionNumbers as $key => $versionNumber) {
                $schemaDefinition = collect($db_Schema_Versions)->where('version_number', $versionNumber)->first();

                if (isset($schemaDefinition->tableCreates)) {
                    foreach ($schemaDefinition->tableCreates as $tableName => $create) {
                        self::checkIsStringSqlValid($tableName);
                        Schema::connection($site_id)->create($tableName, function (Blueprint $table) use ($create) {
                            foreach ($create as $columnName => $column) {
                                self::checkIsStringSqlValid($columnName);
                                $dataType = $column->datatype;
                                $dbDataTypeToMethodMap = Consts::dbDataTypeToMethodMap;
                                $methodName = $dbDataTypeToMethodMap[$dataType] ?? null;
                                if (! $methodName) {
                                    throw new Exception('Column creation method not mapped: '.$columnName, 1);
                                }
                                $builder = $table;
                                if ($methodName) {
                                    if (isset($column->default)) {
                                        $builder->$methodName($columnName)->default($column->default);
                                    } else {
                                        $builder->$methodName($columnName)->nullable($column->nullable ?? true);
                                    }
                                }
                            }
                        });
                    }
                }

                if (isset($schemaDefinition->columnAlters)) {
                    foreach ($schemaDefinition->columnAlters as $tableName => $alter) {
                        self::checkIsStringSqlValid($tableName);
                        Schema::connection($site_id)->table($tableName, function (Blueprint $table) use ($alter) {
                            foreach ($alter as $columnName => $alter) {
                                self::checkIsStringSqlValid($columnName);
                                $dataType = $alter->datatype;
                                $dbDataTypeToMethodMap = Consts::dbDataTypeToMethodMap;
                                $methodName = $dbDataTypeToMethodMap[$dataType];
                                if (! $methodName) {
                                    throw new Exception('Column creation method not mapped: '.$columnName, 1);
                                }
                                $builder = $table;
                                if ($methodName) {
                                    if (isset($alter->default)) {
                                        $builder->$methodName($columnName)->default($alter->default);
                                    } else {
                                        $builder->$methodName($columnName)->nullable($alter->nullable ?? true);
                                    }
                                }
                            }
                        });
                    }
                }

                if (isset($schemaDefinition->indexCreates)) {
                    foreach ($schemaDefinition->indexCreates as $tableName => $index) {
                        self::checkIsStringSqlValid($tableName);
                        Schema::connection($site_id)->table($tableName, function (Blueprint $table) use ($index) {
                            foreach ($index as $columnName => $indexedColumns) {
                                $table->index($indexedColumns);
                            }
                        });
                    }
                }

                if (isset($schemaDefinition->uniqueIndexCreates)) {
                    foreach ($schemaDefinition->uniqueIndexCreates as $tableName => $index) {
                        self::checkIsStringSqlValid($tableName);
                        Schema::connection($site_id)->table($tableName, function (Blueprint $table) use ($index) {
                            foreach ($index as $columnName => $indexedColumns) {
                                $table->unique($indexedColumns);
                            }
                        });
                    }
                }

                if (isset($schemaDefinition->columnDrops)) {
                    foreach ($schemaDefinition->dropColumns as $tableName => $columnName) {
                        Schema::connection($site_id)->dropColumns($tableName, $columnName);
                    }
                }

                if (isset($schemaDefinition->tableDrops)) {
                    foreach ($schemaDefinition->dropTables as $key => $tableName) {
                        Schema::connection($site_id)->dropIfExists($tableName);
                    }
                }

                $site->db_schema_version = $versionNumber;
                $site->save();
            }

            $rcMandatoryFields = self::addMandatoryUserDataColumns($site_id, $driver, trigger: 'onUpgradeSiteDatabase');

            if (! $rcMandatoryFields->operation_successful) {
                return $rcMandatoryFields;
            }

            return $rc;

        } catch (Throwable $th) {
            Log::debug('onUpgradeSiteDatabase '.$th->getMessage().' '.$th->getFile().' '.$th->getLine());
            $rc->operation_successful = false;
            $rc->error_message = $th->getMessage();
            $rc->data = $th->getMessage().' '.$th->getFile().' '.$th->getLine();
            $rc->debug_data = $th->getMessage().' '.$th->getFile().' '.$th->getLine();

            return $rc;
        }
    }

    public static function checkIsStringSqlValid(string $str): void
    {
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $str)) {
            throw new Exception('String value is not valid for sql manipulation: '.$str, 1);
        }
        if (strlen($str) > 64) {
            throw new Exception('String too long for sql manipulation. Max 64: '.$str, 1);
        }
    }

    public static function addMandatoryUserDataColumns($site_id, $driver, $trigger): ResultContainer
    {
        Log::debug('addMandatoryUserDataColumns '.$site_id.' '.$trigger);

        $rc = new ResultContainer;
        $rc->operation_successful = true;

        // try {
            $mandatoryNullableColumnsDefinitions = [
                SysProps::_sn_table,
                SysProps::_sn_signer,
                SysProps::_sn_visitor_id,

                SysProps::_sn_entity_created,
                SysProps::_sn_entity_updated,

                /* Required when returning results */
                SysProps::_sn_signer_verification_key_base64,
                SysProps::_sn_record_json,
                SysProps::_sn_signature,
                SysProps::_sn_entity_deleted,
            ];

            $tableNames = Schema::connection($site_id)->getTableListing();

            foreach ($tableNames as $tableName) {
                Log::debug('addMandatoryUserDataColumns '.$tableName);
                /* _sn_entity_id */
                Schema::connection($site_id)->table($tableName, function (Blueprint $table) use ($tableName, $site_id) {
                    if (! Schema::connection($site_id)->hasColumn($tableName, SysProps::_sn_entity_id)) {
                        $table->string(SysProps::_sn_entity_id)->nullable();
                    }
                });

                /* mandatoryNullableColumnsDefinitions */
                Schema::connection($site_id)->table($tableName, function (Blueprint $table) use ($mandatoryNullableColumnsDefinitions, $tableName, $site_id) {
                    foreach ($mandatoryNullableColumnsDefinitions as $key => $mandatoryNullableColumnsDefinition) {
                        if (! Schema::connection($site_id)->hasColumn($tableName, $mandatoryNullableColumnsDefinition)) {
                            $table->text($mandatoryNullableColumnsDefinition)->nullable();
                        }
                    }
                });
                
                /* Indexes */
                Schema::connection($site_id)->table($tableName, function (Blueprint $table) use ($tableName, $site_id) {
                    $indexName = Str::replace('.', '_', $tableName.SysProps::_sn_entity_id.'_index');
                    if (! Schema::connection($site_id)->hasIndex($tableName, $indexName)) {
                        $table->index(SysProps::_sn_entity_id, $indexName);
                    }
                });
                
            }
        // } catch (Throwable $th) {
        //     $rc->operation_successful = false;
        //     $rc->error_message = $th->getMessage();
        // }

        return $rc;
    }

    /**
     * For larger site:
     * Command sn:recreate-site-db {site_id}
     */
    public function recreateSiteDb(Request $request): ResultContainer
    {
        $request->validate(['site_id' => Consts::siteIdValidationRule]);
        $site_id = $request->site_id;
        $resultContainer = new ResultContainer;
        $resultContainer->operation_successful = true;

        $site = Site::whereSiteId($site_id)->with('most_recent_site_definition')->first();

        $siteConfig = (new SiteConfigService)->getSiteConfig($site);

        if (! $siteConfig || ! $siteConfig->site_Has_Database) {
            $resultContainer->operation_successful = false;
            $resultContainer->error_message = 'Site Config not available';
            $resultContainer->data = $resultContainer->error_message;

            return $resultContainer;
        }

        /* Site has No database, ignore inputs */
        if (! $siteConfig->site_Has_Database) {
            info('parseUpsertIncomingVisitorRecord site_Has_Database');
            $resultContainer->operation_successful = false;
            $resultContainer->error_message = 'Site has no Database';
            $resultContainer->data = $resultContainer->error_message;

            return $resultContainer;
        }

        /* Without grant records */
        $visitorRecords = VisitorRecord::where([
            ['site_id', $site->site_id],
        ])->get();

        (new SiteDatabaseService)->deleteSiteDatabase($site_id);

        $rc = (new SiteDatabaseService)->prepareSiteDatabase($site_id);
        if (! $rc->operation_successful) {
            return $rc;
        }

        $successfulRecordsCount = 0;
        $failedRecordsCount = 0;
        $resultContainer->debug_data = [];
        foreach ($visitorRecords as $key => $visitorRecord) {
            if (in_array($visitorRecord->table, [SysProps::_sn_visitor_resource_upload])) {
                continue;
            }

            if ($site->is_to_be_hosted && ! $visitorRecord->is_grant_record)
            $resultUpsert = (new SiteDatabaseService)->parseUpsertIncomingVisitorRecord(
                $visitorRecord, $visitorRecord->site_id);

            if (! $resultUpsert->operation_successful) {
                /* Count failure instead */
                $failedRecordsCount++;
                $resultContainer->operation_successful = false;
                $resultContainer->error_message = 'Upsert failed: '.$resultUpsert->error_message;
                array_push($resultContainer->debug_data, 'Upsert failed: '.$resultUpsert->error_message);
            } else {
                $successfulRecordsCount++;
            }
            // dump('Successful Records Count: '.$successfulRecordsCount.' | Failed Records Count: '.$failedRecordsCount);
        }

        $resultContainer->data =
            'Successful Records Count: '.$successfulRecordsCount.' | Failed Records Count: '.$failedRecordsCount;

        return $resultContainer;
    }

    public function buildQuerySiteDb($queryParameters, $site_id, $tableName): Builder
    {
        $queryBuilder = DB::connection($site_id)->table($tableName);

        if (isset($queryParameters['where'])) {

            foreach ($queryParameters['where'] as $key => $value) {
                $queryBuilder->where($value[0], $value[1], $value[2]);
            }
        }

        if (isset($queryParameters['orWhere'])) {
            $queryBuilder->where(function (Builder $query) use ($queryParameters) {
                foreach ($queryParameters['orWhere'] as $key => $value) {
                    $query->orWhere($value[0], $value[1], $value[2]);
                }
            });
        }

        if (isset($queryParameters['whereNull'])) {
            $queryBuilder->whereNull($queryParameters['whereNull']);
        }

        if (isset($queryParameters['whereNotNull'])) {
            $queryBuilder->whereNotNull($queryParameters['whereNotNull']);
        }

        if (isset($queryParameters['whereIn'])) {
            $queryBuilder->whereIn($queryParameters['whereIn'][0], $queryParameters['whereIn'][1]);
        }

        if (isset($queryParameters['whereNotIn'])) {
            $queryBuilder->whereNotIn($queryParameters['whereNotIn'][0], $queryParameters['whereNotIn'][1]);
        }

        if (isset($queryParameters['orderBy'])) {
            $queryBuilder->orderBy($queryParameters['orderBy'][0], $queryParameters['orderBy'][1]);
        } else {
            $queryBuilder->orderBy(SysProps::_sn_entity_id);
        }

        if (isset($queryParameters['limit'])) {
            $queryBuilder->limit($queryParameters['limit']);
        }

        $queryBuilder->whereNull(SysProps::_sn_entity_deleted);

        return $queryBuilder;
    }
}
