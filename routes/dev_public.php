<?php

use App\Http\Controllers\DevPublicController;
use App\Http\Controllers\PlaygroundController;
use App\Http\H;
use Illuminate\Support\Facades\Route;

$uiDomains = H::getUiDomains();

foreach ($uiDomains as $key => $domain) {
    Route::domain($domain)->group(function () use ($domain) {
        $domainRouteNamePrefix = $domain;

        Route::any('/dfrf', [DevPublicController::class, 'deleteFirstRunFile'])
            ->middleware('auth_admin:admin')
            ->name($domainRouteNamePrefix.'dfrf');

        Route::any('/refresh_config', [DevPublicController::class, 'refreshConfig'])
            ->middleware('throttle:low_rate');

        Route::get('/test', [PlaygroundController::class, 'test'])
            ->middleware('throttle:medium_rate');

        Route::post('/dev/receive_peers_reporting', [DevPublicController::class, 'receivePeersReporting'])
            ->middleware('throttle:low_rate');

        Route::get('/dev/serve_peer_edges_reporting', [DevPublicController::class, 'servePeerEdgesReporting'])
            ->middleware('auth_admin:admin')->middleware('throttle:low_rate');
    });
}
