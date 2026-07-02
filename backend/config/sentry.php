<?php

use function Cake\Core\env;

\Sentry\init([
    'dsn' => env('SENTRY_DSN'),

    'environment' => env('APP_ENV', 'development'),

    'traces_sample_rate' => 1.0,

    'send_default_pii' => false,

]);