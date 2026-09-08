<?php

use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\FirstRunController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;

require __DIR__.'/subdomain_site.php';
require __DIR__.'/sn_client_ui.php';
require __DIR__.'/sn_client_api.php';
require __DIR__.'/central.php';
require __DIR__.'/dev_public.php';

Route::get('/on_demand_tls_allow', function (Request $request) {
    if (! in_array(request()->ip(), ['127.0.0.1', '::1'])) {
        abort(403);
    }

    $domain = $request->query('domain');

    if (! $domain) {
        return Response::make('Missing domain', 400);
    }

    if (filter_var($domain, FILTER_VALIDATE_IP)) {
        return Response::make('OK', 200);
    }

    return Response::make('Forbidden', 403);
});

Route::get('first_run_setup', [FirstRunController::class, 'onFirstRunNotCompleted'])
    ->name('first_run_setup');

Route::get('documentation/{doc_tag?}', [DocumentationController::class, 'getDoc'])
    ->name('documentation');

Route::get('/', function () {
    $issueType = 'ui_fallback';
    $message = '';

    return view('first_run_issues', compact('issueType', 'message'));
})->name('ui_fallback');
