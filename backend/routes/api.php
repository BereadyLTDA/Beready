<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminPermissionsController;
use App\Http\Controllers\AdminPlanosController;
use App\Http\Controllers\AdminRolesController;
use App\Http\Controllers\AssinaturasController;
use App\Http\Controllers\FlashcardsController;
use App\Http\Controllers\FlashcardTagsController;
use App\Http\Controllers\FrasesSemelhantesController;
use App\Http\Controllers\ImagensGeradasController;
use App\Http\Controllers\PaymentsController;
use App\Http\Controllers\PlanosController;
use App\Http\Controllers\PreferenciasController;
use App\Http\Controllers\ProgressoController;
use App\Http\Controllers\PromptsController;
use App\Http\Controllers\PronunciaController;
use App\Http\Controllers\QuizesController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\TagsController;
use App\Http\Controllers\TraducoesController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

/*
 * Mesmas URLs da versão em CakePHP. Leitura, edição e exclusão dos recursos aceitam a forma REST
 * (/recurso/{id}) e a forma legada com o verbo no path (/recurso/view|edit|delete/{id}), ainda
 * usada pelo frontend.
 *
 * Todas as rotas passam pelo LimitaRequisicoes (grupo "api", ver bootstrap/app.php).
 */

$id = '[0-9]+';
$uuid = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';

/**
 * CRUD padrão de um recurso, com as duas formas de path.
 */
$crud = function (string $base, string $controller, bool $listagem = true) use ($id): void {
    if ($listagem) {
        Route::get($base, [$controller, 'index']);
    }
    Route::post($base, [$controller, 'add']);
    Route::get("{$base}/view/{id}", [$controller, 'view'])->where('id', $id);
    Route::get("{$base}/{id}", [$controller, 'view'])->where('id', $id);
    Route::put("{$base}/edit/{id}", [$controller, 'edit'])->where('id', $id);
    Route::put("{$base}/{id}", [$controller, 'edit'])->where('id', $id);
    Route::delete("{$base}/delete/{id}", [$controller, 'delete'])->where('id', $id);
    Route::delete("{$base}/{id}", [$controller, 'delete'])->where('id', $id);
};

// ------------------------------------------------------------------ públicas

Route::get('/', [UsersController::class, 'health']);

Route::post('/auth/register', [UsersController::class, 'register']);
Route::post('/auth/login', [UsersController::class, 'login']);
Route::post('/auth/logout', [UsersController::class, 'logout']);
Route::post('/auth/refresh', [UsersController::class, 'refresh']);
Route::post('/auth/forgot-password', [UsersController::class, 'forgotPassword']);
Route::post('/auth/verify-email', [UsersController::class, 'verifyEmail']);
Route::post('/auth/resend-verification', [UsersController::class, 'resendVerification']);
Route::post('/auth/reset-password/{token}', [UsersController::class, 'resetPassword'])->where('token', '[a-f0-9]{64}');

// Login social: login/{provider} redireciona ao provedor; o callback gera um código de uso único
// que o frontend troca por tokens em /auth/social/exchange.
Route::get('/auth/login/{provider}', [SocialAuthController::class, 'login'])->where('provider', 'google|facebook|linkedin');
Route::get('/social-auth/callback/{provider}', [SocialAuthController::class, 'callback'])->where('provider', 'google|facebook|linkedin');
Route::post('/auth/social/exchange', [UsersController::class, 'socialExchange']);

// Webhook do Stripe: a autenticidade vem da assinatura Stripe-Signature
Route::post('/payments/webhook', [PaymentsController::class, 'webhook']);

Route::get('/planos', [PlanosController::class, 'index']);

// ------------------------------------------------------------------ autenticadas

