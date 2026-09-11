<?php

namespace App\Http\Controllers;

use App\Dicts\ActionTypes;
use App\Dicts\DataTypes;
use App\Dicts\MessageTypes;
use App\Dicts\MimeType;
use App\Dicts\SchedulesTag;
use App\Dicts\SettingIds;
use App\Dicts\SSETypes;
use App\Dicts\SysProps;
use App\Facades\SiteSSEFacade;
use App\Http\Consts;
use App\Http\H;
use App\Models\CachedResource;
use App\Models\PendingAction;
use App\Models\ResultContainer;
use App\Models\ResultContainerCanUploadFiles;
use App\Models\Site;
use App\Models\SiteDefinition;
use App\Models\VisitorResource;
use App\Services\ChunkService;
use App\Services\CryptoService;
use App\Services\IPFSService;
use App\Services\P2pReplicationService;
use App\Services\PeerMixService;
use App\Services\PermissionService;
use App\Services\PQCryptoService;
use App\Services\RemotePeerService;
use App\Services\SiteConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use stdClass;

class BackendController extends Controller
{
    /**
     * Is Authenticated API (API Token)
     *
     * @authenticated

     *
     * @header Accept application/json
     *
     * @bodyParam api_token string required Example: your_visitor_api_token
     *
     * @response {
     *    "success": true,
     *    "data": {
     *        "is_authenticated": true,
     *        "visitor_id": "1abc"
     *    }
     * }
     *
     * @group Api Token Endpoints
     */
    public function isAuthenticatedApi(Request $request)
    {
        return $this->isAuthenticated($request);
    }

    /**
     * Is Authenticated
     *
     * @authenticated
     *
     * @header Accept application/json
     *
     * @response {
     *    "success": true,
     *    "data": {
     *        "is_authenticated": true,
     *        "visitor_id": "1abc"
     *    }
     * }
     *
     * @group Helper APIs
     */
    public function isAuthenticated(Request $request)
    {
        $site_id = $request->site_id;
        H::siteEndpointsCommonResolve($request, $site_id, $domain);
        if ($invalidResult = (new UtilsController)->validatedReturnSiteId($request, $site_id)) {
            return $invalidResult;
        }

        $rm = new stdClass;
        $rm->is_authenticated = Auth::guard('visitor')->check() || Auth::guard('visitor_api')->check();
        $visitor = Auth::guard('visitor')->user() ?? Auth::guard('visitor_api')->user();
        $authenticated_visitor_id = $visitor->visitor_id ?? null;
        $rm->visitor_id = $authenticated_visitor_id ?? null;

        if (H::isDevNode()) {
            $rm->identity_owner = Auth::guard('owner')->user()->name ?? null;
            $rm->identity_admin = Auth::guard('admin')->user()->name ?? null;
            $as = [
                'is_authenticated' => $rm->is_authenticated,
                'visitor_id' => $rm->visitor_id,
                'identity_owner' => $rm->identity_owner,
                'identity_admin' => $rm->identity_admin,
            ];
        } else {
            $as = [
                'is_authenticated' => $rm->is_authenticated,
                'visitor_id' => $rm->visitor_id,
            ];
        }

        return $this->return_success($as);
    }

    /* Client internal usage only */
    public function getListValuesFromProvider(array $searchedKeys, string $listId): ResultContainer
    {
        info('getListValuesFromProvider');
        $resultContainer = new ResultContainer;
        $resultContainer->operation_successful = true;
        $resultContainer->metadata = null;
        $resultContainer->debug_data = [
            'source' => 'getListValuesFromProvider',
            'listId' => $listId,
            'searchedKeys' => $searchedKeys,
        ];

        $listId = explode('@', $listId);
        $listTag = $listId[0];
        $list_provider_site_address = $listId[1];

        if (! $list_provider_site_address || empty($list_provider_site_address)) {
            $resultContainer->operation_successful = false;
            $resultContainer->error_message = 'Not a valid list identifier';

            return $resultContainer;
        }

        $site_id = $list_provider_site_address;

        $listProviderSite = Site::whereSiteId($site_id)->with('most_recent_site_definition')->first();
        if (! $listProviderSite) {

            /* Init hosting */
            $request = new Request;
            $request->merge([
                'site_id' => $listProviderSite,
                'showRetrievalPageForIndex' => false,
            ]);
            (new SiteServerController)->serveSiteResource($request);
        }

        $listProviderSite = Site::whereSiteId($site_id)->with('most_recent_site_definition')->first();

        if (! $listProviderSite?->most_recent_site_definition) {
            $resultContainer->operation_successful = false;
            $resultContainer->error_message = 'Provider site definition could not be retrieved';

            return $resultContainer;
        }

        $siteConfig = (new SiteConfigService)->getSiteConfig($listProviderSite);
        $listProvider = collect($siteConfig->list_Providers)->where('tag', $listTag)->first();

        if (! $listProvider) {
            $resultContainer->operation_successful = false;
            $resultContainer->error_message = 'No such provider list in Site';

            return $resultContainer;
        }

        $table = $listProvider->table;
        $column_key = $listProvider->column_key;

        $request = new Request;
        $q = new stdClass;

        $q->query_parameters = ['table' => $table, 'debug' => true];

        if (! empty($searchedKeys)) {
            $q->query_parameters['whereIn'] = [
                $column_key, $searchedKeys,
            ];
        }

        $j = json_encode($q);
        $request->setMethod('POST');
        $request->merge(json_decode($j, true));
        $request->merge(['site_id' => $site_id]);

        $request->merge(['envelope_only' => false]);
        $request->merge(['response_as_result_container' => true]);
        $rcFromBackend = (new QueryController)->queryEndpoint($request);

        return $rcFromBackend;
    }

