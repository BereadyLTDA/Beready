<?php
require_once './config/db.php';

function salvarProgresso($dados) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO progresso_usuario (usuario_id, tipo_conteudo, conteudo_id, status)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE status = VALUES(status), ultima_vez = CURRENT_TIMESTAMP");
    $stmt->execute([
        $dados['usuario_id'],
        $dados['tipo_conteudo'],
        $dados['conteudo_id'],
        $dados['status']
    ]);
    return ['status' => 'ok'];
}

function obterProgresso($usuario_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM progresso_usuario WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
