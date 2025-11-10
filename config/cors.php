<?php
return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout', 'storage/*'],

    'allowed_methods' => ['*'],

  'allowed_origins' => array_filter(array_merge(
        [
            'http://localhost:5173',
            'http://localhost:3000',
            'http://crowdbricks-frontend.test',
        ],
        explode(',', env('CORS_ALLOWED_ORIGINS', ''))
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