    /**
     * Visitor State
     *
     * @authenticated
     *
     * @header Accept application/json
     *
     * @response 200 {
     *    "success": true,
     *    "data": [
     *       {
     *           "visitor_id": "131NUHD9mqeCuNGrchfvFp4ZWeBWRNk6hF",
     *           "is_site_admin": false,
     *       }
     *     ]
     * }
     *
     * @group Helper APIs
     */
    public function getVisitorState($site_id = null)
    {
        if (H::inSingleSiteMode(request())) {
            $site_id = config('single_site_mode.sites')[request()->getHost()];
        }

        $authenticated_visitor_id = Auth::guard('visitor')->user()->visitor_id;

        $request = request();

        $site_id = $request->site_id;
        H::siteEndpointsCommonResolve($request, $site_id, $domain);

        if ($invalidResult = (new UtilsController)->validatedReturnSiteId($request, $site_id)) {
            return $invalidResult;
        }

        $site = Site::whereSiteId($site_id)->with('most_recent_site_definition')->first();

        $siteConfig = (new SiteConfigService)->getSiteConfig($site);

        $isSiteAdminRC = PermissionService::isSiteAdmin($authenticated_visitor_id, $siteConfig);

        if (! $isSiteAdminRC->operation_successful) {
            return $this->return_failure('Failed to check is site admin status. '.$isSiteAdminRC->error_message);
        }

        $result = [
            'visitor_id' => $authenticated_visitor_id,
            'is_site_admin' => $isSiteAdminRC->is_site_admin,
            'is_site_admin_reason' => $isSiteAdminRC->is_site_admin_reason,
        ];

        return $this->return_success($result);
    }

    /**
     * Check Can Post to Table
     *
     * @authenticated
     *
     * @header Accept application/json
     *
     * @bodyParam table {string} Example: posts
     *
     * @response 200 {
     *    "success": true,
     *    "data": [
     *       {
     *           "can_post_to_table": "true",
     *       }
     *     ]
     * }
     *
     * @group Records and Resources
     */
    public function handleCheckCanPostToTable(Request $request)
    {
        $table = $request->table;
        $authenticated_visitor_id = Auth::guard('visitor')->user()->visitor_id;
        $site_id = $request->site_id;

        H::siteEndpointsCommonResolve($request, $site_id, $domain);

        if ($validationResult = (new UtilsController)->validatedReturnSiteId($request, $site_id)) {
            return $validationResult;
        }

        $site = Site::whereSiteId($site_id)->with('most_recent_site_definition')->first();

        $siteConfig = (new SiteConfigService)->getSiteConfig($site);

        // dd($authenticated_visitor_id, $table);
        $resultContainer = PermissionService::hasSignerRightsToTable(
            signer: $authenticated_visitor_id,
            siteConfig: $siteConfig,
            table: $table
        );

        if ($resultContainer->operation_successful) {
            unset($resultContainer->operation_successful);

            return $this->return_success($resultContainer);
        } else {
            return $this->return_failure($resultContainer->error_message);
        }
    }

    /**
     * Check Can Upload
     *
     * @authenticated
     *
     * @header Accept application/json
     *
     * @response 200 {
     *    "success": true,
     *    "data": [
     *       {
     *           "can_upload_files": "true",
     *       }
     *     ]
     * }
     *
     * @group Records and Resources
     */
    public function handleCheckCanUpload(Request $request)
    {
        $site_id = $request->site_id;

        $request = request();
        H::siteEndpointsCommonResolve($request, $site_id, $domain);

        if ($invalidResult = (new UtilsController)->validatedReturnSiteId($request, $site_id)) {
            return $invalidResult;
        }

        $authenticated_visitor_id = Auth::guard('visitor')->user()->visitor_id;
        $signer = $authenticated_visitor_id;

        $site = Site::whereSiteId($site_id)->with('most_recent_site_definition')->first();

        $siteConfig = (new SiteConfigService)->getSiteConfig($site);

        $rc = $this->hasVisitorRightsToUpload($signer, $siteConfig);
        if ($rc->operation_successful) {
            return $this->return_success($rc);
        } else {
            return $this->return_failure($rc->error_message);
        }
    }

