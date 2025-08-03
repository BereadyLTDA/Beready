<?php
require_once './config/db.php';

function obterTags() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM tags");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function salvarTag($dados) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO tags (nome) VALUES (?)");
    $stmt->execute([$dados['nome']]);
    return ['status' => 'ok', 'id' => $pdo->lastInsertId()];
}
?>
