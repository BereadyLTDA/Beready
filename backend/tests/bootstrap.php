<?php

/*
 * Os testes usam um Postgres descartável (TEST_DATABASE_URL): o RefreshDatabase recria o esquema
 * a cada execução. Sem a variável, nada roda, para nunca apontar para o banco real por engano.
 */

require __DIR__.'/../vendor/autoload.php';

$url = getenv('TEST_DATABASE_URL') ?: ($_ENV['TEST_DATABASE_URL'] ?? $_SERVER['TEST_DATABASE_URL'] ?? null);
if (! $url && is_file(__DIR__.'/../.env')) {
    $env = Dotenv\Dotenv::parse((string) file_get_contents(__DIR__.'/../.env'));
    $url = $env['TEST_DATABASE_URL'] ?? null;
}
if (! $url) {
    fwrite(STDERR, "Defina TEST_DATABASE_URL (Postgres descartável dos testes) no ambiente ou no .env.\n");
    exit(1);
}

foreach (['DATABASE_URL' => $url, 'DB_URL' => $url] as $chave => $valor) {
    putenv("{$chave}={$valor}");
    $_ENV[$chave] = $_SERVER[$chave] = $valor;
}
