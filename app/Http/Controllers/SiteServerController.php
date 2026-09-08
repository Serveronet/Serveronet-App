<?php

namespace App\Http\Controllers;

use App\Dicts\ActionTypes;
use App\Dicts\CachePrefixes;
use App\Dicts\DataTypes;
use App\Dicts\ListProviderTypes;
use App\Dicts\MimeType;
use App\Dicts\RecordStates;
use App\Dicts\SettingIds;
use App\Http\Consts;
use App\Http\H;
use App\Models\CachedResource;
use App\Models\ContentRetrieval;
use App\Models\ListProviderEntry;
use App\Models\ResultContainer;
use App\Models\Site;
use App\Models\SiteDefinition;
use App\Models\Tracker;
use App\Models\Visitor;
use App\Services\ChunkService;
use App\Services\IPFSService;
use App\Services\PeerMixService;
use App\Services\SiteConfigService;
use App\Services\TorrentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\JsonResponse;
use Throwable;

class SiteServerController extends Controller
{
    public function serveSiteResource(Request $request)
    {
        // info('serveSiteResource');
        $siteHadToBeCreated = false;
        $site_id = '';

        try {
            $request->validate([
                'states_only' => ['nullable', 'boolean'],
                'showRetrievalPageForIndex' => ['nullable', 'boolean'],
                'site_id' => Consts::notRequiredSiteIdValidationRule,
            ]);
        } catch (Throwable $th) {
            $message = 'Request problems '.$th->getMessage();
            $status_code = 400;

            return response(view('conditionalXXX', compact('message', 'status_code')), status: $status_code);
        }

        $res_id = $request->res_id;

        if (H::isDevNode()) {
            $is_to_be_hosted_override = $request->is_to_be_hosted_override;
            // info('is_to_be_hosted_override: '.$is_to_be_hosted_override);
        }

        if ($request->site_id === Consts::visitorControlPanelAddress)
        return redirect(domainRoute('visitor_control_panel', ['site_id' => Consts::visitorControlPanelAddress]));

        $target = $request->url();
        $statesOnly = $request->states_only;
        $showRetrievalPageForIndex = $request->showRetrievalPageForIndex ?? true;

        H::siteEndpointsCommonResolve($request, $site_id, $domain, $is_domain, $is_resolved);

        $target_site_id = $site_id;

        if (! $request)
        $request = new Request;

        try {
            $request->merge(['target_site_id' => $target_site_id]);
            $request->validate(['target_site_id' => Consts::siteIdWithDomainValidationRule,
            ]);
        } catch (Throwable $th) {
            $message = 'Invalid Site ID: '.$site_id;
            $status_code = 500;

            return response(view('conditionalXXX', compact('message', 'status_code')), status: $status_code);
        }

        if ($is_domain && $is_resolved) {
            return redirect(domainRoute('site', ['site_id' => $target_site_id]));
        }

        if ($is_domain && ! $is_resolved) {
            $message = 'Domain not resolved: '.$site_id;
            $status_code = 503;

            return response(view('conditionalXXX', ['message' => $message, 'status_code' => $status_code,
                'site_id' => $site_id]), status: $status_code);
        }

        $siteInBanned = Cache::remember(CachePrefixes::banned_sites_.$site_id, 60, function () use ($site_id) {
            return ListProviderEntry::where([
                ['content', $site_id],
                ['data_type', ListProviderTypes::banned_sites],
            ])->first();
        });

        if ($siteInBanned) {
            $message = 'This Serveronet Site is banned by Owner of this Client';
            $status_code = 503;

            return response(view('conditionalXXX', ['message' => $message, 'status_code' => $status_code,
                'site_id' => $site_id]), status: $status_code);
        }

        $site = H::getCached(CachePrefixes::site_.$site_id, Site::whereSiteId($site_id));

        if (! $site) {
            $allow_add_site_to_client = H::getSettVal(SettingIds::allow_add_site_to_client);
            if (! $allow_add_site_to_client) {
                $message = 'Adding new sites is not allowed';
                $status_code = 503;

                return response(view('conditionalXXX', ['message' => $message, 'status_code' => $status_code,
                    'site_id' => $site_id]), status: $status_code);
            }

            $site = new Site();
            $site->site_id = $site_id;
            $site->is_published = true;

            if (H::isDevNode()) {
                $site->is_to_be_hosted = $is_to_be_hosted_override ?? true;
            }

            $site->save();

            $siteHadToBeCreated = true;
        }

        /* Do we need to retrieve a Site Definition? */

        $siteDefinition = Cache::remember(CachePrefixes::sd_entity_created_.$site_id, 60, function () use ($site_id) {
            return SiteDefinition::whereSiteId($site_id)->orderByDesc('entity_created')->first();
        });

        if (! $siteDefinition) {

            $trackerSitePeers = (new TorrentService)->getUpdatedTrackerSitePeers($site_id);

            if ($statesOnly) {
                $trackersCount = Tracker::inRandomOrder()->take(5)->count();
                $hostingPeers = (new PeerMixService)->getPeerMix(site_id: $site_id);

                if ($siteHadToBeCreated) {
                    $stateForVisitor = 'Site not known therefore was registered. Site Definition is missing.';
                } else {
                    $stateForVisitor = 'Site known. Site Definition is missing.';
                }

                return $this->return_success(
                    data: [
                        'stateForVisitor' => $stateForVisitor,
                        'site_id' => $site_id,
                        'res_id' => $res_id,
                        'continue' => false,
                    ],
                    debug_data: ' | Peers Trackers: '.$trackerSitePeers->data->count().' | Trackers: '.$trackersCount
                    .' | Peers PeerMix: '.json_encode($hostingPeers->pluck('client_address'))
                    .' | trackerSitePeers: '.json_encode($trackerSitePeers->debug_data)
                    .' | metadata: '.json_encode($trackerSitePeers->metadata),
                );
            }

            /* Try to download site definition */

            if ((! $res_id || Str::endsWith($res_id, Consts::defaultDocuments)) && $showRetrievalPageForIndex) {
                return redirect(domainRoute('retrieving', [
                    'site_id' => $site_id,
                    'target' => $target,
                    'missing' => DataTypes::site_definitions,
                    'res_id' => $res_id], $status = 307, subdomainLevelOverride: 1)); /* Temporary redirect */
            } else {
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
                } else {
                    Cache::forget(CachePrefixes::sd_entity_created_.$site_id);
                }
            }
        }

