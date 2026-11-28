<?php

/*
 * Configuração própria do BeReady. Os nomes das variáveis de ambiente são os mesmos da versão
 * em CakePHP, para a troca de backend não exigir um .env novo.
 *
 * O código lê sempre config('beready.*'), nunca env() direto: com `php artisan config:cache`
 * (produção) o env() fora dos arquivos de config devolve null.
 */
return [

    // URL do frontend: destino dos redirecionamentos do login social e links dos e-mails
    'frontend_url' => rtrim((string) env('APP_BASE_URL', 'http://localhost:5173/'), '/').'/',

    // Versão vigente dos termos de uso e da política de privacidade (gravada no aceite do cadastro).
    // Ao mudar o texto das páginas /termos e /privacidade, atualize esta data.
    'termos_versao' => '2026-11-20',

    'jwt' => [
        'secret' => env('JWT_SECRET'),
        'algorithm' => 'HS256',
        'expires' => 3600,            // access token: 1 hora
        'refresh_expires' => 604800,  // refresh token: 7 dias
    ],

    // Cookie httpOnly do refresh token (Path=/auth)
    'refresh_cookie' => [
        'samesite' => env('REFRESH_COOKIE_SAMESITE', 'Lax'),
        'secure' => filter_var(env('REFRESH_COOKIE_SECURE', false), FILTER_VALIDATE_BOOLEAN),
    ],

    'cors_allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')),
    ))),

    // Requisições por minuto por IP; as rotas de autenticação têm um limite próprio, menor
    'rate_limit' => [
        'geral' => 300,
        'autenticacao' => 10,
        'janela_segundos' => 60,
    ],

    'ia' => [
        'anthropic_api_key' => env('ANTHROPIC_API_KEY'),
        'modelo' => env('ANTHROPIC_MODEL') ?: 'claude-opus-5-5',
        'limite_diario' => (int) (env('IA_LIMITE_DIARIO') ?: 50),
    ],

    'nivelamento' => [
        // "0" é válido (refazer quando quiser), por isso não usa ?: como os outros
        'dias_para_refazer' => is_numeric(env('NIVELAMENTO_DIAS_PARA_REFAZER')) ? (int) env('NIVELAMENTO_DIAS_PARA_REFAZER') : 30,
    ],

    'recaptcha' => [
        'secret' => env('RECAPTCHA_SECRET_KEY'),
        'verify_url' => env('RECAPTCHA_VERIFY_URL', 'https://www.google.com/recaptcha/api/siteverify'),
    ],

    'cloudinary' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
        'api_url' => env('CLOUDINARY_API_URL', 'https://api.cloudinary.com/v1_1'),
        'upload_folder' => env('CLOUDINARY_UPLOAD_FOLDER', 'beready/profile'),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
];
