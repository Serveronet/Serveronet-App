<?php

namespace App\Services;

use App\Http\Controllers\Controller;
use App\Http\H;
use App\Models\PqKeySet;
use ParagonIE\PQCrypto\Compat;
use ParagonIE\PQCrypto\MLDSA65\SigningKey;

class PQCryptoService extends Controller
{
    public function genKeySet(): object
    {
        $keys = Compat::mldsa65_keygen();
        ['signingKey' => $sk, 'verificationKey' => $vk] = $keys;

        $pqKeySet = new PqKeySet;

        $pqKeySet->seed = $sk->bytes();
        $pqKeySet->base64_seed = base64_encode($sk->bytes());
        $pqKeySet->hashed_id = H::verKeyToHashedId($vk->bytes());
        $pqKeySet->verification_key_base64 = base64_encode($vk->bytes());
        $pqKeySet->verification_key_bytes = $vk->bytes();

        return $pqKeySet;
    }

    public function getKeySetFromSeed($base64Seed): object
    {
        $sk = SigningKey::fromBytes(base64_decode($base64Seed));

        $pqKeySet = new PqKeySet;

        $pqKeySet->seed = $sk->bytes();
        $pqKeySet->base64_seed = base64_encode($sk->bytes());
        $pqKeySet->hashed_id = H::verKeyToHashedId($sk->getVerificationKey()->bytes());
        $pqKeySet->verification_key_base64 = base64_encode($sk->getVerificationKey()->bytes());
        $pqKeySet->verification_key_bytes = $sk->getVerificationKey()->bytes();

        return $pqKeySet;
    }

    public function isCryptoCorrectVerKey($base64VerificationKey, $message, $base64Signature): bool
    {
        $verificationKeyBytes = base64_decode($base64VerificationKey);
        $binarySignature = base64_decode($base64Signature);
        $valid = Compat::mldsa65_verify($verificationKeyBytes, $binarySignature, $message);

        return $valid;
    }

    public function generateSignature($encryptedSeedHolder, $message_json): string
    {
        $seed = decrypt($encryptedSeedHolder->encrypted_seed);
        $signature = Compat::mldsa65_sign($seed, $message_json);
        $base64_signature = base64_encode($signature->bytes());

        return $base64_signature;
    }
}
