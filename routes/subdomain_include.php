<?php

use App\Http\Controllers\Auth\AuthenticatedVisitorSessionController;
use App\Http\Controllers\Auth\RegisteredVisitorController;
use App\Http\Controllers\BackendController;
use App\Http\Controllers\CsrfCookieController;
use App\Http\Controllers\QueryController;
use App\Http\Controllers\SiteServerController;
use App\Http\Controllers\SiteSSEController;
use App\Http\Controllers\SitesVisitorController;
use App\Http\Controllers\UpsertController;
use App\Http\Controllers\UtilsController;
use App\Http\H;
use App\Http\Middleware\NoFrames;
use Illuminate\Support\Facades\Route;

$isServeronetWelcomeSite = H::isServeronetWelcomeSite();
if ($isServeronetWelcomeSite) {
    return;
}

$SERVE_OWNER_ONLY = config('sn.serve_owner_only');
$api_token_backend_enabled = config('sn.api_token_backend_enabled');

$owner_only_capable = $SERVE_OWNER_ONLY ? ['auth_owner:owner'] : [];
$domain = $domain ?? '';
$domain = $subdomainLevel.$domain;
$routePrefix = $domain;

Route::get('site_api/v1/sse_stream', [SiteSSEController::class, 'streamSSE'])
    ->middleware('throttle:high_rate')
    ->middleware($owner_only_capable)
    ->middleware('single_site_id');

Route::get('site_assets/js/feeling-lib.js', [UtilsController::class, 'getFeelingLib'])
    ->name($routePrefix.'feeling_lib')->middleware('throttle:high_rate');

Route::get('site_assets/img/logo.png', [UtilsController::class, 'getSnLogo'])
    ->name($routePrefix.'logo')->middleware('throttle:high_rate');

Route::get('register', [RegisteredVisitorController::class, 'create'])->name($routePrefix.'register')
    ->middleware($owner_only_capable)->middleware('single_site_id');

Route::post('register', [RegisteredVisitorController::class, 'store'])->middleware($owner_only_capable)->middleware('single_site_id');

Route::get('login', [AuthenticatedVisitorSessionController::class, 'create'])->name($routePrefix.'login')
    ->middleware($owner_only_capable)->middleware('single_site_id');

Route::post('login', [AuthenticatedVisitorSessionController::class, 'store'])
    ->middleware($owner_only_capable)
    ->middleware('single_site_id');

Route::get('visitor_control_panel', [SitesVisitorController::class, 'showVisitorsControlPanel'])
    ->name($routePrefix.'visitor_control_panel')
    ->middleware('throttle:high_rate')
    ->middleware(['auth_visitor:visitor']);

Route::get('visitor_actions/recent_identities/get_recent_identities', [SiteServerController::class, 'getRecentIdentities'])
    ->name($routePrefix.'get_recent_identities')
    ->middleware('throttle:high_rate')->middleware($owner_only_capable)
    ->withoutMiddleware([
        NoFrames::class,
    ]);

$owner_only_capable = $SERVE_OWNER_ONLY ? ['auth_owner:owner'] : [];

Route::get('logout', [AuthenticatedVisitorSessionController::class, 'destroy'])
    ->name($domain.'logout')->middleware($owner_only_capable)
    ->middleware('auth_visitor:visitor')
    ->middleware('single_site_id');

Route::get('visitor_actions_identity_created', [RegisteredVisitorController::class, 'identitySummary'])
    ->name($domain.'identity_created')
    ->middleware('auth_visitor:visitor')
    ->middleware($owner_only_capable);

Route::post('visitor_actions_download_identity', [RegisteredVisitorController::class, 'downloadIdentity'])
    ->name($domain.'download_identity')
    ->middleware('auth_visitor:visitor')
    ->middleware($owner_only_capable);