    /* Permissions to add files */
    public function hasVisitorRightsToUpload($signer, $siteConfig): ResultContainerCanUploadFiles
    {

        $rc = new ResultContainerCanUploadFiles;
        $rc->operation_successful = true;

        if (! $siteConfig->allow_Visitor_Files) {
            $rc->can_upload_files = false;
            $rc->upload_files_reason = 'Site doesn\'t allow visitor files';

            return $rc;
        }

        $site_id = $siteConfig->_sn_site_id;
        if ($site_id === $signer) {
            $rc->can_upload_files = true;
            $rc->upload_files_reason = 'Visitor is a Site Owner';

            return $rc;
        }

        /* Split value againt :;, and spaces */
        $site_Admin_Signers = preg_split('/[ :;,]+/', $siteConfig->site_Admin_Signers, -1, PREG_SPLIT_NO_EMPTY);

        if (in_array($signer, $site_Admin_Signers)) {
            $rc->can_upload_files = true;
            $rc->upload_files_reason = 'Visitor is a Site Admin (Upload)';

            return $rc;
        }

        $rcGrant = PermissionService::getGrantRecord($signer, SysProps::_sn_visitor_resource_upload, $site_id);

        if (! $rcGrant->operation_successful) {
            $rc->can_upload_files = false;
            $rc->upload_files_reason = 'Failed to get grant record. '.$rcGrant->error_message;
            $rc->error_message = $rcGrant->error_message;

            return $rc;
        }

        if ($rcGrant->data->first()) {
            $rc->can_upload_files = true;
            $rc->upload_files_reason = 'Grant record for Visitor found for Uploads';

            return $rc;
        }

        $rc->can_upload_files = false;
        $rc->upload_files_reason = 'Signer has no rights to upload';

        return $rc;
    }

    /**
     * Site State
     *
     * @header Accept application/json
     *
     * @response 200 {
     *    "success": true,
     *    "data": [
     *       {
     *           "site_id": "abc", ...
     *       }
     *     ]
     * }
     *
     * @group Helper APIs
     */
    public function getSiteState(Request $request)
    {
        $site_id = $request->site_id;

        H::siteEndpointsCommonResolve($request, $site_id, $domain);

        if ($invalidResult = (new UtilsController)->validatedReturnSiteId($request, $site_id)) {
            return $invalidResult;
        }

        $site = Site::whereSiteId($site_id)
            ->with('most_recent_site_definition:'.implode(',', SiteDefinition::$publicProperties))
            ->select(Site::$publicProperties)
            ->first();

        if (! $site) {
            return $this->return_failure('No such a Site');
        }

        $siteConfig = (new SiteConfigService)->getSiteConfig($site);

        $site->siteConfig = $siteConfig;
        $site->domain = $domain;

        return $this->return_success($site);
    }

    public function getSiteVisitorFilesP2pApi(Request $request)
    {
        $request->merge(['with_trashed' => true]);

        return $this->getSiteVisitorFiles($request);
    }

