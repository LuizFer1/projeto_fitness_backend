<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout', 'register'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://coreva.luizfdev.com.br',
        'https://coreva.luizfdev.com.br',
        'http://localhost:5173',
        'http://localhost:5174',
        'https://localhost:5173',
        'https://localhost:5174',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
