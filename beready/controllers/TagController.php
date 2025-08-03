<?php
require_once './models/Tag.php';

function listarTags() {
    echo json_encode(obterTags());
}

function criarTag() {
    $dados = json_decode(file_get_contents("php://input"), true);
    echo json_encode(salvarTag($dados));
}
?>
