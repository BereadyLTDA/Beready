<?php

/*
 * CORS da API (mesmas regras da versão em CakePHP): só as origens de CORS_ALLOWED_ORIGINS recebem
 * Access-Control-Allow-Origin, sempre com credenciais (o refresh token vai em cookie).
 */
return [

    'paths' => ['*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'X-CSRF-Token', 'X-Requested-With', 'Accept', 'Cache-Control'],

    'exposed_headers' => ['Authorization', 'X-CSRF-Token'],

    'max_age' => 86400,

    'supports_credentials' => true,

];