    /**
     * Get Visitor Files
     *
     * @authenticated
     *
     * @header Accept application/json
     *
     * @bodyParam query_parameters string Example: {"visitor_id":"hwy4phcbfbigqgr5duodyntqcz34ypr2bdwx6d3oa36rsuvujt7a"}
     *
     * @response {
     *    "success": true,
     *    "data": [
     *       {
     *           "entity_id": "2026...",
     *           "original_file_name": "..."
     *       }
     *     ]
     * }
     *
     * @group Records and Resources
     */
    public function getSiteVisitorFiles(Request $request)
    {
        try {
            $request->validate([
                'query_id' => ['nullable', 'alpha_dash:ascii', 'max:1024'],
                'with_trashed' => ['nullable', 'boolean'],
                'query_parameters' => ['max:'.(Consts::queryMaxSizeBytes)],
            ]);
        } catch (\Throwable $th) {
            info('getSiteVisitorFiles failure');
            info($th->getMessage().' '.$th->getFile().' '.$th->getLine());

            return $this->return_failure($th->getMessage());
        }

        $query_id = $request->query_id;
        $with_trashed = $request->with_trashed;
        $query_parameters = $request->query_parameters ?? [];
        $fulfiller_id = H::getPublicSelfAddress();
        $debug = $request->debug ?? true;

        $site_id = $request->site_id;
        H::siteEndpointsCommonResolve($request, $site_id, $domain);

        if ($invalidResult = (new UtilsController)->validatedReturnSiteId($request, $site_id)) {
            return $invalidResult;
        }

        $site = Site::whereSiteId($site_id)->with('most_recent_site_definition')->first();

        if (! $site) {
            return $this->return_failure('Site is not known');
        }

        $siteConfig = (new SiteConfigService)->getSiteConfig($site);

        if (! $siteConfig->allow_Visitor_Files) {
            return $this->return_failure('Site doesn\'t allow Visitors files');
        }

        switch (gettype($query_parameters)) {
            case 'string':
                $queryParameters = json_decode($query_parameters, true);
                break;

            case 'array':
                $queryParameters = $query_parameters;
                break;

            case 'object':
                $queryParameters = (array) $query_parameters;
                break;
        }

        if (! $site->is_hosted) {
            $queryParameters['visitor_files_query'] = true;
            $queryParameters['with_trashed'] = $with_trashed;

            $request->merge(['query_parameters' => $queryParameters]);
            $request->merge(['site_id' => $site_id]);
            $request->merge(['ttl' => 3]);
            $request->merge(['originator_peer_id' => H::getSettVal(SettingIds::peer_random_id)]);

            /* askPeersForQueryResult */
            $resultContainer = (new RemotePeerService())->askPeersForQueryResult($request);

            if (! $resultContainer->operation_successful) {
                return $this->return_failure(message: $resultContainer->error_message, debug_data: $resultContainer->debug_data);
            }

            (new P2pReplicationService)->handleIncomingRecords(DataTypes::visitor_resources, incomingJson: json_encode($resultContainer->data), forceSave: true, pfm: false);

            $debug_data = $debug ? $resultContainer->debug_data : [];
        }

        $visitorResources = VisitorResource::where([
            ['site_id', $site_id],
        ]);
        if (isset($queryParameters['visitor_id'])) {
            $visitor_id = $queryParameters['visitor_id'];
            $visitorResources = VisitorResource::where([
                ['visitor_id', $visitor_id],
            ]);
        }
        if ($with_trashed) {
            $visitorResources = $visitorResources->withTrashed();
        }

        $visitorResources = $visitorResources->select(VisitorResource::$publicProperties)->get();

        foreach ($visitorResources as $key => $visitorResource) {
            $visitorResource->mime_type = H::getMimeTypeByExtension(pathinfo($visitorResource->original_file_name, PATHINFO_EXTENSION));
        }

        $debug_data = [
            'fulfiller_id' => $fulfiller_id,
            'query_id' => $query_id,
        ];
        $debug_data = $debug ? $debug_data : [];

        return response()->json([
            'success' => true,
            'data' => $visitorResources ?? '[]',
            'query_id' => $query_id,
            'debug_data' => $debug_data,
        ]);
    }

    /**
     * Visitor File Upload (API Token)
     *
     * See regular Endpoint for details
     *
     * @authenticated
     *
     * @group Api Token Endpoints
     *
     * @header Accept application/json
     * @header Content-Type undefined
     *
     * @bodyParam api_token string required Example: your_visitor_api_token
     * @bodyParam data string required Example: formData
     *
     * @response 200 {"success":true,"data": {"id":"new_id"},...
     */
    public function uploadVisitorFileApiToken(Request $request)
    {
        return $this->uploadVisitorFile($request);
    }

