<?php
require_once '../core/Database.php';
require_once '../exceptions/AppException.php';

class UsuarioController
{
    public function login($email, $senha)
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($senha, $user['senha'])) {
                session_start();
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['usuario_email'] = $user['email'];
                header('Location: ../views/painel.php');
                exit;
            } else {
                throw new AppException("Credenciais inválidas.");
            }
        } catch (AppException $e) {
            echo "Erro: " . $e->getMessage();
        }
    }

    public function registrar($email, $senha)
    {
        try {
            $db = Database::getInstance()->getConnection();

            // Verifica se já existe o e-mail
            $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                throw new AppException("E-mail já cadastrado.");
            }

            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO usuarios (email, senha) VALUES (?, ?)");
            $stmt->execute([$email, $hash]);

            header('Location: ../views/login.php');
            exit;
        } catch (AppException $e) {
            echo "Erro ao registrar: " . $e->getMessage();
        }
    }
}

// Roteamento simples
$action = $_GET['action'] ?? null;
$controller = new UsuarioController();

switch ($action) {
    case 'login':
        $controller->login($_POST['email'], $_POST['senha']);
        break;
    case 'registrar':
        if ($_POST['email'] !== $_POST['email_confirm'] || $_POST['senha'] !== $_POST['senha_confirm']) {
            echo "Erro: confirmação de e-mail ou senha inválida.";
            exit;
        }
        $controller->registrar($_POST['email'], $_POST['senha']);
        break;
    default:
        echo "Ação não reconhecida.";
        break;
}
