<?php

use Cake\Cache\Engine\FileEngine;
use Cake\Cache\Engine\RedisEngine;
use Cake\Database\Connection;
use Cake\Log\Engine\FileLog;
use Cake\Mailer\Transport\MailTransport;
use Cake\Database\Driver\Postgres;

use function Cake\Core\env;

/*
 * Cache de vida curta compartilhado entre instâncias: contadores do rate limit e códigos de uso
 * único do login social. Com REDIS_URL definido (ex.: redis://:senha@redis:6379/0) usa Redis, que
 * incrementa de forma atômica e vale para todas as instâncias da API. Sem REDIS_URL cai no
 * FileEngine, suficiente para desenvolvimento com um único processo.
 */
$redis = env('REDIS_URL') ? parse_url((string)env('REDIS_URL')) : null;
$cacheVolatil = static function (string $prefix, string $duration) use ($redis): array {
    if ($redis) {
        return [
            'className' => RedisEngine::class,
            'host' => $redis['host'] ?? '127.0.0.1',
            'port' => $redis['port'] ?? 6379,
            'password' => isset($redis['pass']) ? urldecode($redis['pass']) : false,
            'database' => (int)ltrim($redis['path'] ?? '/0', '/'),
            'tls' => ($redis['scheme'] ?? 'redis') === 'rediss',
            'prefix' => $prefix,
            'duration' => $duration,
        ];
    }

    // Temp do sistema: dentro do OneDrive o is_writable() falha para tmp/cache e o FileEngine
    // se recusa a gravar.
    return [
        'className' => FileEngine::class,
        'path' => sys_get_temp_dir() . DS,
        'prefix' => $prefix,
        'duration' => $duration,
    ];
};

