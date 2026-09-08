<?php

namespace App\Models;

class VisitorResourceEnvelope
{
    public function __construct($envelope)
    {
        $this->record_json = $envelope->record_json;
        $this->signature = $envelope->signature;
        $this->signer_verification_key_base64 = $envelope->signer_verification_key_base64;

        $this->grant_record = $envelope->grant_record ?? null;
    }

    public $record_json;
    
    public $signature;
    
    public $signer_verification_key_base64;
    
    public $grant_record;

}
