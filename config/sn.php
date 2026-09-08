<?php

return [

    'serve_owner_only' => env('SERVE_OWNER_ONLY', true),

    'site_databases_in_mysql' => env('SITE_DATABASES_IN_MYSQL', false),
    
    'api_token_backend_enabled' => env('API_TOKEN_BACKEND_ENABLED', false),

    'is_off_network_node' => env('IS_OFF_NETWORK_NODE', false),

    'dev_is_central_dev_server' => env('DEV_IS_CENTRAL_DEV_SERVER', false),
    
    'dev_api_token' => env('DEV_API_TOKEN', false),

];
