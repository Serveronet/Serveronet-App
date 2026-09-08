<?php

namespace App\Services;

use App\Dicts\ActionTypes;
use App\Dicts\CachePrefixes;
use App\Dicts\KnownResponses;
use Spatie\Url\Url;

use App\Dicts\SysProps;

use App\Http\Consts;
use App\Http\Controllers\BackgroundProcessingController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SiteServerController;
use App\Http\Controllers\TorrentTrackersController;
use App\Http\Controllers\UtilsController;
use App\Http\H;
use App\Models\CachedResource;
use App\Models\Peer;
use App\Models\PendingAction;
use App\Models\ResultContainer;

use App\Models\Site;
use App\Models\SitePeer;
use App\Models\Tracker;
use App\Models\VisitorResource;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Schema;

use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Str;

use PDO;
use stdClass;
use Throwable;

class ChunkService extends Controller
{
    function retrieveAndJoinMissingChunks($sha256, $chunks_json, $file_size, $site_id, $request): ResultContainer
    {
        $rc = new ResultContainer();
        $rc->operation_successful = true;

        $chunks = json_decode($chunks_json);

        foreach ($chunks as $key => $chunk) {
            $chunkCR = CachedResource::whereSha256($chunk->sha256)->first();
            if (! $chunkCR) {
                $rcResult = (new SiteServerController())->handleMissingState(
                    request: $request, 
                    site_id: $site_id, 
                    action_type: ActionTypes::retrieve_resource, 
                    sha256: $chunk->sha256, 
                    mime_type: $visitorResource->mime_type ?? 'text/html', 
                    ipfs_hash: $chunk->ipfs_hash ?? null, 
                    entity_id: null
                );

                if (! $rcResult->operation_successful) {
                    $message = 'Resource chunk could not be retrieved. '.$rcResult->error_message.' | Sha256: '.$chunk->sha256; 
                    $rc->operation_successful = false;
                    $rc->error_message = $message;
                    return $rc;
                }
            }
        }
        (new ChunkService)->joinChunks($chunks, $sha256, $file_size);
        return $rc;
    }

    function chunkify(string $tmpPath): array 
    {
        $path = $tmpPath;
        $handle = fopen($path, "rb");

        if (FALSE === $handle) {
            throw new Exception("Failed to open stream to URL", 1);
        }

        $chunks = [];
        $contents = '';
        $chunkSize = Consts::chunkSize;
        $i = 0;
        $partNum = 0;
        
        while (! feof($handle)) {
            $i++;
            $contents .= fread($handle, $chunkSize / 1024);
            if ($i == $chunkSize / 4096) {
                $partNum++;
                $i = 0;
                $chunkMeta = $this->saveChunkAndHash($contents);
                $chunks[$partNum] = $chunkMeta;
                $contents = '';
            }
        }
        $partNum++;
        
        if ($contents !== '') {
            $chunkMeta = $this->saveChunkAndHash($contents);
            $chunks[$partNum] = $chunkMeta;
            $contents = '';
        }
        
        fclose($handle);
        return $chunks;
    }

    function saveChunkAndHash(string $contents) 
    {
        // info('saveChunkAndHash');

        $sha256 = hash('sha256', $contents);
        $file_size = strlen($contents);
        $cachedResource = CachedResource::where([
            ['sha256', $sha256],
        ])->first();
        $ipfsHash = null;
        if (! $cachedResource) {
            $ipfsHash = IPFSService::publishToIpfs($file_size, $contents);
            $file_name = Str::random(40);
            Storage::disk('cached_resources')->put($file_name, $contents);
            $cachedResource = new CachedResource();
            $cachedResource->sha256 = $sha256;
            $cachedResource->ipfs_hash = $ipfsHash;
            $cachedResource->file_name = $file_name;
            $cachedResource->file_size = $file_size;
            $cachedResource->save();
        } else {
            $ipfsHash = $cachedResource->ipfs_hash;
        }

        return ['sha256' => $sha256, 'file_size' => $file_size, 'ipfs_hash' => $ipfsHash];
    }

