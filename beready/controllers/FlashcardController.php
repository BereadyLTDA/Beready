<?php
require_once '../core/Database.php';
require_once '../exceptions/AppException.php';
require_once './models/Flashcard.php';

class FlashcardController
{
    public function listarFlashcards()
    {
        $usuario_id = $_GET['usuario_id'] ?? null;
        echo json_encode(obterFlashcards($usuario_id));
    }

    public function criarFlashcard()
    {
        $dados = json_decode(file_get_contents("php://input"), true);
        echo json_encode(salvarFlashcard($dados));
    }

    public function deletarFlashcard()
    {
        $dados = json_decode(file_get_contents("php://input"), true);
        echo json_encode(removerFlashcard($dados['id']));
    }
}