Route::post('visitor_actions/remove_recent_visitor_id', [SitesVisitorController::class, 'removeRecentVisitorId'])
    ->name($routePrefix.'remove_recent_visitor_id')
    ->middleware('throttle:high_rate')
    ->middleware('auth_visitor:visitor')
    ->middleware($owner_only_capable);

Route::get('visitor_actions/set_adults_only_access_cookie', [SitesVisitorController::class, 'setAdultsOnlyAccessCookie'])
    ->name($routePrefix.'set_adults_only_access_cookie')
    ->middleware('throttle:high_rate')
    ->middleware('single_site_id')
    ->middleware($owner_only_capable);

Route::post('visitor_actions/forget_visitor', [SitesVisitorController::class, 'forgetVisitor'])
    ->name($routePrefix.'forget_visitor')
    ->middleware('throttle:high_rate')
    ->middleware('auth_visitor:visitor')
    ->middleware('single_site_id')
    ->middleware($owner_only_capable);

Route::post('visitor_actions/toggle_api_token_upsert_endpoints', [
    SitesVisitorController::class,
    'toggleApiTokenVisitorBackends',
])
    ->name($routePrefix.'toggle_api_token_upsert_endpoints')
    ->middleware('throttle:high_rate')
    ->middleware($owner_only_capable)
    ->middleware('auth_visitor:visitor');

// Retrieval in progress
Route::get('/retrieving', [SitesVisitorController::class, 'showRetrievalPage'])
    ->name($routePrefix.'retrieving')
    ->middleware($owner_only_capable)
    ->middleware('throttle:high_rate')
    ->middleware('no-cache');

if ($api_token_backend_enabled) {
    Route::post('site_api/v1/api_token_visitor_record_create', [UpsertController::class, 'createEndpointApi'])
        ->name($routePrefix.'api_token_visitor_record_create')->middleware('throttle:high_rate')->middleware('auth_visitor:visitor_api')
        ->middleware('single_site_id');
}

if ($api_token_backend_enabled) {
    Route::post('site_api/v1/api_token_visitor_file_upload', [BackendController::class, 'uploadVisitorFileApiToken'])
        ->name($routePrefix.'api_token_visitor_file_upload')->middleware('throttle:high_rate')
        ->middleware('auth_visitor:visitor_api')
        ->middleware('single_site_id');
}

// Backend section - must be before catch all
Route::get('site_api/v1/csrf-cookie', [CsrfCookieController::class, 'show'])
    ->middleware('throttle:high_rate')
    ->middleware('single_site_id');

Route::post('site_api/v1/query_endpoint', [QueryController::class, 'siteApiQueryEndpoint'])
    ->name($routePrefix.'query_endpoint')->middleware('throttle:high_rate')
    ->middleware('single_site_id')
    ->middleware($owner_only_capable);

if ($api_token_backend_enabled) {
    Route::post('site_api/v1/api_query_endpoint', [QueryController::class, 'siteApiQueryEndpointToken'])
        ->middleware('throttle:high_rate')
        ->middleware('auth_visitor:visitor_api')
        ->middleware('single_site_id');
}

Route::post('site_api/v1/visitor_files', [BackendController::class, 'getSiteVisitorFiles'])
    ->name($routePrefix.'visitor_files')
    ->middleware($owner_only_capable)
    ->middleware('throttle:high_rate')
    ->middleware('single_site_id');

Route::get('visitor_file/{visitor_entity_id}', [BackendController::class, 'getVisitorsFileContent'])
    ->name($routePrefix.'visitor_file')
    ->middleware('throttle:high_rate')
    ->middleware($owner_only_capable)
    ->middleware('single_site_id');

Route::post('site_api/v1/site_state', [BackendController::class, 'getSiteState'])
    ->name($routePrefix.'site_state')->middleware('throttle:high_rate')
    ->middleware($owner_only_capable)
    ->middleware('single_site_id');

