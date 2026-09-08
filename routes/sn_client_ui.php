<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\AuthenticatedAdminSessionController;
use App\Http\Controllers\BackgroundProcessingController;
use App\Http\Controllers\BuiltInTrackerController;
use App\Http\Controllers\ClientManagerController;
use App\Http\Controllers\FirstRunController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\ListingsController;
use App\Http\Controllers\PortForwardController;
use App\Http\Controllers\PublishSitesController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SiteManagerController;
use App\Http\Controllers\TorrentTrackersController;
use App\Http\Controllers\UpdaterController;
use App\Http\Controllers\UtilsController;
use App\Http\H;
use App\Services\AdminUiService;
use App\Services\SiteConfigService;
use Illuminate\Support\Facades\Route;

if (! H::isServeronetWelcomeSite()) {
    foreach (H::getUiDomains() as $key => $domain) {

        Route::domain($domain)->group(function () use ($domain) {

            $domainRouteNamePrefix = $domain;

            $SERVE_OWNER_ONLY = config('sn.serve_owner_only');

            $owner_only_capable = $SERVE_OWNER_ONLY ? ['auth_owner:owner'] : [];

            Route::get('admin/admin_login', [AuthenticatedAdminSessionController::class, 'create'])
                ->name($domainRouteNamePrefix.'admin_login');

            Route::post('admin/admin_login', [AuthenticatedAdminSessionController::class, 'store']);

            Route::any('admin/admin_logout', [AuthenticatedAdminSessionController::class, 'destroy'])
                ->middleware($owner_only_capable)
                ->middleware('auth_admin:admin')
                ->name($domainRouteNamePrefix.'admin_logout');

            if (! H::inSingleSiteMode(request())) {
                Route::get('/', [GuestController::class, 'index'])->name('0'.$domainRouteNamePrefix.'home');
            }

            $SERVE_OWNER_ONLY = config('sn.serve_owner_only');

            Route::get('favicon.ico', [UtilsController::class, 'getFavicon'])
                ->name($domainRouteNamePrefix.'favicon')->middleware($owner_only_capable)->middleware('throttle:high_rate');

            Route::post('firstrun/check_mysql_connectivity', [FirstRunController::class, 'checkMysqlConnectivity'])
                ->middleware('throttle:medium_rate');

            Route::match(['post', 'get'], 'main_loop_requester', [ClientManagerController::class, 'mainLoopRequester'])
                ->name($domainRouteNamePrefix.'main_loop_requester')->middleware('auth_admin:admin')->middleware('throttle:low_rate');

            Route::post('start_main_loop_endpoint', [ClientManagerController::class, 'startMainLoopIfRequired'])
                ->name($domainRouteNamePrefix.'start_main_loop_endpoint')
                ->middleware('auth_admin:admin')
                ->middleware('throttle:low_rate');

            Route::match(['post', 'get'], 'signal_main_loop_stop', [ClientManagerController::class, 'signalMainLoopStop'])
                ->name($domainRouteNamePrefix.'signal_main_loop_stop')
                ->middleware('auth_admin:admin')
                ->middleware('throttle:low_rate');

            $owner_only_capable = $SERVE_OWNER_ONLY ? ['auth_owner:owner'] : [];

            Route::get('sites', [ListingsController::class, 'guestGridOfSites'])
                ->name($domainRouteNamePrefix.'guest_sites')
                ->middleware($owner_only_capable)
                ->middleware('throttle:medium_rate');

            Route::post('complete_first_run', [FirstRunController::class, 'completeFirstRun']);

            /* End Guest UI section */

            /* Admin UI section */
            Route::middleware(['auth_admin:admin'])->group(function () use ($domainRouteNamePrefix) {

                Route::get('admin/developed_sites', [PublishSitesController::class, 'developedSites'])
                    ->name($domainRouteNamePrefix.'developed_sites')->middleware('throttle:high_rate');

                Route::get('admin/control_panel', [AdminController::class, 'controlPanel'])->name($domainRouteNamePrefix.'control_panel')
                    ->middleware('throttle:high_rate');

                Route::get('admin/advanced_control_panel', [AdminController::class, 'advancedControlPanel'])
                    ->name($domainRouteNamePrefix.'advanced_control_panel')
                    ->middleware('throttle:high_rate');

                Route::get('admin/manage_log', [AdminController::class, 'manageLog'])->name($domainRouteNamePrefix.'manage_log');

                Route::post('admin/debug_download_log', [AdminUiService::class, 'downloadLog'])
                    ->name($domainRouteNamePrefix.'debug_download_log')
                    ->middleware('throttle:high_rate');

                Route::get('admin/scheduleOnDemandBackgroundAction', [BackgroundProcessingController::class, 'scheduleOnDemandBackgroundAction'])
                    ->name($domainRouteNamePrefix.'scheduleOnDemandBackgroundAction')->middleware('throttle:high_rate');

                Route::get('admin/add_manually', [AdminController::class, 'AddManuallyIndex'])->name($domainRouteNamePrefix.'add_manually')
                    ->middleware('throttle:high_rate');

                Route::post('admin/add_manually_endpoint', [AdminController::class, 'AddManually'])
                    ->name($domainRouteNamePrefix.'add_manually_endpoint')->middleware('throttle:high_rate');

                Route::get('admin/settings', [SettingsController::class, 'list_settings'])->name($domainRouteNamePrefix.'settings')
                    ->middleware('throttle:high_rate');

                Route::post('admin/publish_site', [PublishSitesController::class, 'publishSite'])->name($domainRouteNamePrefix.'publish_site')
                    ->middleware('throttle:high_rate');

                Route::post('admin/export_site_config_json', [PublishSitesController::class, 'exportSiteConfigJson'])
                    ->name($domainRouteNamePrefix.'export_site_config_json')->middleware('throttle:high_rate');

                Route::get('admin/example_site_config', [SiteConfigService::class, 'getExampleSiteConfig'])->name($domainRouteNamePrefix.'example_site_config')
                    ->middleware('throttle:high_rate');

                Route::get('admin/admin_sites/{site_id?}', [ListingsController::class, 'adminSitesGrid'])
                    ->name($domainRouteNamePrefix.'admin_sites')->middleware('throttle:high_rate');

                Route::get('admin/background_schedule_executions_table', [ListingsController::class, 'backgroundScheduleExecutionsTable'])
                    ->name($domainRouteNamePrefix.'background_schedule_executions_table')->middleware('throttle:high_rate');

                Route::get('admin/site_admin_actions', [ListingsController::class, 'siteAdminActions'])
                    ->name($domainRouteNamePrefix.'site_admin_actions')->middleware('throttle:high_rate');

                Route::get('admin/admin_peers', [ListingsController::class, 'adminPeers'])
                    ->name($domainRouteNamePrefix.'admin_peers')->middleware('throttle:high_rate');

                Route::get('admin/replication_sessions', [ListingsController::class, 'replicationSessions'])
                    ->name($domainRouteNamePrefix.'replication_sessions')->middleware('throttle:high_rate');

                Route::get('admin/peer_replication_sessions', [ListingsController::class, 'peerReplicationSessions'])
                    ->name($domainRouteNamePrefix.'peer_replication_sessions')->middleware('throttle:high_rate');

                Route::get('admin/internal_requests', [ListingsController::class, 'internalRequests'])
                    ->name($domainRouteNamePrefix.'internal_requests')->middleware('throttle:high_rate');

                Route::get('admin/built_in_tracker', [BuiltInTrackerController::class, 'builtInTracker'])
                    ->name($domainRouteNamePrefix.'built_in_tracker')->middleware('throttle:high_rate');

                Route::post('admin/destroy_by_id', [AdminUiService::class, 'destroyById'])
                    ->middleware('throttle:high_rate');

                Route::get('admin/admin_site_peers/{site_id?}', [ListingsController::class, 'adminSitePeers'])
                    ->name($domainRouteNamePrefix.'admin_site_peers')->middleware('throttle:high_rate');

                Route::get('admin/resources', [ListingsController::class, 'resourcesListing'])
                    ->name($domainRouteNamePrefix.'resources')->middleware('throttle:high_rate');

                Route::get('admin/visitors', [ListingsController::class, 'visitorsListing'])
                    ->name($domainRouteNamePrefix.'visitors')->middleware('throttle:high_rate');

                Route::get('admin/passive_sessions', [ListingsController::class, 'passiveSessionsListing'])
                    ->name($domainRouteNamePrefix.'passive_sessions')->middleware('throttle:high_rate');

                Route::get('admin/admin_site_definitions/{site_id?}', [ListingsController::class, 'adminSiteDefinitions'])
                    ->name($domainRouteNamePrefix.'admin_site_definitions')->middleware('throttle:high_rate');

                Route::post('admin/execute_client_action', [BackgroundProcessingController::class, 'executeClientAction'])
                    ->name($domainRouteNamePrefix.'execute_client_action')->middleware('throttle:high_rate');

                Route::get('admin/site_hosting_overview/{site_id}', [SiteManagerController::class, 'showSiteHostingOverview'])
                    ->name($domainRouteNamePrefix.'site_hosting_overview')->middleware('throttle:high_rate');

                Route::get('admin/site_visitor_resources_overview/{site_id}', [SiteManagerController::class, 'siteVisitorResourcesOverview'])
                    ->name($domainRouteNamePrefix.'site_visitor_resources_overview')->middleware('throttle:high_rate');

                Route::get('admin/site_peers_overview', [SiteManagerController::class, 'showSitePeersOverview'])
                    ->name($domainRouteNamePrefix.'site_peers_overview')->middleware('throttle:high_rate');

                Route::get('admin/site_status_overview', [SiteManagerController::class, 'showSiteStatusOverview'])
                    ->name($domainRouteNamePrefix.'site_status_overview')->middleware('throttle:high_rate');

                Route::get('admin/dashboard', [AdminController::class, 'adminDashboard'])
                    ->name($domainRouteNamePrefix.'admin_dashboard')->middleware('throttle:high_rate');

                Route::post('admin/test_tor_access', [AdminController::class, 'testTorAccess'])
                    ->name($domainRouteNamePrefix.'test_tor_access')->middleware('throttle:high_rate');

                Route::post('admin/test_ipfs', [AdminController::class, 'testIpfs'])
                    ->name($domainRouteNamePrefix.'test_ipfs')->middleware('throttle:high_rate');

                Route::post('admin/detect_external_ip', [UtilsController::class, 'handleExternalIpDetection'])
                    ->name($domainRouteNamePrefix.'detect_external_ip')->middleware('throttle:medium_rate');

                Route::get('admin/trackers', [TorrentTrackersController::class, 'index'])
                    ->name($domainRouteNamePrefix.'trackers')->middleware('throttle:high_rate');

                Route::post('admin/update_trackers', [TorrentTrackersController::class, 'store'])
                    ->name($domainRouteNamePrefix.'update_trackers')->middleware('throttle:high_rate');

                Route::post('admin/get_newtrackon_trackers', [TorrentTrackersController::class, 'getNewTrackersFromNewTrackon'])
                    ->name($domainRouteNamePrefix.'get_newtrackon_trackers')->middleware('throttle:high_rate');

                Route::post('admin/update_external_port', [PortForwardController::class, 'updateExternalPort'])
                    ->name($domainRouteNamePrefix.'update_external_port')->middleware('throttle:high_rate');

                Route::post('admin/modify_symlink', [PublishSitesController::class, 'toggleDevSiteSymlink'])
                    ->name($domainRouteNamePrefix.'modify_symlink')->middleware('throttle:high_rate');

                Route::post('admin/toggle_control_panel_property', [AdminController::class, 'toggleControlPanelProperty'])
                    ->name($domainRouteNamePrefix.'toggle_control_panel_property')->middleware('throttle:high_rate');

                Route::get('admin/resources_maintenance', [BackgroundProcessingController::class, 'resourcesMaintenance'])
                    ->name($domainRouteNamePrefix.'resources_maintenance')->middleware('throttle:high_rate');

                /* Admin xhr */
                Route::get('admin/get_settings', [SettingsController::class, 'getSettings'])
                    ->middleware('throttle:high_rate');

                Route::post('admin/update_setting', [SettingsController::class, 'updateSetting'])
                    ->middleware('throttle:high_rate');

                Route::get('admin/create_sym_links', [UtilsController::class, 'createSymLinks'])
                    ->middleware('throttle:high_rate');

                Route::post('admin/save_enabled_lists', [AdminUiService::class, 'saveEnabledLists'])
                    ->name($domainRouteNamePrefix.'save_enabled_lists')->middleware('throttle:high_rate');

                Route::post('admin/set_app_url', [AdminUiService::class, 'setAppUrl'])
                    ->name($domainRouteNamePrefix.'set_app_url')->middleware('throttle:high_rate');

                Route::post('admin/set_ui_addresses', [AdminUiService::class, 'setUiAddresses'])
                    ->name($domainRouteNamePrefix.'set_ui_addresses')->middleware('throttle:high_rate');

                Route::post('admin/check_internal_call', [AdminUiService::class, 'checkInternalCall'])
                    ->name($domainRouteNamePrefix.'check_internal_call')->middleware('throttle:high_rate');

                Route::post('admin/update_public_address', [AdminUiService::class, 'updatePublicAddress'])
                    ->name($domainRouteNamePrefix.'update_public_address')->middleware('throttle:high_rate');

                Route::get('admin/update_client_from_central', [UpdaterController::class, 'checkAndUpdateClientFromCentral'])
                    ->name($domainRouteNamePrefix.'update_client_from_central');

                /* Site development */
                Route::post('admin/prepare_sites_database', [PublishSitesController::class, 'prepareSitesDatabase'])
                    ->name($domainRouteNamePrefix.'prepare_sites_database')->middleware('throttle:high_rate');
            });

            Route::post('go_to_site', [GuestController::class, 'goToSite'])
                ->name($domainRouteNamePrefix.'go_to_site')->middleware($owner_only_capable);

        });
    }
}
