<?php
require_once './models/Historico.php';

function registrarBusca() {
    $dados = json_decode(file_get_contents("php://input"), true);
    echo json_encode(salvarHistorico($dados));
}

function listarHistorico() {
    $usuario_id = $_GET['usuario_id'] ?? null;
    echo json_encode(obterHistorico($usuario_id));
}
?>