        $siteDefinition = Cache::remember(CachePrefixes::sd_entity_created_.$site_id, 60, function () use ($site_id) {
            return SiteDefinition::whereSiteId($site_id)->orderByDesc('entity_created')->first();
        });

        if (! $siteDefinition) {
            $message = 'Site definition unavailable';
            $status_code = 404;

            return response(view('conditionalXXX', compact('message', 'status_code')), status: $status_code);
        }

        $siteConfig = Cache::remember(CachePrefixes::site_config_.$site_id, 60, function () use ($siteDefinition) {
            return (new SiteConfigService)->getUpdatedSiteConfig(json_decode($siteDefinition->site_config_json, true));
        });

        $site->requests_counter++;
        $site->last_request_at = now();
        $site->visible_in_ui = true;

        if ($siteHadToBeCreated) {

            $preliminaryTotalSiteSizeMB = (int) ($siteDefinition->files_size ?? 0
            + $siteDefinition->visitor_records_preliminary_total_size ?? 0
            + $siteDefinition->visitor_resources_preliminary_total_size ?? 0)
            / 1024 / 1024;

            info('preliminaryTotalSiteSizeMB: '.$preliminaryTotalSiteSizeMB);

            if (
                $preliminaryTotalSiteSizeMB < H::getSettVal(SettingIds::auto_hosting_site_size_threshold_mb)
                && self::canHostIfSpace()
                && H::getSettVal(SettingIds::auto_hosting_enabled)
            ) {
                $site->is_to_be_hosted = true;
                (new BackgroundProcessingController)->hostedSiteInitHostingActions($site_id);
            } else {
                $site->is_to_be_hosted = false;
            }
            info('New Site is_to_be_hosted: '.$site->is_to_be_hosted);
        }

