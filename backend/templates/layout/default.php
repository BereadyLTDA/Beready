<?php
/**
 * Layout padrão personalizado Beready
 * Substitui o default do CakePHP
 * @var \App\View\AppView $this
 */

// Verifica se há usuário autenticado usando o Auth Component
$authUser = $this->request->getSession()->read('Auth.User');
$isLoggedIn = !empty($authUser);
$username = $isLoggedIn ? ($authUser['nome'] ?? $authUser['email'] ?? 'Usuário') : '';
$userId = $isLoggedIn ? $authUser['id'] : null;

// Define a URL inicial baseada no status de login
$homeUrl = $isLoggedIn ? $this->Url->build(['controller' => 'Users', 'action' => 'index', 'home']) : $this->Url->build('/');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?= $this->Html->charset() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $this->fetch('title') ?: 'Beready - Sistema de Flashcards' ?></title>
    <?= $this->Html->meta('icon') ?>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- CSS customizado -->
    <style>
        :root {
            --primary-color: #d81b60;
            --secondary-color: #6c757d;
            --dark-color: #343a40;
            --light-color: #f8f9fa;
            --border-color: #dee2e6;
        }

        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }

        html, body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            height: 100%; 
            width: 100%;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .main-header {
            background: linear-gradient(135deg, var(--primary-color), #c2185b);
            color: white;
            padding: 1rem 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 100%;
            position: relative;
            z-index: 1000;
            flex-shrink: 0;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
        }

        .logo { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            text-decoration: none;
            transition: transform 0.2s ease;
        }
        .logo:hover {
            transform: scale(1.05);
        }
        .logo-icon { 
            font-size: 1.8rem; 
            color: white; 
        }
        .logo-text { 
            font-size: 1.6rem; 
            font-weight: 700; 
            letter-spacing: -0.5px; 
            color: white;
        }

        .nav-container {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .nav-links { 
            display: flex; 
            gap: 8px; 
            align-items: center;
        }
        
        .nav-links a { 
            color: white; 
            text-decoration: none; 
            font-weight: 500; 
            font-size: 0.95rem;
            transition: all 0.3s ease; 
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .nav-links a:hover { 
            background: rgba(255, 255, 255, 0.2); 
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .nav-links a i {
            font-size: 1rem;
        }

        /* Dropdown Menu */
        .dropdown {
            position: relative;
        }

        .dropdown-toggle {
            cursor: pointer;
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .dropdown-toggle:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 5px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            min-width: 200px;
            overflow: hidden;
            z-index: 1001;
            padding: 8px 0;
        }

        .dropdown:hover .dropdown-menu,
        .dropdown-menu:hover {
            display: block;
            animation: slideDown 0.3s ease;
        }
        
        .dropdown {
            position: relative;
        }
        
        .dropdown::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            height: 10px;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 20px;
            color: var(--dark-color);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            background: white;
            border: none;
            border-radius: 0;
            white-space: nowrap;
        }

        .dropdown-menu a:hover {
            background: #f0f0f0;
            padding-left: 25px;
            transform: none;
            box-shadow: none;
        }

        .dropdown-menu a i {
            font-size: 1rem;
            width: 18px;
            text-align: center;
        }

        .dropdown-menu .divider {
            height: 1px;
            background: var(--border-color);
            margin: 5px 0;
        }

        .user-info {
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.15);
            padding: 10px 16px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            font-weight: 500;
        }

        /* Main content */
        main.main { 
            flex: 1; 
            padding: 20px; 
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Container para garantir que o conteúdo principal ocupe o espaço disponível */
        .page-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Footer */
        .main-footer {
            background: var(--dark-color);
            color: white;
            width: 100%;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1rem;
            flex-shrink: 0;
            margin-top: auto;
        }

        /* Flash Messages Modernas */
        .flash-messages-modern {
            position: fixed;
            top: 100px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
        }

        .flash-message {
            padding: 16px 20px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            text-align: left;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border: none;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideInRight 0.5s ease, fadeOut 0.5s ease 4.5s forwards;
        }

        .flash-message.success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .flash-message.error {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .flash-message.info {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }

        .flash-message .icon {
            font-size: 18px;
            flex-shrink: 0;
        }

        .flash-message .content {
            flex: 1;
        }

        /* Para mensagens flash padrão do CakePHP */
        .message {
            padding: 16px 20px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            text-align: left;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border: none;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideInRight 0.5s ease, fadeOut 0.5s ease 4.5s forwards;
        }

        .message.success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .message.error {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .message.info {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
            }
            to {
                opacity: 0;
            }
        }

        /* Responsive */
        @media (max-width: 992px) {
            .header-content { 
                flex-direction: column; 
                gap: 15px; 
                text-align: center;
            }
            
            .nav-container {
                flex-direction: column;
                gap: 15px;
                width: 100%;
            }
            
            .nav-links { 
                gap: 8px; 
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .dropdown-menu {
                right: auto;
                left: 50%;
                transform: translateX(-50%);
            }
        }

        @media (max-width: 768px) {
            main.main {
                padding: 15px;
            }
            
            .logo-text {
                font-size: 1.4rem;
            }
            
            .logo-icon {
                font-size: 1.6rem;
            }

            .nav-links a {
                font-size: 0.85rem;
                padding: 8px 12px;
            }

            .flash-messages-modern {
                top: 80px;
                right: 10px;
                left: 10px;
                max-width: none;
            }
        }

        @media (max-width: 480px) {
            .main-header {
                padding: 0.8rem 15px;
            }
            
            .nav-links {
                gap: 6px;
            }
            
            .nav-links a {
                font-size: 0.8rem;
                padding: 6px 10px;
                gap: 6px;
            }
            
            .nav-links a i {
                font-size: 0.9rem;
            }
            
            .main-footer {
                padding: 15px;
                font-size: 0.9rem;
                text-align: center;
            }

            .flash-message,
            .message {
                padding: 12px 16px;
                font-size: 13px;
            }

            .user-info {
                font-size: 0.8rem;
                padding: 8px 12px;
            }
        }
    </style>

    <?= $this->fetch('meta') ?>
    <?= $this->fetch('css') ?>
    <?= $this->fetch('script') ?>
</head>
<body>
    <div class="page-container">
        <!-- Header -->
        <header class="main-header">
            <div class="header-content">
                <a href="<?= $homeUrl ?>" class="logo">
                    <i class="fas fa-graduation-cap logo-icon"></i>
                    <span class="logo-text">Beready</span>
                </a>
                
                <?php if ($isLoggedIn): ?>
                    <nav class="nav-container">
                        <div class="nav-links">
                            <a href="<?= $homeUrl ?>">
                                <i class="fas fa-home"></i> Início
                            </a>
                            
                            <a href="<?= $this->Url->build('/flashcards/criar') ?>">
                                <i class="fas fa-plus-circle"></i> Criar Flashcard
                            </a>
                            
                            <a href="<?= $this->Url->build('/flashcards') ?>">
                                <i class="fas fa-layer-group"></i> Flashcards
                            </a>
                            
                            <a href="<?= $this->Url->build('/tags/add') ?>">
                                <i class="fas fa-plus"></i> Criar Tag
                            </a>
                            
                            <a href="<?= $this->Url->build('/tags') ?>">
                                <i class="fas fa-tags"></i> Tags
                            </a>
                        </div>
                        
                        <div class="dropdown">
                            <div class="user-info dropdown-toggle">
                                <i class="fas fa-user-circle"></i> 
                                <?= h($username) ?>
                                <i class="fas fa-chevron-down" style="font-size: 0.7rem; margin-left: 4px;"></i>
                            </div>
                            <div class="dropdown-menu">
                                <?php if ($userId): ?>
                                    <a href="<?= $this->Url->build(['controller' => 'Users', 'action' => 'edit', $userId]) ?>">
                                        <i class="fas fa-user-edit"></i> Editar Perfil
                                    </a>
                                    <div class="divider"></div>
                                <?php endif; ?>
                                <?= $this->Html->link(
                                    '<i class="fas fa-sign-out-alt"></i> Sair',
                                    ['controller' => 'Users', 'action' => 'logout'],
                                    ['class' => '', 'escape' => false]
                                ) ?>
                            </div>
                        </div>
                    </nav>
                <?php else: ?>
                    <nav class="nav-links">
                        <a href="<?= $this->Url->build('/') ?>">
                            <i class="fas fa-home"></i> Início
                        </a>
                        <a href="<?= $this->Url->build('/login') ?>">
                            <i class="fas fa-sign-in-alt"></i> Entrar
                        </a>
                    </nav>
                <?php endif; ?>
            </div>
        </header>

        <!-- Flash Messages Modernas -->
        <div class="flash-messages-modern">
            <?= $this->Flash->render() ?>
        </div>

        <!-- Conteúdo principal -->
        <main class="main">
            <?= $this->fetch('content') ?>
        </main>

        <!-- Footer -->
        <footer class="main-footer">
            &copy; <?= date('Y') ?> Beready - Sistema de Flashcards. Todos os direitos reservados.
        </footer>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Script para melhorar as flash messages -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Adiciona ícones às mensagens flash
        const messages = document.querySelectorAll('.message, .flash-message');
        
        messages.forEach(message => {
            // Determina o tipo da mensagem e adiciona o ícone correspondente
            let iconClass = 'fas fa-info-circle';
            if (message.classList.contains('success')) {
                iconClass = 'fas fa-check-circle';
            } else if (message.classList.contains('error')) {
                iconClass = 'fas fa-exclamation-circle';
            } else if (message.classList.contains('warning')) {
                iconClass = 'fas fa-exclamation-triangle';
            }
            
            // Adiciona o ícone se não existir
            if (!message.querySelector('.icon')) {
                const icon = document.createElement('i');
                icon.className = `icon ${iconClass}`;
                message.insertBefore(icon, message.firstChild);
            }
            
            // Remove a mensagem após a animação
            setTimeout(() => {
                message.style.transition = 'opacity 0.5s ease';
                message.style.opacity = '0';
                setTimeout(() => {
                    if (message.parentNode) {
                        message.parentNode.removeChild(message);
                    }
                }, 500);
            }, 5000);
        });
        
        // Remove o container de mensagens se estiver vazio
        const flashContainer = document.querySelector('.flash-messages-modern');
        if (flashContainer && flashContainer.children.length === 0) {
            setTimeout(() => {
                if (flashContainer.parentNode) {
                    flashContainer.parentNode.removeChild(flashContainer);
                }
            }, 6000);
        }
    });
    </script>
</body>
</html>