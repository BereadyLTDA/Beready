<?php
require_once './models/Usuario.php';

function listarUsuarios() {
    echo json_encode(obterTodosUsuarios());
}

function criarUsuario() {
    $dados = json_decode(file_get_contents("php://input"), true);
    echo json_encode(salvarUsuario($dados));
}

function login() {
    $dados = json_decode(file_get_contents("php://input"), true);
    echo json_encode(autenticarUsuario($dados));
}
?>
