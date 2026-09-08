<?php

namespace App\Dicts;

class PublishedVersionsTypes
{
    public const client_update = 'client_update';
    
    public const windows_client_bundle = 'windows_client_bundle';

    public const linux_and_mac_client_bundle = 'linux_and_mac_client_bundle';

    public const server_bundle = 'server_bundle';   
    
    public static function getConstants()
    {
        $r = new \ReflectionClass(__CLASS__);
        return $r->getConstants();
    }
}
