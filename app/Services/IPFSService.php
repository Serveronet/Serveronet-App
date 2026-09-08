<?php

namespace App\Services;

use App\Dicts\SettingIds;
use App\Http\H;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Controller;
use App\Models\CachedResource;
use App\Support\Clients\IPFSClient;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IPFSService extends Controller
{

    public static function getIpfsClient($ipfs_address, $timeout)
    {
        $ipfs = new IPFSClient($ipfs_address, 0, $timeout);

        $base_uri = H::a($ipfs_address) . 'api/v0/';

        $options = [
            'base_uri' => $base_uri,
            'timeout' => $timeout,
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'prepare_ip' => true,
        ];
        H::prepareOptions($options, $base_uri);
        $ipfs->client = new Client($options);
        set_time_limit(max(ini_get('max_execution_time'), $timeout));

        return $ipfs;
    }

    public static function getIsIpfsAvailable()
    {
        $isIpfsAvailable = false;

        $was_ipfs_connectable = H::getSettVal(SettingIds::was_ipfs_connectable);

        $last_ipfs_connectivity_check_ts = H::getSettVal(SettingIds::last_ipfs_connectivity_check_ts);

        $diffLastCheck = now()->diffInMinutes($last_ipfs_connectivity_check_ts);

        if ($was_ipfs_connectable && $diffLastCheck > -15) {
            $isIpfsAvailable = true;
        }

        return $isIpfsAvailable;
    }

    public static function publishToIpfs(int $fileSize, string $contents): string|null
    {
        $isIpfsAvailable = false;
        $isIpfsReadOnly = H::getSettVal(SettingIds::ipfs_read_only);
        if ($isIpfsReadOnly) return null;
        (new AdminController())->testIpfs();
        $isIpfsAvailable = IPFSService::getIsIpfsAvailable();

        if ($isIpfsAvailable) {
            try {
                //Max size 10mb - 60 seconds - proportional distribution
                $timeout = $fileSize / 10 * 1024 * 1024 * 60;
                if ($timeout < 3) $timeout = 3;
                if ($timeout > 60) $timeout = 60;
                if ($timeout == 0) $timeout = 10;
                $ipfs = IPFSService::getIpfsClient(H::getSettVal(SettingIds::ipfs_address), $timeout);

                $ipfsAddResult = $ipfs->add($contents, '', ['pin' => true]);
                $ipfsHash = $ipfsAddResult['Hash'];
            } catch (\Throwable $th) {
                $ipfsHash = null;
            }
        } else {
            $ipfsHash = null;
        }
        return $ipfsHash;
    }

    public static function unpinFromIpfs(string $hash): bool
    {
        $isIpfsAvailable = false;
        $isIpfsReadOnly = H::getSettVal(SettingIds::ipfs_read_only);
        if ($isIpfsReadOnly) return false;
        (new AdminController())->testIpfs();
        $isIpfsAvailable = IPFSService::getIsIpfsAvailable();
        if ($isIpfsAvailable) {
            $ipfs = IPFSService::getIpfsClient(H::getSettVal(SettingIds::ipfs_address), 3);
            $ipfs->unpin($hash);
            return true;
        }
        return false;
    }

    public static function retrieveFromIpfs($file_size, $ipfs_hash, $sha256): bool
    {
        $isIpfsAvailable = IPFSService::getIsIpfsAvailable();
        if ($isIpfsAvailable) {
            //Max size 10mb - 60 seconds - proportional distribution
            $timeout = $file_size / 10 * 1024 * 1024 * 60;
            if ($timeout < 3) $timeout = 3;
            if ($timeout > 60) $timeout = 60;
            if ($timeout == 0) $timeout = 10;

            $ipfs = IPFSService::getIpfsClient(H::getSettVal(SettingIds::ipfs_address), $timeout);
            
            try {
                $content = $ipfs->cat($ipfs_hash);
            } catch (\Throwable $th) {
                return false;
            }
            
            if (hash('sha256', $content) == $sha256) {
                info('Res retrieved from IPFS '.$sha256.' '.$ipfs_hash);
                $cachedResource = new CachedResource();
                $cachedResource->sha256 = $sha256;
                $cachedResource->ipfs_hash = $ipfs_hash;
                $file_name = Str::random(40);
                Storage::disk('cached_resources')->put($file_name, $content);
                $cachedResource->file_name = $file_name;
                $cachedResource->file_size = $file_size;
                $cachedResource->save();
                return true;
            } else return false;
        } else return false;
    }
}
