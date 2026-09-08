<?php

namespace App\Http;

use App\Dicts\ActionTypes;
use App\Dicts\DataTypes;

class Consts
{
    const serveronetSiteAddress = 'serveronet.org';
    
    const snetDomainsAuthoritySiteAddress = 'coedckduv6ipzuwflejhmp7omltcvmhmpo6tl6y2fnpdue4xomuq';

    const snetDomainsAuthorityListId = 'SnetDomains';

    const centralServerAddress = 'https://serveronet.org/';

    const visitorControlPanelAddress = 'visitor-control-panel';

    const mostConnectablePorts = [80, 443, 8080];

    const startOfServeronet = '2026-09-01 00:00:00';

    const maxPendingMessagesBatchSize = 20;

    const siteUploadMaxFileSizeKiloBytes = 1024 * 1024; //1GB - in KB

    const p2pUploadMaxFileSizeBytesNetwork = 8 * 1024 * 1024; //8MB - in KB - base64encoded

    const payloadMaxSizeBytesServeronet = 8 * 1024 * 1024; //8MB - in B

    const sitesPostMaxSizeBytes = Consts::siteUploadMaxFileSizeKiloBytes * 10024; //10GB

    const sitesUploadMaxSizeBytes = Consts::siteUploadMaxFileSizeKiloBytes * 10024; //10GB

    const p2pUploadMaxSizeBytesServeronet = 4 * 1024 * 1024; //4MB

    const chunkSize = 4 * 1024 * 1024;  /* Max chunk size bytes 4MB */

    const siteFilesMaxTotalSizeBytesServeronet = 1024 * 1024 * 1024; //1GB

    const recordJsonMaxSizeBytes = 200 * 1024;

    const queryMaxSizeBytes = 100 * 1024;

    const maxFileSizeIpfsPublish = 100_000_000;

    const siteIdValidationRule = ['required', 'regex:/^(?:visitor-control-panel|[a-z2-7]{52})$/'];

    const siteIdWithDomainValidationRule = 'required|regex:/^[a-zA-Z0-9._-]+$/|min:3|max:63';

    const notRequiredSiteIdValidationRule = 'alpha_dash:ascii|min:10|max:63';

    const ipfsTestFileHash = 'QmTgZ6pKQ9AX4SQv55W1ayE3pC5fiBfAxyxGGHjVf8KRY1';

    const visitorDataDateIdFormat =  'YmdHisu';

    const maxActiveClientsConnected = 3;

    const ui_addresses = 'ui_addresses';

    const dbDataTypeToMethodMap = [
        'string' => 'string',
        'text' => 'text',
        'dateTime' => 'dateTime',
        'date' => 'date',
        'dateTimeTz' => 'dateTimeTz',
        'timestamp' => 'timestamp',
        'integer' => 'integer',
        'bigInteger' => 'bigInteger',
        'boolean' => 'boolean',
        'decimal' => 'decimal',
        'float' => 'float',
        'double' => 'double',
    ];

    const defaultDocuments = [
        'index.html', 'index.htm', 'default.html', 'default.htm', 'home.html', 'home.htm'
    ];

    const justification_context_map = [
        ActionTypes::upload_visitor_resource => DataTypes::visitor_resources,
        ActionTypes::upload_site_resource => DataTypes::site_definitions,
    ];

    const initialTrackers = [
        'http://client.serveronet.org/announce',
    ];

    const defaultSitesClients = ['http://client.serveronet.org/'];

    const defaultDomainsAndSites = [
        [
            'domain' => 'techdemo-snet',
            'site_id' => 'hwy4phcbfbigqgr5duodyntqcz34ypr2bdwx6d3oa36rsuvujt7a',
            'title' => 'Tech Demo',
        ],
        [
            'domain' => 'news-snet',
            'site_id' => 'jmayhiokgwgusjephfkxoqei42qlckj4oausxzhc5wcibsmfdn4a',
            'title' => 'News',
        ],
        [
            'domain' => 'domains-snet',
            'site_id' => 'coedckduv6ipzuwflejhmp7omltcvmhmpo6tl6y2fnpdue4xomuq',
            'title' => 'Snet Domains',
        ],
    ];

    const symlinkRequiredPhrase = 'Yes. I understand security concerns.';

    const allowedVersionSignersVerificationKeysBase64 = [
        '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'
    ];

    const passwordForTests = 'Password4Tests123!';
}
