<?php

namespace App\Http\Controllers;

use App\Dicts\KnownResponses;
use App\Http\H;
use App\Models\LocalTrackerInfoHashPeer;
use App\Models\Site;
use Arokettu\Bencode\Bencode;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BuiltInTrackerController extends Controller
{
    public function builtInTracker(Request $request)
    {
        $info_hashes = Cache::get('info_hashes', []);
        $sites = Site::with('most_recent_site_definition')->get();
        $sitesWithHashes = [];
        foreach ($sites as $key => $site) {
            $site->site_info_hash = sha1($site->site_id);
            array_push($sitesWithHashes, [
                'site_info_hash' => $site->site_info_hash,
                'site_id' => $site->site_id,
                'title' => $site->most_recent_site_definition->title ?? 'Unknown Site',
            ]);
        }

        foreach ($info_hashes as $key_info_hash => $info_hash) {
            foreach ($sitesWithHashes as $key => $siteHash) {
                if ($key_info_hash == $siteHash['site_info_hash']) {
                    $info_hashes[$key_info_hash]['title'] = $siteHash['title'];
                    $info_hashes[$key_info_hash]['site_id'] = $siteHash['site_id'];

                    continue;
                }
            }
        }

        return view('built_in_tracker', compact('info_hashes'));
    }

    public function handleAnnounceByUrl(Request $request)
    {
        info('handleAnnounceByUrl Begin');
        /* Required parameters */
        $requiredParams = ['info_hash', 'peer_id', 'port', 'uploaded', 'downloaded', 'left'];
        foreach ($requiredParams as $param) {
            if (! $request->has($param)) {
                return response(Bencode::encode(['failure reason' => "Missing required parameter: {$param}"]), 200)
                    ->header('Content-Type', 'text/plain');
            }
        }
        // info('Raw: '.$request->input('peer_id').' '.$request->input('port'));

        $info_hash = bin2hex($request->input('info_hash'));
        $peerId = $request->input('peer_id');
        $port = (int) $request->input('port');
        $peer_url = $request->input('peer_url');
        $remoteIp = $request->ip();

        if (! empty($peer_url)) {
            $urlIp = H::obtainIpForUrl($peer_url);
        } else {
            $urlIp = $remoteIp;
        }

        info('Received Peer: '.$peer_url.' '.$urlIp.' '.$remoteIp.' '.$port);
        if ($urlIp !== $remoteIp) {
            info('handleAnnounceByUrl ip mismatch: '.$peer_url.' '.$urlIp.' '.$remoteIp.' '.$port);
            $rc = ClientController::peerHttpsStatus($remoteIp, $port);
            if ($rc->operation_successful && $rc->data != KnownResponses::nok) {
                $https = $rc->data == 'https';
                $peer_url = H::a($remoteIp.':'.$port, https: $https);
            }
        }

        $event = $request->input('event', '');
        $compact = $request->input('compact', 1);
        $noPeerId = $request->input('no_peer_id', 0);

        /* Delete expired announces */
        $expiredHashPeers = LocalTrackerInfoHashPeer::where('created_at', '<', Carbon::now()->subMinutes(30))
        ->where('last_announce', '<', Carbon::now()->subMinutes(30))->get();
        foreach ($expiredHashPeers as $key => $expiredHashPeer) {
            $expiredHashPeer->delete();
        }

        $localTrackerInfoHashPeer = LocalTrackerInfoHashPeer::where('ip', $remoteIp)
            ->where('port', $port)
            ->where('info_hash', $info_hash)
            ->where('peer_id', $peerId)
            ->first();
        info('handleAnnounceByUrl '.$event.' '.$remoteIp.' '.$port);
        if ($event === 'stopped') {
            if ($localTrackerInfoHashPeer) {
                $localTrackerInfoHashPeer->archived_at = now();
                $localTrackerInfoHashPeer->updated_at = now();
                $localTrackerInfoHashPeer->last_event = $event;
                $localTrackerInfoHashPeer->save();
            }
        } else {
            if ($localTrackerInfoHashPeer) {
                $localTrackerInfoHashPeer->archived_at = null;
                $localTrackerInfoHashPeer->updated_at = now();
                $localTrackerInfoHashPeer->last_event = $event;
                $localTrackerInfoHashPeer->last_announce = now();
                $localTrackerInfoHashPeer->peer_id = $peerId;
                $localTrackerInfoHashPeer->save();
            } else {
                $localTrackerInfoHashPeer = new LocalTrackerInfoHashPeer;
                $localTrackerInfoHashPeer->info_hash = $info_hash;
                $localTrackerInfoHashPeer->ip = $remoteIp;
                $localTrackerInfoHashPeer->port = $port;
                $localTrackerInfoHashPeer->peer_url = $peer_url;
                $localTrackerInfoHashPeer->peer_id = $peerId;
                $localTrackerInfoHashPeer->last_event = $event;
                $localTrackerInfoHashPeer->last_announce = now();
                $localTrackerInfoHashPeer->save();
            }
        }

        $siteInfoHashPeers = LocalTrackerInfoHashPeer::where('info_hash', $info_hash)->take(20)->get();
        $siteInfoHashPeers = collect($siteInfoHashPeers)->toArray();

        $peers = $siteInfoHashPeers;
        $response = [
            'interval' => 900, // 15 minutes
            'peers' => $compact ? $this->getCompactPeers($peers, $peerId) :
                $this->getNonCompactPeersWithUrl($peers, $peerId, $noPeerId),
            'warning message' => 'Serveronet Built-In Tracker',
        ];

        return response(Bencode::encode($response), 200)->header('Content-Type', 'text/plain');
    }

    private function getCompactPeers(array $peers, string $excludePeerId): string
    {
        $compactPeers = '';
        foreach ($peers as $peer) {
            /* Exclude the current peer from the list */
            if ($peer['peer_id'] === $excludePeerId) {
                continue;
            }

            $compactPeers .= pack('Nn', ip2long($peer['ip']), $peer['port']);
        }

        return $compactPeers;
    }

    private function getNonCompactPeersWithUrl(array $peers, string $excludePeerId, bool $noPeerId): array
    {
        $nonCompactPeers = [];
        foreach ($peers as $peer) {
            if ($peer['peer_id'] === $excludePeerId) {
                continue;
            }

            $p = [
                'ip' => $peer['ip'],
                'port' => $peer['port'],
                'peer_url' => $peer['peer_url'],
            ];

            // Log::debug('getNonCompactPeersWithUrl: '.json_encode($peers));

            if (! $noPeerId) {
                $p['peer id'] = $peer['peer_id'];
            }

            $nonCompactPeers[] = $p;
        }

        return $nonCompactPeers;
    }
}
