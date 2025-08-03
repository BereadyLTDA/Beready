<?php
require_once './models/Progresso.php';

function marcarProgresso() {
    $dados = json_decode(file_get_contents("php://input"), true);
    echo json_encode(salvarProgresso($dados));
}

function listarProgresso() {
    $usuario_id = $_GET['usuario_id'] ?? null;
    echo json_encode(obterProgresso($usuario_id));
}
?>
