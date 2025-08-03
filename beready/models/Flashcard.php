<?php
require_once './config/db.php';

function obterFlashcards($usuario_id = null) {
    global $pdo;
    if ($usuario_id) {
        $stmt = $pdo->prepare("SELECT * FROM flashcards WHERE usuario_id = ?");
        $stmt->execute([$usuario_id]);
    } else {
        $stmt = $pdo->query("SELECT * FROM flashcards");
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function salvarFlashcard($dados) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO flashcards (usuario_id, tipo_origem, conteudo, traducao, palavras_rel, audio_url, imagem_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $dados['usuario_id'],
        $dados['tipo_origem'],
        $dados['conteudo'],
        $dados['traducao'],
        $dados['palavras_rel'] ?? '',
        $dados['audio_url'] ?? '',
        $dados['imagem_url'] ?? ''
    ]);
    return ['status' => 'ok', 'id' => $pdo->lastInsertId()];
}

function removerFlashcard($id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM flashcards WHERE id = ?");
    $stmt->execute([$id]);
    return ['status' => 'ok', 'mensagem' => 'Flashcard removido'];
}
?>