    /**
     * Visitor File Upload
     *
     * Upload a file as form data
     *
     * @authenticated
     *
     * @group Records and Resources
     *
     * @header Accept application/json
     * @header Content-Type undefined
     *
     * @bodyParam data string required Example: formData
     *
     * @response 200 {
     *       "success":true,"data": {"id":"new_id"},...
     */
    public function uploadVisitorFile(Request $request)
    {
        info('uploadVisitorFile start');

        $request = request();

        $site_id = $request->site_id;
        H::siteEndpointsCommonResolve($request, $site_id, $domain);

        if ($invalidResult = (new UtilsController)->validatedReturnSiteId($request, $site_id)) {
            return $invalidResult;
        }

        $visitor = Auth::guard('visitor')->user() ?? Auth::guard('visitor_api')->user();
        $authenticated_visitor_id = $visitor->visitor_id;
        $authenticated_visitor_verification_key_base64 = $visitor->verification_key_base64;

        $signer = $authenticated_visitor_id;
        $signer_verification_key_base64 = $authenticated_visitor_verification_key_base64;

        $site = Site::whereSiteId($site_id)->with('most_recent_site_definition')->first();

        $siteConfig = (new SiteConfigService)->getSiteConfig($site);

        $canUploadRC = (new BackendController($request))->hasVisitorRightsToUpload(
            $signer, $siteConfig);
            

        if (! $canUploadRC->operation_successful) {
            return $this->return_failure($canUploadRC->error_message);
        }

        if (! $canUploadRC->can_upload_files) {
            return $this->return_failure($canUploadRC->upload_files_reason);
        }

        $uploadedFile = $request->file('uploaded_file');

        if (! $uploadedFile) {
            return $this->return_failure('Error: No uploaded file');
        }

        $tmpPath = $uploadedFile->getPathname();
        if ($tmpPath == '') {
            return $this->return_failure('Error: Temp pathname empty');
        }

        $file_size = $uploadedFile->getSize();

        $upload_max_filesize_ini_bytes = H::getBytesFromHumanSizeString(ini_get('upload_max_filesize'));
        $upload_max_filesize_bytes = collect([$upload_max_filesize_ini_bytes, Consts::sitesUploadMaxSizeBytes])->min();

        $upload_max_filesize_bytes = $upload_max_filesize_bytes / 1024;

        try {
            $request->validate([
                'uploaded_file' => ['max:'.$upload_max_filesize_bytes],
            ]);
        } catch (\Throwable $th) {
            return $this->return_failure($th->getMessage());
        }
        $chunks = [];

        $chunks = (new ChunkService)->chunkify(tmpPath: $tmpPath);

        $original_file_name = $uploadedFile->getClientOriginalName();

        $sha256 = hash_file('sha256', $tmpPath);

        $cachedResource = CachedResource::where([
            ['sha256', $sha256],
        ])->first();

        if (! $cachedResource) {

            $file_name = Str::random(40);
            Storage::putFileAs('cached_resources', $uploadedFile, $file_name);

            if ($file_size < Consts::maxFileSizeIpfsPublish) {
                $ipfsHash = IPFSService::publishToIpfs(
                    fileSize: Storage::disk('cached_resources')->size($file_name),
                    contents: Storage::disk('cached_resources')->get($file_name)
                );
            }

            $cachedResource = new CachedResource;
            $cachedResource->sha256 = $sha256;
            $cachedResource->ipfs_hash = $ipfsHash ?? null;
            $cachedResource->file_name = $file_name;
            $cachedResource->file_size = $file_size;

            $cachedResource->save();

        } else {
            $file_size = $cachedResource->file_size;
        }

        $newRecordTS = now();
        $nowTimeString = $newRecordTS->toDateTimeString();
        $nowMicroTimeString = $newRecordTS->format(Consts::visitorDataDateIdFormat);

        $visitorResource = new VisitorResource;

        $visitor_id = $visitor->visitor_id;
        $entity_id = $newRecordTS->format(Consts::visitorDataDateIdFormat).'_'.$visitor_id;

        $visitorResource->sha256 = $sha256;

        $visitorResource->file_size = $file_size;
        $visitorResource->chunks_json = json_encode($chunks);
        $visitorResource->original_file_name = $original_file_name;

        $resourceAsArrayForJson = [];

        $resourceAsArrayForJson[SysProps::_sn_site_id] = $site_id;
        $resourceAsArrayForJson[SysProps::_sn_visitor_id] = $visitor_id;
        $resourceAsArrayForJson[SysProps::_sn_signer] = $signer;

        $resourceAsArrayForJson[SysProps::_sn_entity_id] = $entity_id;

        $resourceAsArrayForJson[SysProps::_sn_entity_created] = $nowMicroTimeString;
        $resourceAsArrayForJson[SysProps::_sn_entity_updated] = $nowMicroTimeString;
        $resourceAsArrayForJson[SysProps::_sn_entity_deleted] = null;
        $resourceAsArrayForJson['chunks_json'] = $visitorResource->chunks_json;
        $resourceAsArrayForJson['file_size'] = $file_size;
        $resourceAsArrayForJson['original_file_name'] = $original_file_name;
        $resourceAsArrayForJson['sha256'] = $sha256;

        $visitorResource->entity_created = $nowMicroTimeString;
        $visitorResource->entity_updated = $nowMicroTimeString;
        $visitorResource->entity_deleted = null;

        $visitorResource->created_at = $nowTimeString;
        $visitorResource->updated_at = $nowTimeString;

        $visitorResource->deleted_at = null;

        $visitorResource->entity_id = $entity_id;
        $visitorResource->visitor_id = $visitor_id;
        $visitorResource->signer = $visitor_id;
        $visitorResource->site_id = $site_id;

        $record_json = json_encode($resourceAsArrayForJson);

        $visitorResource->signer_verification_key_base64 = $signer_verification_key_base64;
        $visitorResource->record_json = $record_json;
        $visitorResource->signature = (new PQCryptoService)->generateSignature($visitor, $record_json);
        $visitorResource->validated_at = now();
        $visitorResource->save();

        $visitorResource->load('grant_record');

        H::enqueueP2pMessage(
            MessageTypes::visitor_resource,
            payload: $visitorResource->only('record_json', 'signature', 'signer_verification_key_base64'),
            site_id: $site_id,
            priority: 30,
        );

        $closure = function () use ($chunks, $site_id, $entity_id) {
            self::dispatchChunksUpload($chunks, $site_id, $entity_id);
        };

        H::dispatchInternalAsyncClosureWrapper($closure);

        $message = $visitorResource->record_json;

        $type = SSETypes::created_visitor_resource;

        SiteSSEFacade::notify(message: $message, type: $type, event: 'message', site_id: $site_id);

        return $this->return_success(
            data: [SysProps::_sn_entity_id => $entity_id],
            metadata: ['ipfsHash' => $ipfsHash ?? null, 'sha256' => $sha256],
            debug_data: []
        );
    }

