<?php
require_once './config/db.php';

function obterTodosUsuarios() {
    global $pdo;
    $stmt = $pdo->query("SELECT id, nome, email, nivel_ingles FROM usuarios");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function salvarUsuario($dados) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, nivel_ingles, tipo_usuario) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $dados['nome'],
        $dados['email'],
        password_hash($dados['senha'], PASSWORD_DEFAULT),
        $dados['nivel_ingles'],
        'aluno'
    ]);
    return ['status' => 'ok', 'id' => $pdo->lastInsertId()];
}

function autenticarUsuario($dados) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$dados['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($dados['senha'], $user['senha'])) {
        return ['status' => 'logado', 'usuario' => $user];
    } else {
        return ['status' => 'erro', 'mensagem' => 'Credenciais inválidas'];
    }
}
?>
