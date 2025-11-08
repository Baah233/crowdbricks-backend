<?php
return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout', 'storage/*'],

    'allowed_methods' => ['*'],

  'allowed_origins' => [
    'http://localhost:5173',
    'http://crowdbricks-frontend.test',
    // Production origins (uncomment when deploying)
    // 'https://crowdbricks.io',
    // 'https://app.crowdbricks.io',
    // 'https://www.crowdbricks.io',
],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
