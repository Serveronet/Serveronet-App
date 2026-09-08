<?php

namespace App\Models;

use App\Dicts\ListProviderTypes;

class SiteConfig
{
    public $trusted_Site_Peers = [
        'defaultValue' => [],
        'exampleValue' => ['http://example.com'],
    ];

    public $prefered_Trackers = [
        'defaultValue' => [],
        'exampleValue' => ['http://example.com/announce'],
    ];

    public $site_Admin_Signers = [
        'defaultValue' => '',
        'exampleValue' => '',
    ];

    public $site_Requires_Authentication = [
        'defaultValue' => false,
        'exampleValue' => false,
    ];

    public $single_Page_Application = [
        'defaultValue' => false,
        'exampleValue' => false,
    ];

    public $allow_Visitor_Files = [
        'defaultValue' => true,
        'exampleValue' => true,
    ];

    public $site_Has_Database = [
        'defaultValue' => false,
        'exampleValue' => true,
    ];

    public $db_Schema_Versions = [
        'defaultValue' => [],
        'exampleValue' => [
            [
                'version_number' => 0,
                'tableCreates' => [
                    'posts' => [
                        'body' => ['datatype' => 'string', 'nullable' => true],
                        'title' => ['datatype' => 'string', 'nullable' => true],
                        'body_short' => ['datatype' => 'string', 'nullable' => true],
                        'pinned' => ['datatype' => 'integer', 'nullable' => true, 'default' => 0],
                    ],
                    'registered_visitors' => [
                        'visitor_id' => ['datatype' => 'string', 'nullable' => true],
                        'alias' => ['datatype' => 'string', 'nullable' => true],
                    ],
                    'tmp_table' => [
                        'col1' => ['datatype' => 'string', 'nullable' => true],
                    ],
                ],
                'columnAlters' => [
                    'posts' => [
                        'author' => ['datatype' => 'string', 'nullable' => true, 'default' => 'Unknown'],
                    ],
                ],
                'indexCreates' => [
                    'posts' => ['pinned'],
                ],
            ],
            [
                'version_number' => 1,
                'columnAlters' => [
                    'posts' => [
                        'co-author' => ['datatype' => 'string', 'nullable' => true, 'default' => 'Unknown'],
                    ],
                ],
                'columnDrops' => [
                    'registered_visitors' => [
                        'alias',
                    ],
                ],
                'tableDrops' => [
                    'tmp_table',
                ],

            ],
        ],
    ];

    public $show_Adults_Only_Gate = [
        'defaultValue' => false,
        'exampleValue' => false,
    ];

    public $list_Providers = [
        'defaultValue' => [],
        'exampleValue' => [
            [
                'tag' => 'Banned_Sites',
                'description' => 'List of banned sites',
                'table' => 'banned_sites',
                'column_key' => 'site_id',
                'column_value' => 'site_id',
                'data_type' => ListProviderTypes::banned_sites,
            ],
        ],
    ];

    public $inject_Feeling_Library = [
        'defaultValue' => true,
        'exampleValue' => false,
    ];
}