    public static function dispatchChunksUpload(array $chunks, string $site_id, string $entity_id): void
    {
        $hostingPeers = [];

        $hostingPeers = (new PeerMixService)->getPeerMix(site_id: $site_id);

        foreach ($hostingPeers as $key => $peer) {

            foreach ($chunks as $key => $chunk) {

                $pendingAction = PendingAction::where([
                    ['action_type', ActionTypes::upload_visitor_resource],
                    ['sha256', $chunk['sha256']],
                    ['client_address', $peer->client_address],
                ])->first();
                if (! $pendingAction) {
                    $pendingAction = new PendingAction;
                    $pendingAction->action_type = ActionTypes::upload_visitor_resource;
                    $pendingAction->sha256 = $chunk['sha256'];
                    $pendingAction->file_size = $chunk['file_size'];
                    $pendingAction->client_address = $peer->client_address;
                    $pendingAction->site_id = $site_id;
                    $pendingAction->entity_id = $entity_id;
                    $pendingAction->save();
                }
            }
        }

        $tag = SchedulesTag::Process_Pending_Actions;
        $requestConfigSet = [];
        $requestConfigSet['tag'] = $tag;

        H::dispatchInternalAsync('execute_client_action_wrapper', $requestConfigSet);
    }

    /**
     * Visitor File Delete
     *
     * @authenticated
     *
     * @header Accept application/json
     *
     * @bodyParam _sn_entity_id {string} Example: "2026..."
     *
     * @response 200 {
     *    "success": true,
     *    "metadata": "2026..."
     * }
     *
     * @group Records and Resources
     */
    public function deleteVisitorFile(Request $request)
    {
        $request->validate([
            SysProps::_sn_entity_id => 'required|max:'.Consts::recordJsonMaxSizeBytes,
        ]);

        $site_id = $request->site_id;
        H::siteEndpointsCommonResolve($request, $site_id, $domain);

        if ($invalidResult = (new UtilsController)->validatedReturnSiteId($request, $site_id)) {
            return $invalidResult;
        }

        $entity_id = $request->{SysProps::_sn_entity_id};

        if (! Str::contains($entity_id, '_')) {
            return $this->return_failure('Invalid file ID');
        }

        $incomingRecord = new stdClass;
        $visitor = Auth::guard('visitor')->user() ?? Auth::guard('visitor_api')->user();
        $authenticated_visitor_id = $visitor->visitor_id;
        $authenticated_visitor_verification_key_base64 = $visitor->verification_key_base64;

        $signer_verification_key_base64 = $authenticated_visitor_verification_key_base64;
        $incomingRecord->{SysProps::_sn_signer} = $authenticated_visitor_id;
        $derived_visitor_id = explode('_', $entity_id)[1];
        $entity_created = explode('_', $entity_id)[0];
        $incomingRecord->{SysProps::_sn_visitor_id} = $derived_visitor_id;
        $incomingRecord->{SysProps::_sn_entity_id} = $entity_id;

        $site = Site::whereSiteId($site_id)->with('most_recent_site_definition')->first();
        $siteConfig = (new SiteConfigService)->getSiteConfig($site);

        $signerRightsRC = PermissionService::hasSignerRightsToVisitorResource($incomingRecord, $siteConfig);

        if (! $signerRightsRC->operation_successful) {
            return $this->return_failure('Failed to signer rights. '.$signerRightsRC->error_message);
        }

        if (! $signerRightsRC->has_signer_rights) {
            return $this->return_failure('No rights to this visitor resource');
        }

        $visitorResource = VisitorResource::where([
            ['entity_id', $entity_id],
            ['site_id', $site_id],
        ])->withTrashed()->first();

        $nowString = now()->toDateTimeString();

        if (! $visitorResource) {
            $visitorResource = new VisitorResource;
            $visitorResource->updated_at = $nowString;
        }

        // $visitor = Auth::guard('visitor')->user();
        $visitor_id = $visitor->visitor_id;

        $visitorResource->entity_id = $entity_id;

        $visitorResource->site_id = $site_id;
        $visitorResource->file_size = 0;
        $visitorResource->original_file_name = null;
        $visitorResource->mime_type = null;

        $visitorResource->visitor_id = $derived_visitor_id;
        $visitorResource->signer = $incomingRecord->{SysProps::_sn_signer};

        $newRecordTS = now();
        $nowMicroTimeString = $newRecordTS->format(Consts::visitorDataDateIdFormat);

        $visitorResource->entity_created = $entity_created;
        $visitorResource->entity_updated = $nowMicroTimeString;
        $visitorResource->entity_deleted = $nowMicroTimeString;

        $visitorResource->updated_at = $nowString;
        $visitorResource->deleted_at = $nowString;

        $newRecordTS = now();
        $nowMicroTimeString = $newRecordTS->format(Consts::visitorDataDateIdFormat);

        $recordAsArrayForJson = [];

        $recordAsArrayForJson[SysProps::_sn_entity_id] = $entity_id;
        $recordAsArrayForJson[SysProps::_sn_site_id] = $site_id;
        $recordAsArrayForJson[SysProps::_sn_signer] = $visitor_id;

        $recordAsArrayForJson[SysProps::_sn_visitor_id] = $derived_visitor_id;

        $recordAsArrayForJson[SysProps::_sn_entity_created] = $entity_created;
        $recordAsArrayForJson[SysProps::_sn_entity_updated] = $nowMicroTimeString;
        $recordAsArrayForJson[SysProps::_sn_entity_deleted] = $nowMicroTimeString;

        $record_json = json_encode($recordAsArrayForJson);

        $visitorResource->signer_verification_key_base64 = $signer_verification_key_base64;
        $visitorResource->record_json = $record_json;
        $visitorResource->signature = (new PQCryptoService)->generateSignature($visitor, $record_json);
        $visitorResource->validated_at = now();
        $visitorResource->save();

        $visitorResource->load('grant_record');

        H::enqueueP2pMessage(
            MessageTypes::visitor_resource,
            payload: $visitorResource->only('record_json', 'signature', 'signer_verification_key_base64'),
            site_id: $site_id,
            priority: 40,
        );

        $message = $visitorResource->record_json;

        $type = SSETypes::deleted_visitor_resource;
        
        SiteSSEFacade::notify(message: $message, type: $type, event: 'message', site_id: $site_id);

        return $this->return_success(null, 'Deleted: '.$entity_id);
    }

