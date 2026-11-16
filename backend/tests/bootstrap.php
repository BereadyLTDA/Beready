<?php

declare(strict_types=1);

/**
 * Test runner bootstrap.
 *
 * Usa o datasource "test" (Postgres em TEST_DATABASE_URL, definido em config/app.php):
 * aplica as migrations, limpa todas as tabelas e roda os seeds de referência
 * (roles, permissões e planos). Os testes apagam/recriam os próprios dados via fixtures.
 */

use Cake\Cache\Cache;
use Cake\Chronos\Chronos;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\ConnectionHelper;
use Migrations\Migrations;
use Migrations\TestSuite\Migrator;
use function Cake\Core\env;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/bootstrap.php';

if (empty($_SERVER['HTTP_HOST']) && !Configure::read('App.fullBaseUrl')) {
    Configure::write('App.fullBaseUrl', 'http://localhost');
}

// Erros provocados pelos testes não devem ir para o Sentry
\Sentry\SentrySdk::init();

$testDatabaseUrl = env('TEST_DATABASE_URL');
if (empty($testDatabaseUrl)) {
    exit("TEST_DATABASE_URL não definida. Configure-a no backend/.env (veja o .env.example).\n");
}

// Garante que nenhum override (ex.: config/app_local.php) apontou o datasource de teste para outro banco
$expected = ConnectionManager::parseDsn($testDatabaseUrl);
$actual = ConnectionManager::getConfig('test') ?? [];
foreach (['host', 'port', 'database'] as $key) {
    if ((string)($expected[$key] ?? '') !== (string)($actual[$key] ?? '')) {
        exit("O datasource 'test' deve usar TEST_DATABASE_URL. Remova overrides de Datasources em config/app_local.php.\n");
    }
}
unset($expected, $actual, $key);

// Rate limit, códigos do login social e uso da IA em memória, isolados do servidor de desenvolvimento
foreach (['rate_limit', 'social_login', 'ia_uso'] as $cacheConfig) {
    Cache::drop($cacheConfig);
    Cache::setConfig($cacheConfig, ['className' => 'Array']);
}
unset($cacheConfig);

Chronos::setTestNow(Chronos::now());
session_id('cli');

ConnectionHelper::addTestAliases();

// Schema a partir de config/Migrations; ao final todas as tabelas (exceto phinxlog) ficam vazias
(new Migrator())->run();

// O Migrator pode ter lido o schema antes de aplicar migrations novas: descarta o cache de metadados
Cache::clear('_cake_model_');

// Dados de referência (roles, permissões, planos): todos os seeds de config/Seeds, na ordem das dependências
(new Migrations())->seed(['connection' => 'test']);
