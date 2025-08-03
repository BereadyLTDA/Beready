<?php
require_once './config/db.php';

function salvarHistorico($dados) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO historico_pesquisa (usuario_id, termo) VALUES (?, ?)");
    $stmt->execute([$dados['usuario_id'], $dados['termo']]);
    return ['status' => 'ok'];
}

function obterHistorico($usuario_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM historico_pesquisa WHERE usuario_id = ? ORDER BY data_busca DESC");
    $stmt->execute([$usuario_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