    /**
     * Serve Visitor File
     *
     * Get contents of Visitor Resource (File) by specifing id of previously uploaded file
     *
     * @group Records and Resources
     *
     * @urlParam visitor_entity_id string Id of a visitor resource. Example: 20261216121630346240_hwy4...
     *
     * @response 200
     */
    public function getVisitorsFileContent(Request $request)
    {
        $request->merge([
            'visitor_entity_id' => $request->route('visitor_entity_id'),
        ]);

        try {
            $request->validate([
                'visitor_entity_id' => ['required', 'string', 'max:1024'],
            ]);
        } catch (\Throwable $th) {
            $message = $th->getMessage();
            $status_code = 400;

            return response(view('conditionalXXX', compact('message', 'status_code')), status: $status_code);
        }

        $site_id = $request->site_id;

        H::siteEndpointsCommonResolve($request, $site_id, $domain);

        if ($invalidResult = (new UtilsController)->validatedReturnSiteId($request, $site_id)) {
            return $invalidResult;
        }

        $visitor_entity_id = $request->visitor_entity_id;

        $entity_id_exploded = explode('_', $visitor_entity_id);
        $ts = $entity_id_exploded[0];
        $visitor_id = $entity_id_exploded[1];

        $entity_id = $ts.'_'.$visitor_id;

        $site_id = $request->site_id;

        $request = new Request;

        $site = Site::whereSiteId($site_id)->first();

        if (! $site) {
            $allow_add_site_to_client = H::getSettVal(SettingIds::allow_add_site_to_client);
            if (! $allow_add_site_to_client) {
                $message = 'Adding new sites is not allowed';
                $status_code = 503;

                return response(view('conditionalXXX', ['message' => $message, 'status_code' => $status_code,
                    'site_id' => $site_id]), status: $status_code);
            }

            $site = new Site;
            $site->site_id = $site_id;
            $site->is_published = true;
            $site->save();
        }

        $siteDefinition = SiteDefinition::whereSiteId($site_id)->orderByDesc('entity_created')->first();
        if (! $siteDefinition) {
            /* Site Definition needed for file listing */
            $rcResult = (new SiteServerController)->handleMissingState(
                request: $request,
                site_id: $site_id,
                action_type: ActionTypes::retrieve_site_definition,
                sha256: null,
                mime_type: null,
                ipfs_hash: null,
                entity_id: null
            );
            if (! $rcResult->operation_successful) {
                $message = 'Couldn\'t retrieve site definition. '.$rcResult->error_message;
                $status_code = 404;

                return response(view('conditionalXXX', compact('message', 'status_code')), status: $status_code);
            }
        }

        $site = Site::whereSiteId($site_id)->with('most_recent_site_definition')->first();

        $siteConfig = (new SiteConfigService)->getSiteConfig($site);

        $visitorResource = VisitorResource::where([
            ['entity_id', $entity_id],
            ['site_id', $site_id],
        ])->withTrashed()->first();
        if (! $visitorResource) {

            $rcResult = (new SiteServerController)->handleMissingState(
                request: $request,
                site_id: $site_id,
                action_type: ActionTypes::retrieve_visitor_resource_defintion,
                sha256: null,
                mime_type: null,
                ipfs_hash: null,
                entity_id: $entity_id
            );

            if (! $rcResult->operation_successful) {
                $message = 'Visitor Resource not known. '.$rcResult->error_message;
                $status_code = 404;

                return response(view('conditionalXXX', compact('message', 'status_code')), status: $status_code);
            } else {
                $visitorResource = VisitorResource::where([
                    ['entity_id', $entity_id],
                    ['site_id', $site_id],
                ])->first();
            }

        }
        if ($visitorResource->entity_deleted) {
            $message = 'Visitor Resource is deleted.';
            $status_code = 410;

            return response(view('conditionalXXX', compact('message', 'status_code')), status: $status_code);
        }

        $cr = CachedResource::whereSha256($visitorResource->sha256)->first();

        if (! $cr) {
            $rc = (new ChunkService)->retrieveAndJoinMissingChunks(
                $visitorResource->sha256, $visitorResource->chunks_json,
                $visitorResource->file_size, $site_id, $request
            );

            if (! $rc->operation_successful) {
                $message = 'Cuold not retrieve chunk. '.$rc->error_message;
                $status_code = 404;

                return response(view('conditionalXXX', compact('message', 'status_code')), status: $status_code);
            }
        }
        $cr = CachedResource::whereSha256($visitorResource->sha256)->first();

        $mime_type = H::getMimeTypeByExtension(pathinfo($visitorResource->original_file_name, PATHINFO_EXTENSION));

        return (new SiteServerController($request))->serveResourceFile(
            $request,
            $cr->file_name,
            $mime_type,
            $site_id,
            $domain,
            $cr->sha256,
            $siteConfig
        );
    }