// Public Site backend
Route::post('site_api/v1/is_authenticated', [BackendController::class, 'isAuthenticated'])
    ->middleware('throttle:high_rate')
    ->middleware($owner_only_capable)
    ->middleware('single_site_id')
    ->middleware($owner_only_capable);

if ($api_token_backend_enabled) {
    Route::post('site_api/v1/api_is_authenticated', [BackendController::class, 'isAuthenticatedApi'])
        ->middleware('throttle:high_rate')->middleware('auth_visitor:visitor_api')
        ->middleware('single_site_id');
}

Route::post('site_api/v1/client_config', [BackendController::class, 'getClientConfig'])
    ->middleware('throttle:high_rate')
    ->middleware($owner_only_capable)
    ->middleware('single_site_id');

Route::post('site_api/v1/mime_types_mapping', [BackendController::class, 'getMimeTypesMapping'])
    ->middleware('throttle:high_rate')
    ->middleware($owner_only_capable)
    ->middleware('single_site_id');

Route::post('site_api/v1/visitor_record_create', [UpsertController::class, 'createEndpoint'])
    ->name($routePrefix.'visitor_record_create')
    ->middleware('auth_visitor:visitor')
    ->middleware('throttle:high_rate')
    ->middleware($owner_only_capable)
    ->middleware('single_site_id');

if ($api_token_backend_enabled) {
    Route::post('site_api/v1/api_token_visitor_record_update', [QueryController::class, 'updateEndpointApi'])
        ->name($routePrefix.'api_token_visitor_record_update')->middleware('throttle:high_rate')->middleware('auth_visitor:visitor_api')
        ->middleware('single_site_id');
}

Route::post('site_api/v1/visitor_record_update', [UpsertController::class, 'updateEndpoint'])
    ->name($routePrefix.'visitor_record_update')
    ->middleware('auth_visitor:visitor')
    ->middleware('throttle:high_rate')
    ->middleware($owner_only_capable)
    ->middleware('single_site_id');

Route::post('site_api/v1/visitor_file_upload', [BackendController::class, 'uploadVisitorFile'])
    ->name($routePrefix.'visitor_file_upload')
    ->middleware('throttle:high_rate')
    ->middleware(['auth_visitor:visitor'])
    ->middleware($owner_only_capable)
    ->middleware('single_site_id');

Route::post('site_api/v1/visitor_file_delete', [BackendController::class, 'deleteVisitorFile'])
    ->name($routePrefix.'visitor_file_delete')->middleware('throttle:high_rate')
    ->middleware($owner_only_capable)
    ->middleware(['auth_visitor:visitor'])
    ->middleware('single_site_id');

Route::post('site_api/v1/get_visitor_state', [BackendController::class, 'getVisitorState'])
    ->middleware('throttle:high_rate')->middleware(['auth_visitor:visitor'])
    ->middleware($owner_only_capable)
    ->middleware('single_site_id');

Route::post('site_api/v1/check_can_post_to_table', [BackendController::class, 'handleCheckCanPostToTable'])
    ->middleware('throttle:high_rate')->middleware(['auth_visitor:visitor'])
    ->middleware($owner_only_capable)
    ->middleware('single_site_id');

Route::post('site_api/v1/check_can_upload', [BackendController::class, 'handleCheckCanUpload'])
    ->middleware('throttle:high_rate')->middleware(['auth_visitor:visitor'])
    ->middleware($owner_only_capable)
    ->middleware('single_site_id');

$owner_only_capable = $SERVE_OWNER_ONLY ? ['auth_owner:owner'] : [];

Route::get('/', [SiteServerController::class, 'serveSiteResource'])
    ->name($routePrefix.'home')
    ->middleware($owner_only_capable)
    ->middleware('single_site_id');

Route::get('/{res_id?}', [SiteServerController::class, 'serveSiteResource'])
    ->where('res_id', '(.*)')->name($routePrefix.'site')
    ->middleware($owner_only_capable)
    ->middleware('single_site_id');
