<?php

namespace App\Http\Controllers;

use App\Dicts\ActionTypes;
use App\Dicts\MessageTypes;
use App\Dicts\RecordStates;
use App\Dicts\SettingIds;
use App\Dicts\SSETypes;
use App\Facades\SiteSSEFacade;
use App\Http\Consts;
use App\Http\H;
use App\Models\CachedResource;
use App\Models\Peer;
use App\Models\PendingAction;
use App\Models\Site;
use App\Models\SiteDefinition;
use App\Models\VisitorResource;
use App\Services\ChunkService;
use App\Services\IPFSService;
use App\Services\PeerMixService;
use App\Services\PQCryptoService;
use App\Services\SiteConfigService;
use App\Services\SiteDatabaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PublishSitesController extends Controller
{
    public function toggleDevSiteSymlink(Request $request)
    {
        $request->validate([
            'create' => ['nullable', 'boolean'],
            'required_phrase' => ['required', Rule::in([Consts::symlinkRequiredPhrase])],
        ]);

        $create = $request->create;
        if ($create) {
            Artisan::call('storage:link');
        } else {
            $linkfile = public_path('developed_sites');
            if (file_exists($linkfile)) {
                try {
                    rmdir($linkfile);
                } catch (\Throwable $th) {
                }

                try {
                    unlink($linkfile);
                } catch (\Throwable $th) {
                }
            }
        }

        return redirect(domainRoute('control_panel'))->with('success', 'Modified')
            ->withFragment('modify_symlink');
    }

    public function isSymlinkExists()
    {
        $linkfile = public_path('developed_sites');

        return file_exists($linkfile);
    }

    public function developedSites(Request $request)
    {
        $inboundConnectablePeerCount = Peer::query()->whereNotNull('last_inbound_connected_at')->count();
        $client_not_connectable = $inboundConnectablePeerCount == 0;

        $autoCreateSymlink = H::getSettVal(SettingIds::auto_create_developed_sites_symlink);
        if ($autoCreateSymlink) {
            $this->toggleDevSiteSymlink($request->merge(['create' => true,
                'required_phrase' => Consts::symlinkRequiredPhrase]));
        }

        $symlinkExists = (new PublishSitesController)->isSymlinkExists();

        $developed_sites_collection = collect();
        $developed_sites_secrets = json_decode(Storage::get('developed_sites_secrets.json')) ?? null;

        // dd($developed_sites_secrets);

        if (! File::exists(storage_path('app/developed_sites'))) {
            File::makeDirectory(storage_path('app/developed_sites'));
        }

        $developed_site_dirs = array_diff(scandir(storage_path('app/developed_sites')), ['.', '..']);
        foreach ($developed_site_dirs as $developed_site_dir) {
            $obj = (object) [];
            $obj->developed_site_dir = $developed_site_dir;
            $site_disk = Storage::build([
                'driver' => 'local',
                'root' => storage_path('app/developed_sites/'.$developed_site_dir),
            ]);
            $allFiles = $site_disk->allFiles('');
            $obj->files_count = count($allFiles);
            $site = Site::where('developed_site_dir', $developed_site_dir)->first();

            /* Site for developed site directory exists */
            if ($site) {
                $site_id = $site->site_id;
                $obj->site_id = $site_id;
                $obj->site_config_json = $site->site_config_json_draft;
                $site_config_array = json_decode($obj->site_config_json, true);
                $site_config = (new SiteConfigService)->getUpdatedSiteConfig($site_config_array);
                $obj->site_config_json = json_encode($site_config, JSON_PRETTY_PRINT);
                $obj->title = $site->title_draft;
                $obj->description = $site->description_draft;
                $earliest_site_definition = SiteDefinition::whereSiteId($site_id)
                    ->orderByDesc('entity_created')->first();
                $obj->earliest_site_definition = $earliest_site_definition;

            } else {
                /* No Site for developed site directory */

                $developed_site_secret = $developed_sites_secrets?->{$developed_site_dir} ?? null;

                if ($developed_site_secret) {

                    /* Private seed found in secrets file */
                    $keySet = (new PQCryptoService)->getKeySetFromSeed($developed_site_secret->base64_seed);
                } else {
                    $keySet = (new PQCryptoService)->genKeySet();
                }
                $seedBytes = $keySet->seed;

                $site_id = $keySet->hashed_id;

                $siteBySiteId = Site::whereSiteId($site_id)->first();
                if ($siteBySiteId) {
                    $site = $siteBySiteId;
                } else {
                    $site = new Site;
                }

                $site->site_id = $site_id;

                $site->encrypted_seed = encrypt($seedBytes);

                $site->title_draft = $developed_site_dir;
                $site->developed_site_dir = $developed_site_dir;

                $fileSiteConfigArray = null;
                if (in_array('site_config_export.json', $allFiles)) {
                    $fileSiteConfigArray = json_decode($site_disk->get('site_config_export.json'), true);
                }

                if ($fileSiteConfigArray) {
                    $defaultSiteConfig = (new SiteConfigService)->getUpdatedSiteConfig($fileSiteConfigArray);
                } else {
                    $defaultSiteConfig = (new SiteConfigService)->getDefaultSiteConfig();
                }

                $defaultSiteConfigJson = json_encode($defaultSiteConfig, JSON_PRETTY_PRINT);
                $site->site_config_json_draft = $defaultSiteConfigJson;

                $site->is_published = false;
                $site->save();
                $obj->earliest_site_definition = null;
                $obj->site_id = $site_id;
                $obj->title = $developed_site_dir;

                $obj->site_config_json = $defaultSiteConfigJson;
            }

            $siteTotalSizeBytes = 0;

            $obj->reservedDirPresent = false;
            $reservedPaths = self::getReserveredPaths();
            foreach ($allFiles as $file) {

                foreach ($reservedPaths as $key => $reservedPath) {
                    if (Str::startsWith($file, $reservedPath)) {
                        $obj->reservedDirPresent = true;
                    }
                }
                $fileSize = $site_disk->size($file);

                $siteTotalSizeBytes += $fileSize;
            }
            $obj->siteTotalSizeBytes = $siteTotalSizeBytes;
            $obj->siteTotalSizeReadable = H::bytesCountToReadable($siteTotalSizeBytes);

            $obj->canBePublished = true;

            if (
                $obj->siteTotalSizeBytes > Consts::siteFilesMaxTotalSizeBytesServeronet ||
                $obj->reservedDirPresent
            ) {
                $obj->canBePublished = false;
            }

            $developed_sites_collection->push($obj);
        }

        // dd($developed_sites_collection);

        return view('developed_sites', compact('developed_sites_collection', 'symlinkExists', 'client_not_connectable'));
    }

    public function publishSite(Request $request)
    {
        return $this->publish_site($request, false);
    }

    public function publish_site(Request $request)
    {
        H::echoHeaderWithViewport(pfm: true);

        $description = $request->input('description');
        $developed_site_dir = $request->developed_site_dir;
        $site = Site::where('developed_site_dir', $developed_site_dir)->first();
        $siteConfig = $request->input('site_config_json') ?? $site->site_config_json_draft;

        $title = $request->input('title') ?? $site->title_draft;
        $add_self_as_trusted_site_peer = $request->input('add_self_as_trusted_site_peer') ?? false;

        $target_site_id = $request->site_id;

        $request->merge(['site_config_json' => $siteConfig]);

        try {
            $request->validate([
                'title' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:1024'],
                'site_config_json' => ['required', 'json', 'max:'.Consts::recordJsonMaxSizeBytes],
                'add_self_as_trusted_site_peer' => ['nullable', 'boolean'],
                'site_id' => Consts::siteIdValidationRule,
                'developed_site_dir' => [
                    'required',
                    'string',
                    'max:1024',
                    'regex:/^[A-Za-z0-9._ -]+$/',
                ],
            ]);
        } catch (\Throwable $th) {
            return redirect(domainRoute('developed_sites'))->withFragment('#'.$target_site_id)
                ->with('error', $th->getMessage().' ('.$target_site_id.')')
                ->with('site_id', $target_site_id);
        }

        $this->exportSiteConfigJson($request, withRedirect: false);

        H::pfm('Publishing Site. Please wait...');

        $site_id = $site->site_id;

        $siteConfig = (new SiteConfigService)->getUpdatedSiteConfig(json_decode($siteConfig, true));

        $siteConfig->_sn_site_id = $site_id;

        if ($add_self_as_trusted_site_peer && ! in_array(H::getPublicSelfAddress(), $siteConfig->trusted_Site_Peers)) {
            array_push($siteConfig->trusted_Site_Peers, H::getPublicSelfAddress());
        }

        if (! $site->encrypted_seed) {
            return redirect(domainRoute('developed_sites'))->withFragment('#'.$site_id)
                ->with('error', 'ERROR. Missing Private seed ('.$site_id.')')
                ->with('site_id', $site_id);
        }

        $site_disk = Storage::build([
            'driver' => 'local',
            'root' => storage_path('app/developed_sites/'.$developed_site_dir),
        ]);
        $files = $site_disk->allFiles('');

        if (count($files) == 0) {
            return redirect(domainRoute('developed_sites'))->withFragment('#'.$site_id)
                ->with('error', 'ERROR. Site has no files: '.$title.' ('.$site_id.')')
                ->with('site_id', $site_id);
        }

        $file_listing = collect();
        $i = 0;
        $count = count($files);

        $nowMicroTimeString = now()->format(Consts::visitorDataDateIdFormat);
        $siteTotalSizeBytes = 0;

        $modifiedOrNewSiteResources = [];
        foreach ($files as $file) {
            $i++;
            $fileSize = $site_disk->size($file);
            $siteTotalSizeBytes += $fileSize;
            $sha256 = hash_file('sha256', $site_disk->path($file));
            $siteResource = CachedResource::where('sha256', $sha256)
                ->select(CachedResource::$publicProperties)->first();
            $storingWasRequired = false;
            if (! $siteResource) {
                $file_name = Str::random(40);

                $stream = $site_disk->readStream($file);

                Storage::disk('cached_resources')->writeStream(
                    $file_name,
                    $stream
                );

                fclose($stream);

                $storingWasRequired = true;

                $siteResource = new CachedResource;

                if ($fileSize < Consts::maxFileSizeIpfsPublish) {
                    $ipfsHash = IPFSService::publishToIpfs($fileSize, $contents = $site_disk->get($file));
                }

                $siteResource->file_name = $file_name;
                $siteResource->file_size = $fileSize;
                $siteResource->sha256 = $sha256;
                $siteResource->ipfs_hash = $ipfsHash ?? null;
                $siteResource->save();
            }

            $siteResource->mime_type = H::getMimeTypeByExtension(pathinfo($file, PATHINFO_EXTENSION));

            $chunks = (new ChunkService)->chunkify($site_disk->path($file));

            if ($storingWasRequired) {
                array_push($modifiedOrNewSiteResources, $sha256);
                foreach ($chunks as $key => $chunk) {
                    array_push($modifiedOrNewSiteResources, $chunk['sha256']);
                }
            }

            $siteResource->res_id = $file;
            $siteResource->chunks_json = json_encode($chunks);

            H::pfm($i.'/'.$count.' File: '.$file.' | Chunks: '.count($chunks).' | Storing required: '.tfyn($storingWasRequired).' | Sha256: '.$sha256);

            unset($siteResource->file_name);

            $file_listing->push($siteResource);
        }

        $siteDefinition = new SiteDefinition;
        $siteDefinition->site_id = $site_id;
        $keySet = (new PQCryptoService)->getKeySetFromSeed(base64_encode(decrypt($site->encrypted_seed)));
        $verification_key_base64 = $keySet->verification_key_base64;
        $siteDefinition->signer_verification_key_base64 = $verification_key_base64;
        $file_listing_json = json_encode($file_listing);

        if (strlen($file_listing_json) > Consts::recordJsonMaxSizeBytes) {
            return $this->scrollToSiteWithError($site_id, 'File Listing is too big. Max: '
            .Consts::recordJsonMaxSizeBytes.' bytes. Delete some files.');
        }

        $siteDefinition->file_listing_json = $file_listing_json;
        $siteDefinition->entity_created = $nowMicroTimeString;
        $siteDefinition->download_state = RecordStates::download_downloaded;
        $siteDefinition->files_count = $count;
        $siteDefinition->files_size = $siteTotalSizeBytes;
        $siteDefinition->is_pending_distribution = true;
        $siteDefinition->site_config_json = json_encode($siteConfig, JSON_PRETTY_PRINT);
        $siteDefinition->title = $title;
        $siteDefinition->description = $description;
        $siteDefinition->created_at = now()->toDateTimeString();
        $siteDefinition->updated_at = now()->toDateTimeString();

        $record_json = json_encode($siteDefinition->only(SiteDefinition::$publicProperties));

        if (strlen($record_json) > Consts::recordJsonMaxSizeBytes) {
            return $this->scrollToSiteWithError($site_id, 'Site Definition json is too big. Max: '
            .Consts::recordJsonMaxSizeBytes.' bytes. Delete some files or shorten paths.');
        }

        $siteDefinition->record_json = $record_json;
        $siteDefinition->signature = (new PQCryptoService)->generateSignature($site, $record_json);
        $siteDefinition->save();

        H::enqueueP2pMessage(
            MessageTypes::site_definition,
            payload: $siteDefinition->only('record_json', 'signature', 'signer_verification_key_base64'),
            site_id: $site_id,
            priority: 10,
        );

        $site->is_to_be_hosted = true;
        $site->is_hosted = true;
        $site->is_published = true;
        $site->visible_in_ui = true;
        $site->published_at = now()->toDateTimeString();

        $site->title_draft = $title;
        $site->description_draft = $description;
        $site->site_config_json_draft = json_encode($siteConfig, JSON_PRETTY_PRINT);
        $site->save();

        $message = $siteDefinition->record_json;

        SiteSSEFacade::notify(message: $message, type: SSETypes::created_site_definition,
            event: 'message', site_id: $siteDefinition->site_id);

        $pfm = false;
        if (H::isDevNode()) {
            $pfm = true;
        }

        $closure = function () use ($site_id, $pfm) {
            BackgroundProcessingController::sendPendingMessages($site_id, pfm: $pfm);
            self::processSiteDefinitionsPendingDistribution($site_id, pfm: $pfm);
            BackgroundProcessingController::processPendingActions($site_id, pfm: $pfm);
            TorrentTrackersController::trackerAnnouncingHosting(site_id: $site_id, pfm: $pfm);
        };

        if (H::isDevNode()) {
            $closure();
        } else {
            H::dispatchInternalAsyncClosureWrapper($closure);
        }

        H::pfm('Site publishing continues in background.');

        $redirEnabled = true;
        if ($redirEnabled ?? true) {
            return redirect(domainRoute('developed_sites'))->withFragment('#'.$site_id)
                ->with([
                    'status' => 'Site published: '.$title.' ('.$site_id.')'])
                ->with('site_id', $site_id);
        }
    }

    public function scrollToSiteWithError($site_id, $errorMessage)
    {
        return redirect(domainRoute('developed_sites'))->withFragment('#'.$site_id)
            ->with('error_site_specific', $errorMessage)
            ->with('site_id', $site_id);
    }

    public static function processSiteDefinitionsPendingDistribution($site_id = null, $limit = 10, $pfm = false)
    {
        if ($site_id) {
            if ($invalidResult = (new UtilsController)->validatedReturnSiteId(request(), $site_id)) {
                return $invalidResult;
            }
        }

        $siteDefinitionBuilder = SiteDefinition::where('is_pending_distribution', true)->orderByDesc('entity_created');

        if ($site_id) {
            $siteDefinitionBuilder->whereSiteId($site_id);
        }

        $siteDefinition = $siteDefinitionBuilder->first();
        if (! $siteDefinition) {
            H::pfm('No Site Definition is pending distribution', pfm: $pfm);

            return;
        }

        if (! $site_id) {
            $site_id = $siteDefinition->site_id;
        }

        $flChunksHashes = [];
        H::pfm('SD Pending distribution - created TS: '.$siteDefinition->entity_created.' '.$site_id, pfm: $pfm);
        $siteDefinition->distributed_at = now();
        $siteDefinition->is_pending_distribution = false;
        $siteDefinition->save();

        $flCollection = collect(json_decode($siteDefinition->file_listing_json));

        foreach ($flCollection as $key => $value) {
            $chunks = json_decode($value->chunks_json);
            foreach ($chunks as $key => $chunk) {
                array_push($flChunksHashes, $chunk->sha256);
            }
        }

        $peers = (new PeerMixService)->getPeerMix(site_id: $site_id);

        H::pfm('Peers: ', pfm: $pfm);
        H::pfm($peers->pluck('client_address'), pfm: $pfm);

        foreach ($peers as $key => $peer) {
            info('processSiteDefinitionsPendingDistribution '.$peer->client_address);
            try {
                if (! (new PublishSitesController)->isPeerEagerHostingSite($peer, $site_id, pfm: $pfm = false)) {
                    continue;
                }

                $url = H::a($peer->client_address).'p2p_api/v1/check_hashes_hosting_state';
                H::pfm('check_hashes_hosting_state '.$peer->client_address, pfm: $pfm);

                $justification_record_json = $siteDefinition->record_json;
                $justification_signature = $siteDefinition->signature;
                $justification_signer_verification_key_base64 = $siteDefinition->signer_verification_key_base64;

                $client = H::setupClient(H::isTorAddress($peer->client_address));
                $options = [
                    'timeout' => H::timeoutAdjust(H::isTorAddress($peer->client_address), 10),
                    'form_params' => [
                        'hashes' => $flChunksHashes,
                        'justification_record_json' => $justification_record_json,
                        'justification_signature' => $justification_signature,
                        'justification_signer_verification_key_base64' => $justification_signer_verification_key_base64,
                    ],
                    'prepare_ip' => true,
                ];
                H::prepareOptions($options, $url);

                $response = $client->post($url, $options);

                $body = (string) $response->getBody();
                H::pfm($body, pfm: $pfm);
                $responseMessage = json_decode($body);
                info('$body');
                info($body);

                $success = ($responseMessage)->success;
                info('processSiteDefinitionsPendingDistribution '.$peer->client_address.' $success '.tfyn($success));
                if ($success != true) {
                    continue;
                }

                if (! property_exists($responseMessage, 'data')) {
                    continue;
                }

                $hashesArray = (array) $responseMessage->data;
                info('processSiteDefinitionsPendingDistribution '.$peer->client_address.' $hashesArray '.count($hashesArray));

                H::pfm($peer->client_address.' is Missing: '.count($hashesArray ?? []), pfm: $pfm);

                if (count($hashesArray ?? 0) > 0) {
                    foreach ($hashesArray as $key => $sha256) {
                        $pendingAction = PendingAction::where([
                            ['action_type', ActionTypes::upload_site_resource],
                            ['sha256', $sha256],
                            ['state', RecordStates::action_pending],
                            ['client_address', $peer->client_address],
                        ])->first();
                        if (! $pendingAction) {
                            $pendingAction = new PendingAction;
                            $pendingAction->action_type = ActionTypes::upload_site_resource;
                            $pendingAction->sha256 = $sha256;
                            $pendingAction->site_id = $site_id;
                            $pendingAction->client_address = $peer->client_address;
                            $pendingAction->save();
                        }
                    }
                }

                H::pfm('Url: '.$url.' Result: '.$response->getStatusCode(), pfm: $pfm);
            } catch (\Throwable $th) {
                info($th->getLine().' '.$th->getFile().' '.$th->getMessage());
                H::pfm('send_message_to_peer '.$th->getFile().' '.$th->getLine(), pfm: $pfm);
                H::pfm('send_message_to_peer '.$th->getMessage(), pfm: $pfm);
            }
        }

        $limit--;
        self::processSiteDefinitionsPendingDistribution($site_id, $limit, pfm: $pfm);
    }

    public static function prepareResourcesRepublish($site_id, $limit = 5, $pfm = false)
    {
        if ($invalidResult = (new UtilsController)->validatedReturnSiteId(request(), $site_id)) {
            return $invalidResult;
        }

        $visitorResources = VisitorResource::where([
            ['site_id', $site_id],
        ])->get();

        $visitorResourceChunksHashes = [];
        $siteResourceChunksHashes = [];
        $resourceChunksHashesMap = [];

        foreach ($visitorResources as $key => $visitorResource) {
            $chunks = json_decode($visitorResource->chunks_json);
            foreach ($chunks as $key => $chunk) {
                array_push($visitorResourceChunksHashes, $chunk->sha256);
            }
        }

        $visitorResourceChunksHashes = array_unique($visitorResourceChunksHashes);

        $cachedResources = CachedResource::whereIn('sha256', $visitorResourceChunksHashes)->select('sha256')->get();

        $visitorResourceChunksHashes = $cachedResources->pluck('sha256')->toArray();

        foreach ($visitorResourceChunksHashes as $key => $hash) {
            $resourceChunksHashesMap[$hash] = ActionTypes::upload_visitor_resource;
        }

        $site = Site::whereSiteId($site_id)->with('most_recent_site_definition')->first();
        $flCollection = collect(json_decode($site->most_recent_site_definition->file_listing_json));

        foreach ($flCollection as $key => $resource) {
            $chunks = json_decode($resource->chunks_json);
            foreach ($chunks as $key => $chunk) {
                array_push($siteResourceChunksHashes, $chunk->sha256);
            }
        }

        foreach ($siteResourceChunksHashes as $key => $hash) {
            $resourceChunksHashesMap[$hash] = ActionTypes::upload_site_resource;
        }

        if (empty($resourceChunksHashesMap)) {
            info('$resourceChunksHashesMap is empty');

            return;
        }

        $peers = (new PeerMixService)->getPeerMix(site_id: $site_id);

        H::pfm($peers, pfm: $pfm);

        foreach ($peers as $key => $peer) {
            try {
                if (! (new PublishSitesController)->isPeerEagerHostingSite($peer, $site_id, pfm: $pfm = false)) {
                    continue;
                }

                $url = H::a($peer->client_address).'p2p_api/v1/check_hashes_hosting_state';
                H::pfm('check_hashes_hosting_state '.$peer->client_address, pfm: $pfm);

                $client = H::setupClient(H::isTorAddress($peer->client_address));
                $options = [
                    'timeout' => H::timeoutAdjust(H::isTorAddress($peer->client_address), 10),
                    'form_params' => [
                        'hashes' => array_keys($resourceChunksHashesMap),
                    ],
                    'prepare_ip' => true,
                ];
                H::prepareOptions($options, $url);
                $response = $client->post($url, $options);

                $body = (string) $response->getBody();
                H::pfm($body, pfm: $pfm);
                $responseMessage = json_decode($body);
                $success = ($responseMessage)->success;

                if ($success != true) {
                    continue;
                }

                if (! property_exists($responseMessage, 'data')) {
                    continue;
                }

                $hashesArray = (array) $responseMessage->data;

                H::pfm($peer->client_address.' is Missing: '.count($hashesArray ?? []), pfm: $pfm);

                if (count($hashesArray ?? 0) > 0) {
                    foreach ($hashesArray as $key => $sha256) {

                        $action_type = $resourceChunksHashesMap[$hash];

                        $pendingAction = PendingAction::where([
                            ['action_type', $action_type],
                            ['sha256', $sha256],
                            ['state', RecordStates::action_pending],
                            ['client_address', $peer->client_address],
                        ])->first();

                        if (! $pendingAction) {
                            $pendingAction = new PendingAction;
                            $pendingAction->action_type = $action_type;
                            $pendingAction->sha256 = $sha256;
                            $pendingAction->site_id = $site_id;
                            $pendingAction->client_address = $peer->client_address;
                            $pendingAction->save();
                        }
                    }
                }

                H::pfm('Url: '.$url.' Result: '.$response->getStatusCode(), pfm: $pfm);
            } catch (\Throwable $th) {
                info($th->getMessage().' '.$th->getFile().' '.$th->getLine());
                H::pfm('send_message_to_peer '.$th->getFile().' '.$th->getLine(), pfm: $pfm);
                H::pfm('send_message_to_peer '.$th->getMessage(), pfm: $pfm);
            }
        }
    }

    public function isPeerEagerHostingSite($peer, $site_id, $pfm = false): bool
    {
        H::pfm('check_site_hosting_eagerness '.$peer->client_address, pfm: $pfm);
        $url = H::a($peer->client_address).'p2p_api/v1/check_site_hosting_eagerness';
        $client = H::setupClient(H::isTorAddress($peer->client_address));
        $options = [
            'timeout' => H::timeoutAdjust(H::isTorAddress($peer->client_address), 10),
            'form_params' => ['site_id' => $site_id],
            'prepare_ip' => true,
        ];
        H::prepareOptions($options, $url);
        $response = $client->post($url, $options);
        $body = (string) $response->getBody();
        $responseMessage = json_decode($body);
        $success = ($responseMessage)->success;

        if ($success != true) {
            return false;
        }

        if (! $responseMessage->data->eager == 'true') {
            return false;
        }

        return true;
    }

    public function exportSiteConfigJson(Request $request, $withRedirect = true)
    {
        $request->validate([
            'site_config_json' => ['required', 'json', 'max:'.Consts::recordJsonMaxSizeBytes],
            'developed_site_dir' => [
                'required',
                'string',
                'max:1024',
                'regex:/^[A-Za-z0-9._ -]+$/',
            ],
            'site_id' => Consts::siteIdValidationRule,
        ]);

        $siteConfigJson = $request->input('site_config_json');
        $developed_site_dir = $request->input('developed_site_dir');
        $site_id = $request->input('site_id');

        $site_disk = Storage::build([
            'driver' => 'local',
            'root' => storage_path('app/developed_sites/'.$developed_site_dir),
        ]);

        $site_disk->put('site_config_export.json', $siteConfigJson);

        if ($withRedirect) {
            return redirect(domainRoute('developed_sites'))->withFragment('#'.$site_id)
                ->with('status', 'Site Config exported: '.$developed_site_dir.' ('.$site_id.')')
                ->with('site_id', $site_id);
        }
    }

    public function prepareSitesDatabase(Request $request)
    {
        $request->validate([
            'site_id' => Consts::siteIdValidationRule,
        ]);

        $site_id = $request->input('site_id');

        $siteProd = Site::whereSiteId($site_id)->with('most_recent_site_definition')->first();

        if (! $siteProd->most_recent_site_definition) {
            $message = 'No published Site Definition';

            return redirect(domainRoute('developed_sites'))->withFragment('#'.$site_id)
                ->with('status', $message);
        }

        $siteConfig = json_decode($siteProd->most_recent_site_definition->site_config_json);

        if ($siteConfig->site_Has_Database) {
            (new SiteDatabaseService)->prepareSiteDatabase($site_id);
        } else {
            abort(400, 'Site has no database!');
        }

        $message = 'Site\'s database prepared';

        return redirect(domainRoute('developed_sites'))->withFragment('#'.$site_id)
            ->with('status', $message);
    }

    public static function getReserveredPaths(): array
    {
        return [
            'register',
            'login',
            'logout',
            'visitor_actions',
            'retrieving',
            'site_api',
            'visitor_file',
            'site_assets',
        ];
    }
}