    public function joinChunksOrRequire($site_id = null, $pfm = false)
    {
        $sitesToBeHosted = Site::where('is_to_be_hosted', true)->with('most_recent_site_definition')->get();
        $siteWithVisitorFiles = collect();

        foreach ($sitesToBeHosted as $key => $site) {
            $siteConfig = (new SiteConfigService)->getSiteConfig($site);
            if (! $siteConfig)
            continue;

            if ($siteConfig->allow_Visitor_Files ?? false)
            $siteWithVisitorFiles->push($site);
        }

        foreach ($siteWithVisitorFiles as $key => $site) {
            $site_id = $site->site_id;
            H::pfm('site_id: '.$site_id, pfm: $pfm);
            
            $visitorResources = VisitorResource::where([
                ['site_id', $site_id],
            ])->get();

            foreach ($visitorResources as $key => $visitorResource) {
                H::pfm($visitorResource->original_file_name, pfm: $pfm);

                $cr = CachedResource::whereSha256($visitorResource->sha256)->first();
                $is_available  = (bool) $cr;

                if ($is_available) 
                continue;
            
                $chunks = json_decode($visitorResource->chunks_json);
                $chunksCount = count((array)$chunks);
                $availableCount = 0;

                $cr = null;
                foreach ($chunks as $key => $chunk) {
                    $cr = CachedResource::whereSha256($chunk->sha256)->first();
                    if ($cr) {
                        $availableCount++;
                    } else {
                        $pendingAction = PendingAction::where([
                            ['action_type', ActionTypes::retrieve_resource],
                            ['sha256', $chunk->sha256],
                            ['site_id', $site_id],
                        ])->first();
                        if (! $pendingAction) {
                            H::pfm('Adding pending action', pfm: $pfm);
                            $pendingAction = new PendingAction();
                            $pendingAction->action_type = ActionTypes::retrieve_resource;
                            $pendingAction->sha256 = $chunk->sha256;
                            // $pendingAction->mime_type = $site_resource->mime_type;
                            $pendingAction->site_id = $site_id;
                            $pendingAction->ipfs_hash = $chunk->ipfs_hash ?? null;
                            $pendingAction->file_size = $chunk->file_size;
                            $pendingAction->save();
                        }

                    }
                    $cr = null;
                }
                if ($chunksCount == $availableCount) {
                    H::pfm('Promoting to full file', pfm: $pfm);
                    (new ChunkService)->joinChunks($chunks, $visitorResource->sha256, $visitorResource->file_size);
                }
            }
        }
    }

    function joinChunks($chunks, $sha256, $file_size) 
    {
        info('joinChunks');
        $fileRetrievedInMeantime = CachedResource::whereSha256($sha256)->first();
        if ($fileRetrievedInMeantime)
        return $fileRetrievedInMeantime;

        $full_file_name = Str::random(40);
        $contents = '';
        Storage::disk('cached_resources')->put($full_file_name, $contents);
        $fileSystem = Storage::disk('cached_resources');
        $fullFilePath = $fileSystem->path($full_file_name);
        $fullFileHandle = null;
        foreach ($chunks as $key => $chunk) {
            $cr = CachedResource::whereSha256($chunk->sha256)->first();
            $handle = Storage::disk('cached_resources')->readStream($cr->file_name);
            $contents = '';
            $fullFileHandle = fopen($fullFilePath, "a");
            while (!feof($handle)) {
                $contents .= fread($handle, Consts::chunkSize);
            }
            fwrite($fullFileHandle, $contents, strlen($contents));
            fclose($handle);
        }
        fclose($fullFileHandle);

        $fileJoinedInOtherThread = CachedResource::whereSha256($sha256)->first();
        if ($fileJoinedInOtherThread) {
            unlink($fullFileHandle);
            return $fileJoinedInOtherThread;
        } else {
            $cachedResource = new CachedResource();
            $cachedResource->sha256 = $sha256;
            $cachedResource->ipfs_hash = null; 
            $cachedResource->file_name = $full_file_name;
            $cachedResource->file_size = $file_size;
            $cachedResource->save();
            return $cachedResource;
        }
    }

    function consumeReceivedResourceChunk(string $action_type, string $record_json, string $sha256): void 
    {
        info('consumeReceivedResourceChunk '.$action_type);
        info('consumeReceivedResourceChunk record_json '.Str::limit($record_json, 100));
        $chunks = collect();
        switch ($action_type) {
            case ActionTypes::upload_site_resource :
                /* Determine which site resource contains chunk */
                $resourcesList = collect(json_decode(json_decode($record_json)
                ->file_listing_json));
                $targetRes = new stdClass();
                
                foreach ($resourcesList as $key => $res) {
                    $resChunks = collect(json_decode($res->chunks_json));
                    if ($resChunks->where('sha256', $sha256)->first()) {
                        $targetRes = $res;
                        $chunks = $resChunks;
                        break;
                    }
                }
            break;

            case ActionTypes::upload_visitor_resource :
                $targetRes = json_decode($record_json);
                $chunks = collect(json_decode($targetRes->chunks_json));
            break;
            
            default:

            break;
        }

        $targetSha256 = $targetRes->sha256; 
        $targetFileSize = $targetRes->file_size;

        /* Checking if new chunk completes a resource */
        $availableChunks = CachedResource::whereIn('sha256', $chunks->pluck('sha256')->toArray())->get();
        info('$availableChunks');
        info(json_encode($availableChunks));
        info('json_encode($chunks)');
        info(json_encode($chunks));

        if (count($availableChunks) == count($chunks)) {
            (new ChunkService())->joinChunks($chunks, $targetSha256, $targetFileSize);
        }
    }
}