return [
    /*
     * Debug Level:
     * Production Mode: false
     * Development Mode: true
     */
    'debug' => filter_var(env('DEBUG'), FILTER_VALIDATE_BOOLEAN),

    /*
     * Configure basic information about the application.
     */
    'App' => [
        'namespace' => 'App',
        'encoding' => env('APP_ENCODING'),
        'defaultLocale' => env('APP_DEFAULT_LOCALE'),
        'defaultTimezone' => env('APP_DEFAULT_TIMEZONE'),
        'base' => false,
        'dir' => 'src',
        'webroot' => 'webroot',
        'wwwRoot' => WWW_ROOT,
        'fullBaseUrl' => false,
        'imageBaseUrl' => 'img/',
        'cssBaseUrl' => 'css/',
        'jsBaseUrl' => 'js/',
        'paths' => [
            'plugins' => [ROOT . DS . 'plugins' . DS],
            'templates' => [dirname(__DIR__) . DS . 'templates' . DS],
            'locales' => [RESOURCES . 'locales' . DS],
        ],
    ],

    /*
     * Security and encryption configuration
     */
    'Security' => [
        'salt' => env('SECURITY_SALT'),
    ],

    /*
     * JWT CONFIGURATION
     */
    'Jwt' => [
        'secret' => env('JWT_SECRET'),
        'algorithm' => 'HS256',
        'expires' => 3600, // 1 hora em segundos
        'refresh_expires' => 604800, // 7 dias
    ],

    /*
     * Asset timestamps
     */
    'Asset' => [
        //'timestamp' => true,
    ],

    /*
     * Cache adapters - MODIFICADO para usar Array em desenvolvimento
     */
    'Cache' => [
        'default' => [
            'className' => FileEngine::class,
            'path' => CACHE,
            'url' => env('CACHE_DEFAULT_URL'),
        ],
        // Contadores do RateLimitMiddleware (janela de 60s)
        'rate_limit' => $cacheVolatil('beready_rl_', '+2 minutes'),
        // Códigos de uso único do login social (trocados em /auth/social/exchange)
        'social_login' => $cacheVolatil('beready_sl_', '+60 seconds'),
        // Gerações por IA de cada usuário no dia (GeracaoService)
        'ia_uso' => $cacheVolatil('beready_ia_', '+1 day'),
        '_cake_core_' => [
            'className' => 'Array',
            'prefix' => 'myapp_cake_core_',
            'serialize' => true,
            'duration' => '+1 years',
        ],
        '_cake_model_' => [
            'className' => 'Array',
            'prefix' => 'myapp_cake_model_',
            'serialize' => true,
            'duration' => '+1 years',
        ],
        '_cake_translations_' => [
            'className' => 'Array',
            'prefix' => 'myapp_cake_translations_',
            'serialize' => true,
            'duration' => '+1 years',
        ],
    ],

    /*
     * Error and Exception handlers
     */
    'Error' => [
        'errorLevel' => E_ALL & ~E_WARNING & ~E_USER_WARNING & ~E_NOTICE & ~E_DEPRECATED,
        'skipLog' => [],
        'log' => true,
        'trace' => true,
        'ignoredDeprecationPaths' => [],
    ],

    /*
     * Debugger configuration
     */
    'Debugger' => [
        'editor' => 'phpstorm',
    ],

    /*
    * Email configuration
    */
    'EmailTransport' => [
        'default' => [
            'className' => 'Smtp',
            'host' => env('EMAIL_HOST'),
            'port' => env('EMAIL_PORT'),
            'username' => env('EMAIL_USERNAME'),
            'password' => env('EMAIL_PASSWORD'),
            'tls' => filter_var(env('EMAIL_TLS'), FILTER_VALIDATE_BOOLEAN),
            'timeout' => 30,
        ],
    ],

    'Email' => [
        'default' => [
            'transport' => 'default',
            'from' => [env('EMAIL_FROM') => env('EMAIL_FROM_NAME')],
            'charset' => env('APP_ENCODING'),
            'headerCharset' => env('APP_ENCODING'),
        ],
    ],

    /*
     * Database connections
     */
    'Datasources' => [
        'default' => [
            'className' => Connection::class,
            'driver' => Postgres::class,
            'url' => env('DATABASE_URL'),
            'encoding' => 'utf8',
            'timezone' => 'UTC',
            'cacheMetadata' => true,
            'quoteIdentifiers' => false,
        ],

        // Banco usado pelo PHPUnit; o schema é recriado a partir das migrations (tests/bootstrap.php)
        'test' => [
            'className' => Connection::class,
            'driver' => Postgres::class,
            'url' => env('TEST_DATABASE_URL'),
            'encoding' => 'utf8',
            'timezone' => 'UTC',
            'cacheMetadata' => true,
            'quoteIdentifiers' => false,
            'log' => false,
        ],
    ],

    /*
     * Logging configuration
     */
    'Log' => [
        'debug' => [
            'className' => FileLog::class,
            'path' => LOGS,
            'file' => 'debug',
            'url' => env('LOG_DEBUG_URL'),
            'scopes' => null,
            'levels' => ['notice', 'info', 'debug'],
        ],
        'error' => [
            'className' => FileLog::class,
            'path' => LOGS,
            'file' => 'error',
            'url' => env('LOG_ERROR_URL'),
            'scopes' => null,
            'levels' => ['warning', 'error', 'critical', 'alert', 'emergency'],
        ],
        'queries' => [
            'className' => FileLog::class,
            'path' => LOGS,
            'file' => 'queries',
            'url' => env('LOG_QUERIES_URL'),
            'scopes' => ['cake.database.queries'],
        ],
    ],

    /*
     * Session configuration
     */
    'Session' => [
        'defaults' => 'php',
    ],

    /*
     * DebugKit configuration
     */
    'DebugKit' => [
        'forceEnable' => false,
        'safeTld' => env('DEBUG_KIT_SAFE_TLD'),
        'ignoreAuthorization' => env('DEBUG_KIT_IGNORE_AUTHORIZATION'),
    ],

    'TestSuite' => [
        'errorLevel' => null,
        'fixtureStrategy' => null,
    ],
];
