<?php

use App\Http\Controllers\CentralController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\UpdaterController;
use App\Http\Controllers\UtilsController;
use App\Http\H;
use Illuminate\Support\Facades\Route;

if (H::isServeronetWelcomeSite()) {
    foreach (H::getUiDomains() as $key => $domain) {
        Route::domain($domain)->group(function () use ($domain) {
            $domainRouteNamePrefix = $domain;

            Route::get('/', [LandingController::class, 'showLanding'])->name('0'.$domainRouteNamePrefix.'home');

            Route::get('favicon.ico', [UtilsController::class, 'getFavicon'])
                ->name($domainRouteNamePrefix.'favicon')->middleware('throttle:high_rate');

            Route::get('/downloads', [CentralController::class, 'downloads'])
                ->name($domainRouteNamePrefix.'downloads')->middleware('throttle:high_rate');

            Route::get('/demos', [CentralController::class, 'demos'])
                ->name($domainRouteNamePrefix.'demos')->middleware('throttle:high_rate');

            Route::get('/all_downloads_listing/{with_dev?}', [CentralController::class, 'allDownloadsListing'])
                ->name($domainRouteNamePrefix.'all_downloads_listing')->middleware('throttle:high_rate');

            Route::get('/download_version', [CentralController::class, 'downloadMostRecentVersion'])
                ->name($domainRouteNamePrefix.'download_version')->middleware('throttle:low_rate');

            $middleware = config('sn.dev_is_central_dev_server')
            ? ['auth_admin_api_token:admin_api', 'throttle:low_rate'] : ['throttle:low_rate'];
            Route::get('/download/{file_name}', [CentralController::class, 'downloadVersionFile'])
                ->name($domainRouteNamePrefix.'download_version_file')
                ->middleware('throttle:low_rate')
                ->middleware($middleware);

            Route::get('/client_update.zip', [CentralController::class, 'downloadMostRecentVersion_client_update'])
                ->name($domainRouteNamePrefix.'client_update.zip')->middleware('throttle:low_rate')
                ->middleware($middleware);

            Route::get('/windows_client_bundle.zip', [CentralController::class, 'downloadMostRecentVersion_windows_client_bundle'])
                ->name($domainRouteNamePrefix.'windows_client_bundle.zip')->middleware('throttle:low_rate')
                ->middleware($middleware);

            Route::get('/linux_and_mac_client_bundle.zip', [CentralController::class, 'downloadMostRecentVersion_linux_and_mac_client_bundle'])
                ->name($domainRouteNamePrefix.'linux_and_mac_client_bundle.zip')->middleware('throttle:low_rate')
                ->middleware($middleware);

            Route::get('/server_bundle.zip', [CentralController::class, 'downloadMostRecentVersion_server_bundle'])
                ->name($domainRouteNamePrefix.'server_bundle.zip')->middleware('throttle:low_rate')
                ->middleware($middleware);

            /* Without auth */
            Route::get('/api/v1/list_versions_endpoint', [CentralController::class, 'handleListVersions'])
                ->name($domainRouteNamePrefix.'list_versions_endpoint')->middleware('throttle:high_rate');

            Route::get('bootstrap_peers.json', [CentralController::class, 'getBootstrapPeers']);

            Route::post('/dev/receive_new_version_endpoint', [CentralController::class, 'receiveNewVersionEndpoint'])
                ->middleware('auth_admin_api_token:admin_api')->middleware('throttle:low_rate');

            /* Admin auth required */
            Route::get('admin/update_client_from_central', [UpdaterController::class, 'checkAndUpdateClientFromCentral'])
                ->middleware(['auth_admin:admin'])->name($domainRouteNamePrefix.'update_client_from_central');

        });
    }
}
