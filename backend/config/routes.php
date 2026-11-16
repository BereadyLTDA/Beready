<?php

declare(strict_types=1);

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

/** @var \Cake\Routing\RouteBuilder $routes */
$routes->setRouteClass(DashedRoute::class);

$id = ['id' => '\d+'];
$uuid = ['uuid' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'];
$questao = ['id' => '\d+', 'questaoId' => '\d+'];
$provider = ['provider' => 'google|facebook|linkedin'];

/**
 * Registra uma rota. $alvo é "Controller::action"; $patterns restringe os parâmetros da URL
 * e $pass lista os que chegam como argumentos da action (os demais são lidos via getParam).
 *
 * @param array<string>|string $metodos
 * @param array<string, string> $patterns
 * @param array<string> $pass
 */
$rota = static function (
    array|string $metodos,
    string $path,
    string $alvo,
    array $patterns = [],
    array $pass = [],
) use ($routes): void {
    [$controller, $action] = explode('::', $alvo);
    $route = $routes->connect($path, ['controller' => $controller, 'action' => $action]);
    if ($patterns) {
        $route->setPatterns($patterns);
    }
    if ($pass) {
        $route->setPass($pass);
    }
    $route->setMethods((array)$metodos);
};

/**
 * CRUD padrão de um recurso. Leitura, edição e exclusão aceitam a forma REST (/recurso/{id})
 * e a forma legada com o verbo no path (/recurso/view|edit|delete/{id}), ainda usada pelo frontend.
 */
$crud = static function (string $base, string $controller, bool $listagem = true) use ($rota, $id): void {
    if ($listagem) {
        $rota('GET', $base, "$controller::index");
    }
    $rota('POST', $base, "$controller::add");
    $rota('GET', "$base/view/{id}", "$controller::view", $id);
    $rota('GET', "$base/{id}", "$controller::view", $id);
    $rota('PUT', "$base/edit/{id}", "$controller::edit", $id);
    $rota('PUT', "$base/{id}", "$controller::edit", $id);
    $rota('DELETE', "$base/delete/{id}", "$controller::delete", $id);
    $rota('DELETE', "$base/{id}", "$controller::delete", $id);
};

// HEALTHCHECK
$rota('GET', '/', 'Users::health');

// ADMIN (escopo protegido pelo AdminMiddleware)
$rota('GET', '/admin/users', 'Admin::users');
$rota('POST', '/admin/users/role', 'Admin::updateRole');
$rota('GET', '/admin/stats', 'Admin::stats');

$rota('GET', '/admin/roles', 'AdminRoles::index');
$rota('POST', '/admin/roles', 'AdminRoles::add');
$rota('PUT', '/admin/roles/{id}', 'AdminRoles::edit', $id, ['id']);
$rota('DELETE', '/admin/roles/{id}', 'AdminRoles::delete', $id, ['id']);

$rota('GET', '/admin/permissions', 'AdminPermissions::index');

$rota('GET', '/admin/planos', 'AdminPlanos::index');
$rota('POST', '/admin/planos', 'AdminPlanos::add');
$rota(['PUT', 'POST'], '/admin/planos/edit/{id}', 'AdminPlanos::edit', $id, ['id']);
$rota(['DELETE', 'POST'], '/admin/planos/delete/{id}', 'AdminPlanos::delete', $id, ['id']);

// PLANOS E ASSINATURA
$rota('GET', '/planos', 'Planos::index');
$rota('POST', '/planos/{id}/assinar', 'Planos::assinar', $id, ['id']);
$rota('POST', '/planos/cancelar', 'Planos::cancelar');
// Stripe Billing Portal
$rota('POST', '/planos/portal', 'Planos::portal');
$rota('GET', '/user/assinatura', 'Assinaturas::current');
$rota('GET', '/user/permissions', 'Users::permissions');
$rota('POST', '/payments/webhook', 'Payments::webhook');

// AUTENTICAÇÃO
$rota('POST', '/auth/register', 'Users::register');
$rota('POST', '/auth/login', 'Users::login');
$rota('POST', '/auth/logout', 'Users::logout');
$rota('POST', '/auth/refresh', 'Users::refresh');
$rota('POST', '/auth/forgot-password', 'Users::forgotPassword');
$rota('POST', '/auth/reset-password/{token}', 'Users::resetPassword', ['token' => '[a-f0-9]{64}'], ['token']);

// Login social: login/{provider} redireciona ao provedor; o callback gera um código de uso único
// que o frontend troca por tokens em /auth/social/exchange.
$rota('GET', '/auth/login/{provider}', 'SocialAuth::login', $provider, ['provider']);
$rota('GET', '/social-auth/callback/{provider}', 'SocialAuth::callback', $provider, ['provider']);
$rota('POST', '/auth/social/exchange', 'Users::socialExchange');

// USUÁRIOS (UUID primeiro, ID como fallback)
$rota('GET', '/users/me', 'Users::me');
$rota('GET', '/users/{uuid}', 'Users::viewByUuid', $uuid);
$rota('GET', '/users/view/{id}', 'Users::view', $id);
$rota('GET', '/users/{id}', 'Users::view', $id);
$rota(['PUT', 'POST'], '/users/update/{id}', 'Users::update', $id);
$rota('DELETE', '/users/delete/{id}', 'Users::delete', $id);
$rota('POST', '/upload/profile-photo', 'Upload::profilePhoto');

// FLASHCARDS
$crud('/flashcards', 'Flashcards');
$rota('GET', '/flashcards/{uuid}', 'Flashcards::viewByUuid', $uuid);
// Repetição espaçada: devidos para revisar e registro da avaliação
$rota('GET', '/flashcards/revisao', 'Flashcards::devidos');
$rota('POST', '/flashcards/{id}/revisao', 'Flashcards::revisao', $id, ['id']);

$rota('GET', '/flashcard-tags/flashcard/{flashcardId}', 'FlashcardTags::getByFlashcard', ['flashcardId' => '\d+']);
$rota('POST', '/flashcard-tags', 'FlashcardTags::add');
$rota('DELETE', '/flashcard-tags', 'FlashcardTags::remove');

// QUIZES
$crud('/quizes', 'Quizes');
// Gera um quiz a partir dos flashcards do usuário (cria um quiz: sujeito ao limite do plano)
$rota('POST', '/quizes/gerar', 'Quizes::gerar');
// Questões (dono)
$rota('GET', '/quizes/{id}/questoes', 'Quizes::questoes', $id, ['id']);
$rota('POST', '/quizes/{id}/questoes', 'Quizes::addQuestao', $id, ['id']);
$rota('PUT', '/quizes/{id}/questoes', 'Quizes::substituirQuestoes', $id, ['id']);
$rota('PUT', '/quizes/{id}/questoes/{questaoId}', 'Quizes::editQuestao', $questao, ['id', 'questaoId']);
$rota('DELETE', '/quizes/{id}/questoes/{questaoId}', 'Quizes::deleteQuestao', $questao, ['id', 'questaoId']);
// Jogar (dono ou quiz público): correção sempre no servidor
$rota('POST', '/quizes/{id}/questoes/{questaoId}/verificar', 'Quizes::verificar', $questao, ['id', 'questaoId']);
$rota('POST', '/quizes/{id}/finalizar', 'Quizes::finalizar', $id, ['id']);

// TAGS
$crud('/tags', 'Tags');
$rota('GET', '/tags/usuario/{usuarioId}', 'Tags::getByUsuario', ['usuarioId' => '\d+']);

// PREFERÊNCIAS, PROGRESSO E RESPOSTAS
$rota('GET', '/preferencias/usuario/{usuarioId}', 'Preferencias::getByUsuario', ['usuarioId' => '\d+']);
$rota('POST', '/preferencias', 'Preferencias::save');

$rota('GET', '/progresso/usuario/{usuarioId}', 'Progresso::getByUsuario', ['usuarioId' => '\d+']);
$rota('POST', '/progresso', 'Progresso::save');
$rota('POST', '/progresso/incrementar-flashcards', 'Progresso::incrementarFlashcards');
$rota('POST', '/progresso/incrementar-tempo', 'Progresso::incrementarTempo');

$rota('POST', '/respostas', 'Respostas::save');

// PROMPTS E O QUE DERIVA DELES (sem listagem geral: sempre por prompt)
$crud('/prompts', 'Prompts');
$rota('GET', '/prompts/usuario/{usuarioId}', 'Prompts::getByUsuario', ['usuarioId' => '\d+']);

// Geração por IA a partir do texto do prompt (GeracaoService): cria a tradução / as frases
$rota('POST', '/prompts/{id}/traducoes/gerar', 'Traducoes::gerar', $id, ['id']);
$rota('POST', '/prompts/{id}/frases/gerar', 'FrasesSemelhantes::gerar', $id, ['id']);

$crud('/traducoes', 'Traducoes', listagem: false);
$rota('GET', '/traducoes/prompt/{promptId}', 'Traducoes::getByPrompt', ['promptId' => '\d+']);

$crud('/imagens', 'ImagensGeradas', listagem: false);
$rota('GET', '/imagens/prompt/{promptId}', 'ImagensGeradas::getByPrompt', ['promptId' => '\d+']);

$crud('/frases', 'FrasesSemelhantes', listagem: false);
$rota('GET', '/frases/prompt/{promptId}', 'FrasesSemelhantes::getByPrompt', ['promptId' => '\d+']);

// FALLBACK (deve ser sempre a última rota)
$routes->connect('/*', ['controller' => 'Users', 'action' => 'notFound']);
