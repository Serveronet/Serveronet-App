<?php

use App\Http\Controllers\ActiveClientController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\BackendController;
use App\Http\Controllers\BackgroundProcessingController;
use App\Http\Controllers\BuiltInTrackerController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientManagerController;
use App\Http\Controllers\PassiveClientController;
use App\Http\Controllers\PublishSitesController;
use App\Http\Controllers\QueryController;
use App\Http\Controllers\ReplicationController;
use App\Http\Controllers\TorrentTrackersController;
use App\Http\H;
use App\Services\InternalCallService;
use Illuminate\Support\Facades\Route;

$SERVE_OWNER_ONLY = config('sn.serve_owner_only');
$api_token_backend_enabled = config('sn.api_token_backend_enabled');

$isServeronetWelcomeSite = H::isServeronetWelcomeSite();
if ($isServeronetWelcomeSite) {
    return;
}

/* Endpoints with negative effects */
Route::post('p2p_api/v1/check_peer', [ClientController::class, 'handlePeerStateCheck'])
    ->middleware('throttle:medium_rate');

Route::any('/announce', [BuiltInTrackerController::class, 'handleAnnounceByUrl'])
    ->name('announce')->middleware('throttle:medium_rate');
/* End of endpoints with negative effects */

Route::post('p2p_api/v1/serve_external_peer_details', [ClientController::class, 'serveExternalPeerDetails'])
    ->middleware('throttle:medium_rate');

// API
Route::post('p2p_api/v1/receive_message_endpoint_peer', [ClientController::class, 'onReceiveMessage_peer'])
    ->middleware('throttle:medium_rate');

Route::post('p2p_api/v1/receive_message_endpoint_site_definition', [ClientController::class, 'onReceiveMessage_site_definition'])
    ->middleware('throttle:medium_rate');

Route::post('p2p_api/v1/receive_message_endpoint_site_peer', [ClientController::class, 'onReceiveMessage_site_peer'])
    ->middleware('throttle:medium_rate');

Route::post('p2p_api/v1/receive_message_endpoint_visitor_record', [ClientController::class, 'onReceiveMessage_visitor_record'])
    ->middleware('throttle:medium_rate');

Route::post('p2p_api/v1/receive_message_endpoint_visitor_resource', [ClientController::class, 'onReceiveMessage_visitor_resource'])
    ->middleware('throttle:medium_rate');

Route::post('p2p_api/v1/check_site_hosting_eagerness', [ClientController::class, 'handleSiteHostingEagernessCheck'])
    ->middleware('throttle:medium_rate');

Route::post('p2p_api/v1/check_hashes_hosting_state', [ClientController::class, 'handleHashesHostingStateCheck'])
    ->middleware('throttle:medium_rate');

Route::post('p2p_api/v1/check_site_hosting_state', [ClientController::class, 'handleSiteHostingStateCheck'])
    ->middleware('throttle:medium_rate');

Route::post('p2p_api/v1/resource_upload_endpoint', [ClientController::class, 'handleReceivedResourceUpload'])
    ->middleware('throttle:medium_rate');

Route::post('p2p_api/v1/p2p_visitor_files', [BackendController::class, 'getSiteVisitorFilesP2pApi'])
    ->middleware('throttle:medium_rate');
    
Route::post('p2p_api/v1/handle_passive_client_initiating_session', [ActiveClientController::class, 'handlePassiveClientInitiatingSession'])
    ->middleware('throttle:medium_rate');

Route::post('p2p_api/v1/handle_passive_awaiting_requests', [ActiveClientController::class, 'handlePassiveAwaitingRequests'])
    ->middleware('throttle:high_rate');

Route::post('p2p_api/v1/p2p_query_endpoint', [QueryController::class, 'p2pQueryEndpoint'])
    ->middleware('throttle:high_rate');

Route::post('p2p_api/v1/request_data_endpoint', [ClientController::class, 'handlePeerRequestingData'])
    ->middleware('throttle:high_rate');

Route::post('p2p_api/v1/receive_fulfilled_retrieval', [ClientController::class, 'handleReceiveFulfilledRetrieval'])
    ->middleware('throttle:high_rate');

Route::post('p2p_api/v1/request_modified_between_dates', [ClientController::class, 'handleRequestModifiedBetweenDates'])
    ->middleware('throttle:high_rate');

Route::post('p2p_api/v1/receive_fulfilled_crowd_query', [ClientController::class, 'handleReceiveFulfilledCrowdQuery'])
    ->middleware('throttle:medium_rate');

/* Client Automation section */
if ($api_token_backend_enabled) {
    Route::post('p2p_api/v1/api_publish_site', [PublishSitesController::class, 'publishSite'])
        ->name('api_publish_site')
        ->middleware('auth_admin_api_token:admin_api')
        ->middleware('throttle:low_rate');
}

/* Internal section */
Route::post('internal/replicate_missed_data', [ReplicationController::class, 'replicateMissedData'])
    ->name('replicate_missed_data')->middleware('throttle:medium_rate');

Route::post('internal/response_to_actives_action', [PassiveClientController::class, 'responseToActivesAction'])
    ->name('response_to_actives_action')->middleware('throttle:medium_rate');

Route::post('internal/execute_client_action_wrapper', [BackgroundProcessingController::class, 'executeClientActionWrapper'])
    ->name('execute_client_action_wrapper')->middleware('throttle:medium_rate');

Route::post('internal/handle_internal_call_verification', [AdminController::class, 'handleInternalCallVerification'])
    ->name('handle_internal_call_verification')->middleware('throttle:low_rate');

Route::post('internal/handle_internal_closure', [InternalCallService::class, 'handleInternalClosure'])
    ->name('handle_internal_closure')->middleware('throttle:medium_rate');

Route::post('internal/peer_replication_session_handler', [ReplicationController::class, 'peerReplicationSessionHandler'])
    ->name('peer_replication_session_handler')->middleware('throttle:medium_rate');

Route::post('internal/announce_to_tracker', [TorrentTrackersController::class, 'announceToTracker'])
    ->name('announce_to_tracker')->middleware('throttle:medium_rate');

Route::post('internal/handle_crowd_query_order', [QueryController::class, 'handleCrowdQueryOrder'])
    ->name('handle_crowd_query_order')->middleware('throttle:medium_rate');

Route::post('internal/handle_crowd_retrieval_order', [ClientController::class, 'handleCrowdRetrievalOrder'])
    ->name('handle_crowd_retrieval_order')->middleware('throttle:medium_rate');

Route::post('internal/loop_maintainer', [ClientManagerController::class, 'loopMaintainer'])
    ->name('loop_maintainer')->middleware('throttle:medium_rate');

Route::post('internal/passive_session_worker', [PassiveClientController::class, 'passiveSessionWorker'])
    ->name('passive_session_worker')->middleware('throttle:medium_rate');

