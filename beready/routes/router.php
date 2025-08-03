<?php
$request = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

switch ($request) {
    case '/usuarios':
        require_once './controllers/UsuarioController.php';
        if ($method == 'GET') {
            listarUsuarios();
        } elseif ($method == 'POST') {
            criarUsuario();
        }
        break;

    case '/login':
        require_once './controllers/UsuarioController.php';
        if ($method == 'POST') {
            login();
        }
        break;

    case '/flashcards':
        require_once './controllers/FlashcardController.php';
        if ($method == 'GET') {
            listarFlashcards();
        } elseif ($method == 'POST') {
            criarFlashcard();
        } elseif ($method == 'DELETE') {
            deletarFlashcard();
        }
        break;

    case '/progresso':
        require_once './controllers/ProgressoController.php';
        if ($method == 'GET') {
            listarProgresso();
        } elseif ($method == 'POST') {
            marcarProgresso();
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['erro' => 'Rota não encontrada']);
        break;
}
?>
