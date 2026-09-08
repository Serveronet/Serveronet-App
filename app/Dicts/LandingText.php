<?php

namespace App\Dicts;

class LandingText
{
    public static $blocks = [
        [
            'title' => 'P2P Websites',
            'color' => 'card-back-gradient',
            'image' => './sn_client_resources/img/token_FILL0_wght100_GRAD-25_opsz48.svg',
            'subtitle' => 'Free and uncensorable',
            'description' => 'Decentralized, Cryptographic Peer 2 Peer Network of Sites',
        ],
        [
            'title' => 'Uncensorable',
            'image' => './sn_client_resources/img/campaign_FILL0_wght100_GRAD-25_opsz48.svg',
            'subtitle' => 'No central server. No domains. ',
            'description' => 'No one can take away you domain as they are not required. Optional Opennic domains allows you to redirect from clearnet DNS.
            Decentralized with fallbacks in case of obstacles.',
        ],
        [
            'title' => 'Free and Open source',
            'color' => 'card-back-gradient',
            'image' => './sn_client_resources/img/money_off_FILL0_wght100_GRAD-25_opsz48.svg',
            'subtitle' => 'Hosted by peers',
            'description' => 'Publish you site to peers and if it\'s interesting, it will stay online forever. Sites are hosted by peers.
            Serveronet uses only scripting languages. No black box binary blobs enforced. Opensource on all building blocks.',
        ],
        [
            'title' => 'Decentralized',
            'image' => './sn_client_resources/img/Ipfs-logo-1024-ice-text.png',
            'image2' => '',
            'subtitle' => 'PQ Crystals cryptography, BitTorrent Trackers, IPFS',
            'description' => 'Serveronet works Peer 2 Peer, uses Bittorrent trackers and optionally IPFS as a file storage.',
        ],
        [
            'title' => 'Anonymity',
            'color' => 'card-back-gradient',
            'image' => './sn_client_resources/img/Tor-logo-2011-flat.svg',
            'subtitle' => 'Tor and public keys',
            'description' => 'Serveronet relies on PQ Crystals cryptography to create identitities. No emails or alike required.
            Tor can be used to route all the comunication.',
        ],
        [
            'title' => 'SEO Friendly',
            'image' => './sn_client_resources/img/travel_explore_FILL0_wght100_GRAD-25_opsz48.svg',
            'subtitle' => 'Lives in clearnet',
            'description' => 'Unlike other P2P networks Serveronet is not Darknet only. Serveronet Sites behave like regular websites. 
            Ads and CDNs are fully supported.',
        ],
        [
            'title' => 'Developer friendly',
            'color' => 'card-back-gradient',
            'image' => './sn_client_resources/img/code_FILL0_wght100_GRAD-25_opsz48.svg',
            'subtitle' => 'P2P Site Development made easy',
            'description' => 'If you want to publish your Site you don\'t have to learn some obscure P2P technology. 
            Serveronet follows standard patterns of frontend, backend with database and user accounts patters. Heavy lifting happens under the hood.',
        ],
        [
            'title' => 'Deployment',
            'subtitle' => 'Linux, Windows, Servers',
            'image' => './sn_client_resources/img/deployed_code_FILL0_wght100_GRAD-25_opsz48.svg',
            'description' => 'You can use it on your own PC as a client or deploy on a public hosting server. 
            Deployment is easy and should work on a Shared Hosting, VPS, Raspbian etc.',
        ],
        [
            'title' => 'Moderation and Control',
            'color' => 'card-back-gradient',
            'subtitle' => 'Optional governance',
            'image' => './sn_client_resources/img/supervisor_account_FILL0_wght100_GRAD-25_opsz48.svg',
            'description' => 'Spam, abuse are known issues. Site owner can moderate unwanted content. 
            Client owner can ban sites to prevent hosting of an unwanted Site.
            ',
        ],
    ];

    public static $roadMapBlocks = [
        [
            'title' => 'Private Sites',
            'color' => 'card-back-gradient',
            'image' => './sn_client_resources/img/lock_100dp_1F1F1F_FILL0_wght100_GRAD0_opsz48.svg',
            'subtitle' => 'Members only websites',
            'description' => 'Private, members only Sites with "Breakout Rooms" (Content available to member peers only)',
        ],
        [
            'title' => 'External storages',
            'image' => './sn_client_resources/img/desktop_cloud_100dp_1F1F1F_FILL0_wght100_GRAD0_opsz48.svg',
            'subtitle' => 'External cloud storages',
            'description' => 'Client can store data in externals storages like AWS, Google Drive.',
        ],
    ];

}


