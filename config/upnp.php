<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SSDP Discovery Settings
    |--------------------------------------------------------------------------
    */

    'ssdp' => [
        'multicast_ip' => '239.255.255.250',
        'multicast_port' => 1900,
        'timeout' => 3,
        'max_results' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Known Routers
    |--------------------------------------------------------------------------
    | Map of router identifiers to their IPs. Used for direct-connection
    | fallback when SSDP multicast discovery doesn't yield results.
    */

    'routers' => [
        'tplink' => [
            'ip' => env('TPLINK_ROUTER_IP', '192.168.2.1'),
            'description_paths' => [
                '/gw.xml',
                '/igd.xml',
                '/device.xml',
                '/root.xml',
                '/xml/IGD.xml',
                '/upnp/Igd.xml',
                '/desc.xml',
                '/rootDesc.xml',
                '/data/ModelInfo.xml',
            ],
        ],
        'asus' => [
            'ip' => env('ASUS_ROUTER_IP'),
            'description_paths' => [
                '/gadget.xml',
                '/upnp/control.xml',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Port-Forward Settings
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'protocol' => 'TCP',
        'description' => 'Serveronet',
        'duration' => 0,
    ],

];