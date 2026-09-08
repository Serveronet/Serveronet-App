<?php

namespace App\Dicts;

class PublishedVersionsLabels
{
    public static array $mapping = [

        PublishedVersionsTypes::client_update => 'Client Update',

        PublishedVersionsTypes::windows_client_bundle => 'Windows Client Bundle',

        PublishedVersionsTypes::linux_and_mac_client_bundle => 'Linux/Mac Client Bundle',

        PublishedVersionsTypes::server_bundle => 'Server Bundle',
        
    ];
}