        if (H::isDevNode()) {
            $site->is_to_be_hosted = $is_to_be_hosted_override ?? $site->is_to_be_hosted;
        }

        $site->save();

        Cache::forget(CachePrefixes::site_.$site_id);
        $site = H::getCached(CachePrefixes::site_.$site_id, Site::whereSiteId($site_id));

        if ($statesOnly) {
            return $this->return_success(
                data: [
                    'stateForVisitor' => 'Site Definition is in possession',
                    'site_id' => $site_id,
                    'res_id' => $res_id,
                    'continue' => true,
                ],
                debug_data: 'N/A',
            );
        }

        if ($siteConfig->show_Adults_Only_Gate) {
            if (! $request->cookie('adults_only_accepted')) {
                return response(view('adults_only_gate', compact('siteDefinition')), 403);
            }
        }

        if ($siteConfig->site_Requires_Authentication) {
            if (! Auth::guard('visitor')->check()) {
                return redirect(domainRoute('login', ['site_id' => $site_id]));
            }
        }

        /* Site required for it's rules */
        $queryBuilder = SiteDefinition::where('site_id', $site_id)->orderByDesc('entity_created');

        if ($site->is_hosted) {
            $queryBuilder->orderBy('download_state');
        }

        /* Do we need to retrieve a Site Resource? */
        $siteDefinition = $queryBuilder->orderByDesc('entity_created')->first();
        $fileListingJson = $siteDefinition->file_listing_json;
        $site_resources = json_decode($fileListingJson);

        if (! $res_id) {
            if (! Str::endsWith($request->getPathInfo(), '/')) {
                if ($domain) {
                    $site_id = $domain;
                }

                if ($site_id) {
                    return redirect(H::siteUrl($site_id));
                }
            }

            /* Fallback for slash ending addresses - index will be served instead */
            $flIndexFile = collect($site_resources)->whereIn('res_id', Consts::defaultDocuments)->first();

            if ($flIndexFile) {
                $res_id = $flIndexFile->res_id;
            } else {
                $message = 'Site doesn\'t have index file. Giving up.';
                $status_code = 404;

                return response(view('conditionalXXX', compact('message', 'status_code')), status: $status_code);
            }
        }

        $pattern = '/^([a-zA-Z0-9-_|\/]+)+$/'; // Path with dot
        if ($siteConfig->single_Page_Application && preg_match($pattern, $res_id)) {
            $flFile = collect($site_resources)->where('res_id', 'index.html')->first();
        } else {
            $flFile = collect($site_resources)->where('res_id', $res_id)->first();
        }
        if (! $flFile) {
            $message = 'No such a file in the File Listing';
            $status_code = 404;

            return response(view('conditionalXXX', compact('message', 'status_code')), status: $status_code);
        }

        /* Check if cached */
        $sha256 = $flFile->sha256;

        $cachedResource = H::getCached(CachePrefixes::cr_.$sha256, CachedResource::whereSha256($sha256));

        if ($cachedResource) {
            $cachedResource->last_request_at = now();
            $cachedResource->requests_counter++;
            $cachedResource->save();

            return (new SiteServerController($request))->serveResourceFile(
                $request, $cachedResource->file_name,
                $flFile->mime_type ?? 'text/html',
                $site_id,
                $domain,
                $cachedResource->sha256,
                $siteConfig,
            );
        }

        if ($flFile->ipfs_hash) {
            $retrieved = IPFSService::retrieveFromIpfs($flFile->file_size, $flFile->ipfs_hash, $flFile->sha256);

            if ($retrieved) {
                info('retrieved FromIpfs - ipfs_hash: '.$flFile->ipfs_hash.' | sha256: '.$flFile->sha256);
                $cachedResource = CachedResource::whereSha256($flFile->sha256)->first();

                return (new SiteServerController($request))->serveResourceFile(
                    $request,
                    $cachedResource->file_name,
                    $flFile->mime_type ?? 'text/html',
                    $site_id,
                    $domain,
                    $cachedResource->sha256,
                    $siteConfig,
                    debug_data: 'Retrieved from IPFS',
                );
            }
        }