Route::middleware('jwt')->group(function () use ($id, $uuid, $crud) {

    // Admin (role admin no banco)
    Route::middleware('admin')->prefix('admin')->group(function () use ($id) {
        Route::get('/users', [AdminController::class, 'users']);
        Route::post('/users/role', [AdminController::class, 'updateRole']);
        Route::get('/stats', [AdminController::class, 'stats']);

        Route::get('/roles', [AdminRolesController::class, 'index']);
        Route::post('/roles', [AdminRolesController::class, 'add']);
        Route::put('/roles/{id}', [AdminRolesController::class, 'edit'])->where('id', $id);
        Route::delete('/roles/{id}', [AdminRolesController::class, 'delete'])->where('id', $id);

        Route::get('/permissions', [AdminPermissionsController::class, 'index']);

        Route::get('/planos', [AdminPlanosController::class, 'index']);
        Route::post('/planos', [AdminPlanosController::class, 'add']);
        Route::match(['PUT', 'POST'], '/planos/edit/{id}', [AdminPlanosController::class, 'edit'])->where('id', $id);
        Route::match(['DELETE', 'POST'], '/planos/delete/{id}', [AdminPlanosController::class, 'delete'])->where('id', $id);
    });

    // Planos e assinatura
    Route::post('/planos/{id}/assinar', [PlanosController::class, 'assinar'])->where('id', $id);
    Route::post('/planos/cancelar', [PlanosController::class, 'cancelar']);
    Route::post('/planos/portal', [PlanosController::class, 'portal']);
    Route::get('/user/assinatura', [AssinaturasController::class, 'current']);
    Route::get('/user/permissions', [UsersController::class, 'permissions']);

    // Usuários (UUID primeiro, ID como fallback)
    Route::get('/users/me', [UsersController::class, 'me']);
    Route::get('/users/{uuid}', [UsersController::class, 'viewByUuid'])->where('uuid', $uuid);
    Route::get('/users/view/{id}', [UsersController::class, 'view'])->where('id', $id);
    Route::get('/users/{id}', [UsersController::class, 'view'])->where('id', $id);
    Route::match(['PUT', 'POST'], '/users/update/{id}', [UsersController::class, 'update'])->where('id', $id);
    Route::delete('/users/delete/{id}', [UsersController::class, 'delete'])->where('id', $id);
    Route::post('/upload/profile-photo', [UploadController::class, 'profilePhoto']);

    // Flashcards (a criação respeita o limite do plano)
    Route::get('/flashcards/revisao', [FlashcardsController::class, 'devidos']);
    Route::post('/flashcards', [FlashcardsController::class, 'add'])->middleware('limite.plano:flashcards');
    Route::get('/flashcards', [FlashcardsController::class, 'index']);
    Route::get('/flashcards/{uuid}', [FlashcardsController::class, 'viewByUuid'])->where('uuid', $uuid);
    Route::get('/flashcards/view/{id}', [FlashcardsController::class, 'view'])->where('id', $id);
    Route::get('/flashcards/{id}', [FlashcardsController::class, 'view'])->where('id', $id);
    Route::put('/flashcards/edit/{id}', [FlashcardsController::class, 'edit'])->where('id', $id);
    Route::put('/flashcards/{id}', [FlashcardsController::class, 'edit'])->where('id', $id);
    Route::delete('/flashcards/delete/{id}', [FlashcardsController::class, 'delete'])->where('id', $id);
    Route::delete('/flashcards/{id}', [FlashcardsController::class, 'delete'])->where('id', $id);
    // Repetição espaçada: registro da avaliação do estudo
    Route::post('/flashcards/{id}/revisao', [FlashcardsController::class, 'revisao'])->where('id', $id);

    Route::get('/flashcard-tags/flashcard/{flashcardId}', [FlashcardTagsController::class, 'getByFlashcard'])->where('flashcardId', $id);
    Route::post('/flashcard-tags', [FlashcardTagsController::class, 'add']);
    Route::delete('/flashcard-tags', [FlashcardTagsController::class, 'remove']);

    // Quizes (criar e gerar respeitam o limite do plano)
    Route::post('/quizes/gerar', [QuizesController::class, 'gerar'])->middleware('limite.plano:quizes');
    Route::post('/quizes', [QuizesController::class, 'add'])->middleware('limite.plano:quizes');
    Route::get('/quizes', [QuizesController::class, 'index']);
    Route::get('/quizes/view/{id}', [QuizesController::class, 'view'])->where('id', $id);
    Route::get('/quizes/{id}', [QuizesController::class, 'view'])->where('id', $id);
    Route::put('/quizes/edit/{id}', [QuizesController::class, 'edit'])->where('id', $id);
    Route::put('/quizes/{id}', [QuizesController::class, 'edit'])->where('id', $id);
    Route::delete('/quizes/delete/{id}', [QuizesController::class, 'delete'])->where('id', $id);
    Route::delete('/quizes/{id}', [QuizesController::class, 'delete'])->where('id', $id);
    // Questões (dono)
    Route::get('/quizes/{id}/questoes', [QuizesController::class, 'questoes'])->where('id', $id);
    Route::post('/quizes/{id}/questoes', [QuizesController::class, 'addQuestao'])->where('id', $id);
    Route::put('/quizes/{id}/questoes', [QuizesController::class, 'substituirQuestoes'])->where('id', $id);
    Route::put('/quizes/{id}/questoes/{questaoId}', [QuizesController::class, 'editQuestao'])->where(['id' => $id, 'questaoId' => $id]);
    Route::delete('/quizes/{id}/questoes/{questaoId}', [QuizesController::class, 'deleteQuestao'])->where(['id' => $id, 'questaoId' => $id]);
    // Jogar (dono ou quiz público): correção sempre no servidor
    Route::post('/quizes/{id}/questoes/{questaoId}/verificar', [QuizesController::class, 'verificar'])->where(['id' => $id, 'questaoId' => $id]);
    Route::post('/quizes/{id}/finalizar', [QuizesController::class, 'finalizar'])->where('id', $id);

    // Tags
    Route::post('/tags', [TagsController::class, 'add'])->middleware('limite.plano:tags');
    Route::get('/tags', [TagsController::class, 'index']);
    Route::get('/tags/view/{id}', [TagsController::class, 'view'])->where('id', $id);
    Route::get('/tags/{id}', [TagsController::class, 'view'])->where('id', $id);
    Route::put('/tags/edit/{id}', [TagsController::class, 'edit'])->where('id', $id);
    Route::put('/tags/{id}', [TagsController::class, 'edit'])->where('id', $id);
    Route::delete('/tags/delete/{id}', [TagsController::class, 'delete'])->where('id', $id);
    Route::delete('/tags/{id}', [TagsController::class, 'delete'])->where('id', $id);
    Route::get('/tags/usuario/{usuarioId}', [TagsController::class, 'getByUsuario'])->where('usuarioId', $id);

    // Preferências, progresso e respostas
    Route::get('/preferencias/usuario/{usuarioId}', [PreferenciasController::class, 'getByUsuario'])->where('usuarioId', $id);
    Route::post('/preferencias', [PreferenciasController::class, 'save']);

    Route::get('/progresso/usuario/{usuarioId}', [ProgressoController::class, 'getByUsuario'])->where('usuarioId', $id);
    Route::post('/progresso', [ProgressoController::class, 'save']);
    Route::post('/progresso/incrementar-tempo', [ProgressoController::class, 'incrementarTempo']);

    // Pronúncia (exercício "Repita a frase")
    Route::get('/pronuncia/frases', [PronunciaController::class, 'frases']);
    Route::post('/pronuncia/tentativas', [PronunciaController::class, 'registrar']);
    Route::get('/pronuncia/resumo', [PronunciaController::class, 'resumo']);

    // Prompts e o que deriva deles (sem listagem geral: sempre por prompt)
    Route::post('/prompts', [PromptsController::class, 'add'])->middleware('limite.plano:prompts');
    Route::get('/prompts', [PromptsController::class, 'index']);
    Route::get('/prompts/view/{id}', [PromptsController::class, 'view'])->where('id', $id);
    Route::get('/prompts/{id}', [PromptsController::class, 'view'])->where('id', $id);
    Route::put('/prompts/edit/{id}', [PromptsController::class, 'edit'])->where('id', $id);
    Route::put('/prompts/{id}', [PromptsController::class, 'edit'])->where('id', $id);
    Route::delete('/prompts/delete/{id}', [PromptsController::class, 'delete'])->where('id', $id);
    Route::delete('/prompts/{id}', [PromptsController::class, 'delete'])->where('id', $id);
    Route::get('/prompts/usuario/{usuarioId}', [PromptsController::class, 'getByUsuario'])->where('usuarioId', $id);

    // Geração por IA a partir do texto do prompt: cria a tradução / as frases
    Route::post('/prompts/{id}/traducoes/gerar', [TraducoesController::class, 'gerar'])->where('id', $id);
    Route::post('/prompts/{id}/frases/gerar', [FrasesSemelhantesController::class, 'gerar'])->where('id', $id);

    $crud('/traducoes', TraducoesController::class, listagem: false);
    Route::get('/traducoes/prompt/{promptId}', [TraducoesController::class, 'getByPrompt'])->where('promptId', $id);

    $crud('/imagens', ImagensGeradasController::class, listagem: false);
    Route::get('/imagens/prompt/{promptId}', [ImagensGeradasController::class, 'getByPrompt'])->where('promptId', $id);

    $crud('/frases', FrasesSemelhantesController::class, listagem: false);
    Route::get('/frases/prompt/{promptId}', [FrasesSemelhantesController::class, 'getByPrompt'])->where('promptId', $id);

    // Path desconhecido: com token, 404; sem token, o AutenticaJwt responde 401 antes (como no CakePHP)
    Route::fallback([UsersController::class, 'notFound']);
});
