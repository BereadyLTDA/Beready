<?php
return [
    'EmailTransport' => [
        'default' => [
            'className' => 'Debug',
        ],
    ],
    'Email' => [
        'default' => [
            'transport' => 'default',
            'from' => ['noreply@beready.com' => 'BeReady'],
            'charset' => 'utf-8',
            'headerCharset' => 'utf-8',
        ],
    ],
];