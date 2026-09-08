<?php

namespace App\Http\Controllers;

use App\Dicts\SettingIds;
use App\Http\H;
use App\Models\Dev\ReportingPeerEdge;
use App\Models\Peer;
use App\Services\PQCryptoService;
use Base32\Base32;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ParagonIE\PQCrypto\Compat;
use ParagonIE\PQCrypto\MLDSA65\SigningKey;

class DevPublicController extends Controller
{
    public function refreshConfig()
    {
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('route:clear');

        Artisan::call('optimize');
        
        return response('Config and Cache refreshed');
    }

    public function receivePeersReporting(Request $request)
    {
        if (H::isDevNode()) {
            return response('For Dev env only');
        }

        $peers = json_decode($request->peers, true);

        foreach ($peers as $key => $peer) {
            $reportingPeerEdge = ReportingPeerEdge::where([
                ['self_client_address', $peer['self_client_address']],
                ['remote_client_address', $peer['remote_client_address']],
            ])->first();
            if (! $reportingPeerEdge) {
                $reportingPeerEdge = new ReportingPeerEdge;
            }

            $reportingPeerEdge->self_client_address = $peer['self_client_address'];
            $reportingPeerEdge->remote_client_address = $peer['remote_client_address'];
            $reportingPeerEdge->reputation = $peer['reputation'];
            $reportingPeerEdge->is_remote_connectable = $peer['is_remote_connectable'];
            $reportingPeerEdge->is_return_self_connectable = $peer['is_return_self_connectable'];
            $reportingPeerEdge->save();
        }
    }

    public function servePeerEdgesReporting(Request $request)
    {
        $reportingPeerEdge = ReportingPeerEdge::select('self_client_address', 'remote_client_address',
            'reputation', 'is_remote_connectable', 'is_return_self_connectable')->get();

        return $reportingPeerEdge->toArray();
    }

    public function reportPeersConnectivity($pfm = false): void
    {
        $dev_custom_central_server_address = H::getSettVal(SettingIds::dev_custom_central_server_address);
        if (! $dev_custom_central_server_address)
            return;

        $peers = Peer::select('client_address', 'reputation', 'last_connected_at', 'last_inbound_connected_at')->get();
        foreach ($peers as $key => $peer) {
            $peer->self_client_address = H::getPublicSelfAddress();
            $peer->remote_client_address = $peer->client_address;
            $peer->is_remote_connectable = ($peer->last_connected_at > now()->subHour());
            $peer->is_return_self_connectable = ($peer->last_inbound_connected_at > now()->subHour());
            unset($peer->last_connected_at);
            unset($peer->last_inbound_connected_at);
            unset($peer->client_address);
        }

        $url = $dev_custom_central_server_address.'dev/receive_peers_reporting';

        H::forceFlush();

        $client = H::setupClient(false);
        $options = [
            'timeout' => H::timeoutAdjust(false, 10),
            'form_params' => [
                'peers' => json_encode($peers->toArray()),
            ],
            'prepare_ip' => true,
        ];
        H::prepareOptions($options, $url);

        $client->post($url, $options);
    }

    public function deleteFirstRunFile()
    {
        if (! H::isDevNode()) {
            H::pfm('Not a Dev node - delete file first run file manually');

            return;
        }

        if (Storage::disk('local')->exists('first_run_completed.php')) {
            Storage::disk('local')->delete('first_run_completed.php');
        } else {
            Storage::disk('local')->put('first_run_completed.php', 'true');
        }

        return redirect('/');
    }

    public function pqCryptoParagonieShowCase()
    {
        // Key generation
        $keyPair = Compat::mldsa65_keygen();
        ['signingKey' => $sk, 'verificationKey' => $vk] = $keyPair;
        dump('keyPair generated ');
        // dump($keyPair);
        $verificationKeyBytes = $vk->bytes();
        dump('verificationKeyBytes: '.$verificationKeyBytes);
        // dump($verificationKeyBytes);
        $secretSeedBytes = $keyPair['signingKey']->bytes();
        dump('signingKey secretSeedBytes: '.$secretSeedBytes);
        // dump($secretSeedBytes);
        // dump('Restoring singing key: ');
        $sk = SigningKey::fromBytes($secretSeedBytes);
        dump('Restored singing key from bytes: '.$sk->bytes());
        // dump($sk->bytes());
        // dd($sk->bytes());
        dump('Restored Verification Key bytes: '.$sk->getVerificationKey()->bytes());
        // dump($sk->getVerificationKey()->bytes());
        $verificationKeyBytes = $sk->getVerificationKey()->bytes();
        $base64OfVerificationKeyBytesSD = base64_encode($verificationKeyBytes);
        dump('base64 verificationKey '.$base64OfVerificationKeyBytesSD);
        $binaryHashOfverificationKey = hash('sha256', $verificationKeyBytes, true);
        // dd(H::verKeyToHashedId($verificationKeyBytes));
        dump('binaryHashOfverificationKey: '.$binaryHashOfverificationKey);
        $base32encodedbinaryHashOfVerificationKey = Base32::encode($binaryHashOfverificationKey);
        dump('Base32 of Hash verificationKey: '.$base32encodedbinaryHashOfVerificationKey);
        dump('Count $base32encodedbinaryHashOfVerificationKey: '.strlen($base32encodedbinaryHashOfVerificationKey));
        $lowerCased = strtolower($base32encodedbinaryHashOfVerificationKey);
        dump('$lowerCased: '.$lowerCased);
        $withoutEquals = (string) Str::replace('=', '', $lowerCased);
        dump('Site Id - withoutEquals: '.$withoutEquals);
        $base32Padeed = str_pad($withoutEquals, 56, '=', STR_PAD_RIGHT);
        dump('$base32Padeed: '.$base32Padeed);
        $upperCased = strtoupper($base32Padeed);
        dump('$upperCased: '.$upperCased);
        $fromSiteIdBinaryHashOfverificationKey = Base32::decode($upperCased);
        dump('From Site ID binaryHashOfverificationKey: '.$fromSiteIdBinaryHashOfverificationKey);

        $base64DecodedVerificationKeyBytesSD = base64_decode($base64OfVerificationKeyBytesSD);

        $binaryHashOfverificationKeySD = hash('sha256', $base64DecodedVerificationKeyBytesSD, true);
        dump('binaryHashOfverificationKeySD: '.$binaryHashOfverificationKeySD);
        // dump('fromSiteIdBinaryHashOfverificationKey: '.$fromSiteIdBinaryHashOfverificationKey);

        $message = 'message';
        $signature = Compat::mldsa65_sign($sk, $message);
        $base64_signature = base64_encode($signature->bytes());
        // dd($base64_signature);

        dd((new PQCryptoService)->isCryptoCorrectVerKey($base64OfVerificationKeyBytesSD, $message, $base64_signature));
        dump('$signature: '.$signature->bytes());
        $valid = Compat::mldsa65_verify($vk, $signature, $message);
        dump('Verify is valid: '.tfyn($valid)); // bool(true)
        $vk = substr($base64DecodedVerificationKeyBytesSD, 0, 1951).'a';
        $valid = Compat::mldsa65_verify($vk, $signature, $message);
        dump('Verify tampered is valid: '.tfyn($valid)); // bool(true)
        // dd(strlen($vk));
    }
}