        $rcResult = (new ChunkService)->retrieveAndJoinMissingChunks(
            $flFile->sha256, $flFile->chunks_json,
            $flFile->file_size, $site_id, $request
        );

        if (! $rcResult->operation_successful) {
            $debug_data = $rcResult->error_message;
            if ($statesOnly) {
                return $this->return_success(
                    data: [
                        'stateForVisitor' => 'Resource is missing. '.$rcResult->error_message,
                        'site_id' => $site_id,
                        'res_id' => $res_id,
                        'continue' => false,
                    ],
                    debug_data: $debug_data,
                );
            }

            $message = 'The resource could not be retrieved: '.$res_id.' | Sha 256: '
            .$flFile->sha256;
            $status_code = 404;

            return response(view('conditionalXXX', compact('message', 'status_code', 'debug_data')), status: $status_code);
        }

        if ($rcResult->operation_successful) {
            $cachedResource = CachedResource::whereSha256($flFile->sha256)->first();
            $cachedResource->last_request_at = now();
            $cachedResource->requests_counter++;
            $cachedResource->save();

            return (new SiteServerController($request))->serveResourceFile(
                $request,
                $cachedResource->file_name,
                $flFile->mime_type ?? 'text/html',
                $site_id,
                $domain,
                $cachedResource->sha256,
                $siteConfig,
                debug_data: 'Retrieved from Peers',
            );
        }
    }

    public static function canHostIfSpace()
    {
        $auto_hosting_free_space_threshold_mb = H::getSettVal(SettingIds::auto_hosting_free_space_threshold_mb);

        $auto_hosting_resources_size_threshold_mb = H::getSettVal(SettingIds::auto_hosting_resources_size_threshold_mb);

        $crs = CachedResource::get()->sum('file_size');

        $free_space = disk_free_space('./');

        $autoHostingCriteriaMetFreeSpace = $auto_hosting_free_space_threshold_mb < (int) ($free_space / 1024 / 1024);

        $autoHostingCriteriaMetResourcesSize = $auto_hosting_resources_size_threshold_mb > (int) ($crs / 1024 / 1024);

        $autoHostingCriteriasMet = $autoHostingCriteriaMetFreeSpace && $autoHostingCriteriaMetResourcesSize;

        return $autoHostingCriteriasMet;
    }

    public function handleMissingState($request, $site_id, $action_type, $sha256, $mime_type, $ipfs_hash, $entity_id = null): ResultContainer
    {
        info('handleMissingState');
        $rc = new ResultContainer;
        $rc->operation_successful = true;

        $newRetrievalRequired = false;

        $retrieval = ContentRetrieval::where([
            ['site_id', $site_id],
            ['sha256', $sha256],
            ['entity_id', $entity_id],
            ['action_type', $action_type],
        ])->orderByDesc('created_at')->first();

        if ($retrieval) {
            switch ($retrieval->state) {

                case RecordStates::retrieval_processing:
                    if (Carbon::parse($retrieval->created_at)->diffInSeconds(now()) > 60) {
                        $newRetrievalRequired = true;
                    } else {
                        sleep(1);
                        for ($i = 1; $i < 3; $i++) {
                            $parallelRetrieval = ContentRetrieval::where([
                                ['site_id', $site_id],
                                ['sha256', $sha256],
                                ['entity_id', $entity_id],
                                ['action_type', $action_type],
                            ])->first();

                            switch ($parallelRetrieval->state) {
                                case RecordStates::retrieval_completed:
                                    $rc->debug_data = 'Resumed Retrieval Completed';

                                    return $rc;
                                    break;
                                case RecordStates::retrieval_failed:
                                    if ($retrieval->retries_count < 3) {
                                        $retrieval->retries_count = $retrieval->retries_count + 1;
                                        $retrieval->state = RecordStates::retrieval_pending;
                                        $retrieval->save();
                                        $contentRetrieval = $retrieval;
                                    }
                                    break;
                            }
                            sleep($i);
                        }
                        $rc->error_message = 'Resumed Retrieval failed after retries';
                        $rc->operation_successful = false;

                        return $rc;
                    }
                    break;

                case RecordStates::retrieval_failed:

                    if ($retrieval->retries_count < 3) {
                        $retrieval->retries_count = $retrieval->retries_count + 1;
                        $retrieval->state = RecordStates::retrieval_pending;
                        $retrieval->save();
                        $contentRetrieval = $retrieval;
                    } else {
                        if (Carbon::parse($retrieval->created_at)->diffInSeconds(now()) > 60) {
                            $newRetrievalRequired = true;
                            $retrieval->delete();
                        } else {
                            $rc->error_message = 'Failed Retrieval restarted after retries';
                            $rc->operation_successful = false;

                            return $rc;
                        }
                    }

                    break;
            }
        } else {
            $newRetrievalRequired = true;
        }

        if ($newRetrievalRequired) {
            $contentRetrieval = new ContentRetrieval;
            $contentRetrieval->retrieval_id = Str::random(40);
            $contentRetrieval->action_type = $action_type;
            $contentRetrieval->site_id = $site_id;
            $contentRetrieval->sha256 = $sha256;
            $contentRetrieval->entity_id = $entity_id;
            $contentRetrieval->mime_type = $mime_type;
            $contentRetrieval->ipfs_hash = $ipfs_hash;
            $contentRetrieval->save();

            H::savePassiveTriggersForSite($site_id);
        } else {
            $contentRetrieval = $retrieval;
        }

        $clientController = new ClientController;

        $ttl = 3;
        $originator_peer_id = H::getSettVal(SettingIds::peer_random_id);
        $request->merge(['ttl' => $ttl]);
        $request->merge(['originator_peer_id' => $originator_peer_id]);
        $request->merge(['retrieval_id' => $contentRetrieval->retrieval_id]);

        $rcGetData = $clientController->getDataFromHostingPeers($request);

        if ($rcGetData->operation_successful) {
            $rc->debug_data = 'In new retrieval - retrieved data from peers';

            return $rc;
        } else {
            $rc->error_message = 'In new retrieval - failed to retrieve data from peers. '.$rcGetData->error_message.' | $entity_id: '.$entity_id;
            $rc->operation_successful = false;

            return $rc;
        }
    }

    public function serveResourceFile($request, $fileName, $mime_type, $site_id, $domain, $etag, $siteConfig, $debug_data = null)
    {
        if (! Storage::disk('cached_resources')->exists($fileName)) {
            $message = 'Resource file not found. Client Error. Perform resource maintenance.';
            $status_code = 500;

            return response(view('conditionalXXX', compact('message', 'status_code')), status: $status_code);
        }

        $headersInitial = [];
        $headersInitial['Content-Type'] = $mime_type;
        $headersInitial['etag'] = $etag;
        if (! is_string($debug_data)) {
            $debug_data = json_encode($debug_data);
        }
        $headersInitial['debug_data'] = $debug_data;

        $headers = self::prepareHeaderForVisitor($headersInitial);

        if (H::inSingleSiteMode(request())) {
            $siteRoot = H::a($request->getSchemeAndHttpHost());
        } else {
            $siteRoot = H::a(H::siteUrl($domain ?? $site_id));
        }

        try {
            $clientRoot = H::a(route('0'.getDomain().'home'));
        } catch (Throwable $th) {
            $clientRoot = $siteRoot;
        }

        $uiAddresses = H::getUiAddresses();
        $path = '/';

        if (in_array($mime_type, self::getInjectableMimeTypes())) {

            $siteRootCookie = cookie('site_root', $siteRoot, 2147483647, $path, null, false, false);
            $clientRootCookie = cookie('client_root', $clientRoot, 2147483647, $path, null, false, false);
            $uiAddressesJsonCookie = cookie('ui_addresses_json', json_encode($uiAddresses), 2147483647, $path, null, false, false);
            $siteIdCookie = cookie('site_id', $domain ?? $site_id, 2147483647, $path, null, false, false);

            Cookie::queue($siteRootCookie);
            Cookie::queue($clientRootCookie);
            Cookie::queue($uiAddressesJsonCookie);
            Cookie::queue($siteIdCookie);

            $file_content = Storage::disk('cached_resources')->get($fileName);
            $file_content = $this->injectResourceContent($file_content, $mime_type, $site_id, $siteConfig, $headers);

            $headers['Content-Length'] = strlen($file_content);
            $resp = response($file_content)->withHeaders($headers);

        } else {
            $headers['Content-Length'] = Storage::disk('cached_resources')->size($fileName);
            $resp = response()->file(
                Storage::disk('cached_resources')->path($fileName), headers: $headers);
        }

        return $resp;
    }

    public static function prepareHeaderForVisitor($headers): array
    {
        $headers['X-Content-Type-Options'] = 'nosniff';
        $headers['Cache-Control'] = 'max-age=86400, must-revalidate';
        $headers['Cross-Origin-Opener-Policy'] = 'unsafe-none'; // Optional
        $headers['X-Frame-Options'] = 'DENY';

        return $headers;
    }

    public static function injectResourceContent($file_content, $mime_type, $site_id, $siteConfig, &$headers)
    {
        if ($siteConfig->inject_Feeling_Library && ! H::inSingleSiteMode(request())) {
            if (in_array($mime_type, self::getInjectableMimeTypes())) {
                $body = '</body>';
                $content = '<script src="site_assets/js/feeling-lib.js" type="text/javascript"></script></body>';
                $file_content = Str::replace($body, $content, $file_content, caseSensitive: false);
            }
        }

        return $file_content;
    }

    public function getRecentIdentities(Request $request)
    {
        $target_site_id = $request->target_site_id;
        if ($target_site_id) {
            $request->validate([
                'target_site_id' => Consts::siteIdValidationRule,
            ]);

            return redirect()->intended(
                H::a(H::siteUrl($target_site_id))
            );
        }
        /* Access-Control-Allow-Origin */
        $recent_visitor_ids = json_decode($request->cookie('recent_visitor_ids'));
        $recent_visitor_ids = collect($recent_visitor_ids);

        $existingRecentVisitorsFromCookie = Visitor::whereIn('visitor_id', $recent_visitor_ids)
            ->select(array_merge(Visitor::$publicProperties, ['alias']))
            ->orderByDesc('created_at')->get();

        foreach ($existingRecentVisitorsFromCookie as $key => $visitor) {
            $visitor->short = H::getShortSiteID($visitor->visitor_id);
            $hash = H::stringToHash($visitor->visitor_id);
            $visitor->color = H::hashToColor($hash);
        }
        $uiDomainsJson = json_encode(H::getUiDomains());

        return view('recent_identities', compact('existingRecentVisitorsFromCookie', 'uiDomainsJson'));
    }

    public function upsertRecentIdentities(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'target_site_id' => Consts::siteIdValidationRule,
            'new_identity' => 'required|alpha_dash:ascii|min:26|max:63',
        ]);

        $recent_visitor_ids = json_decode($request->cookie('recent_visitor_ids'));
        $recent_visitor_ids = collect($recent_visitor_ids);
        $recent_visitor_ids->push($request->new_identity);
        $target_site_id = $request->target_site_id;
        $recent_visitor_ids = $recent_visitor_ids->unique();
        $recent_visitor_ids = json_encode($recent_visitor_ids);
        $recent_visitor_ids = cookie('recent_visitor_ids', $recent_visitor_ids, 2147483647, '/', null, false, false);

        return redirect()->intended(route('get_recent_identities', [
            'target_site_id' => $target_site_id,
            'site_id' => Consts::visitorControlPanelAddress,
        ]))->withCookie($recent_visitor_ids);
    }

    public static function getInjectableMimeTypes()
    {
        $a = [];
        $mt = MimeType::MIME_TYPES;
        array_push($a,
            $mt['htm'],
            $mt['html'],
            $mt['shtml']
        );

        return $a;
    }
}
