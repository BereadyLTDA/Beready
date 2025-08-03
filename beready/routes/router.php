<?php
require_once './controllers/UsuarioController.php';
require_once './controllers/FlashcardController.php';
require_once './controllers/ProgressoController.php';
require_once './controllers/TagController.php';
require_once './controllers/HistoricoController.php';

$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

switch ($request) {
    case '/usuarios':
        $controller = new UsuarioController();
        if ($method === 'GET') {
            $controller->listarUsuarios();
        } elseif ($method === 'POST') {
            // supondo criarUsuario com dois parâmetros email, senha
            $controller->criarUsuario($_POST['email'], $_POST['senha']);
        }
        break;

    case '/login':
        $controller = new UsuarioController();
        if ($method === 'POST') {
            $controller->login($_POST['email'], $_POST['senha']);
        }
        break;

    case '/flashcards':
        $controller = new FlashcardController();
        if ($method === 'GET') {
            $controller->listarFlashcards();
        } elseif ($method === 'POST') {
            $controller->criarFlashcard();
        } elseif ($method === 'DELETE') {
            $controller->deletarFlashcard();
        }
        break;

    case '/progresso':
        $controller = new ProgressoController();
        if ($method === 'GET') {
            $controller->listarProgresso();
        } elseif ($method === 'POST') {
            $controller->marcarProgresso();
        }
        break;

    case '/tags':
        $controller = new TagController();
        if ($method === 'GET') {
            $controller->listarTags();
        } elseif ($method === 'POST') {
            $controller->criarTag();
        }
        break;

    case '/historico':
        $controller = new HistoricoController();
        if ($method === 'GET') {
            $controller->listarHistorico();
        } elseif ($method === 'POST') {
            $controller->registrarBusca();
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['erro' => 'Rota não encontrada: ' . $request]);
        break;
}