    /**
     * Client Config
     *
     * @header Accept application/json
     *
     * @response 200 {
     *    "success": true,
     *    "data": [
     *       {
     *           "post_max_size": "8 MiB",...
     *       }
     *     ]
     * }
     *
     * @group Helper APIs
     */
    public function getClientConfig()
    {
        $post_max_size_ini_bytes = H::getBytesFromHumanSizeString(ini_get('post_max_size'));
        $post_max_size_bytes = min($post_max_size_ini_bytes, Consts::sitesPostMaxSizeBytes);
        $upload_max_filesize_ini_bytes = H::getBytesFromHumanSizeString(ini_get('upload_max_filesize'));
        $upload_max_filesize_bytes = min($upload_max_filesize_ini_bytes, Consts::sitesUploadMaxSizeBytes);

        $ini = [
            'post_max_size' => H::bytesCountToReadable($post_max_size_bytes),
            'post_max_size_bytes' => $post_max_size_bytes,
            'upload_max_filesize' => H::bytesCountToReadable($upload_max_filesize_bytes),
            'upload_max_filesize_bytes' => $upload_max_filesize_bytes,
        ];

        return $this->return_success($ini);
    }

    /**
     * Mime Type Mapping
     *
     * @header Accept application/json
     *
     * @response 200 {"success":true,"data":{"1km":"application\/vnd.1000minds.decision-model+xml",...
     *
     * @group Helper APIs
     */
    public function getMimeTypesMapping()
    {
        return $this->return_success(MimeType::MIME_TYPES);
    }
}
