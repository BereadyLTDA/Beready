<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bem-vindo ao BeReady!</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f6f9fc;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #d81b60, #c2185b);
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            color: #fff;
            font-size: 28px;
            margin: 0;
        }
        .header p {
            color: rgba(255,255,255,0.9);
            font-size: 16px;
            margin: 10px 0 0;
        }
        .content {
            padding: 40px 30px;
        }
        .content h2 {
            color: #333;
            font-size: 22px;
            margin-top: 0;
        }
        .content p {
            color: #555;
            font-size: 16px;
            line-height: 1.6;
            margin: 16px 0;
        }
        .button {
            display: inline-block;
            background: linear-gradient(135deg, #d81b60, #c2185b);
            color: #fff !important;
            padding: 14px 40px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            margin: 20px 0;
        }
        .features {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin: 24px 0;
        }
        .feature-item {
            background: #f8f9fa;
            padding: 16px;
            border-radius: 8px;
            text-align: center;
        }
        .feature-item .icon {
            font-size: 24px;
        }
        .feature-item h4 {
            margin: 8px 0 4px;
            font-size: 14px;
            color: #333;
        }
        .feature-item p {
            margin: 0;
            font-size: 12px;
            color: #888;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }
        .footer p {
            color: #888;
            font-size: 13px;
            margin: 5px 0;
        }
        @media (max-width: 480px) {
            .features {
                grid-template-columns: 1fr;
            }
            .content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>BeReady</h1>
            <p>Sua jornada de aprendizado de inglês começa aqui!</p>
        </div>

        <!-- Content -->
        <div class="content">
            <h2>Olá, {{ $nome }}!</h2>

            <p>Que bom ter você conosco! O <strong>BeReady</strong> vai te ajudar a aprender inglês de forma inteligente e divertida.</p>

            <p>Estamos muito animados para te acompanhar nessa jornada. </p>

            <div style="text-align: center;">
                <a href="{{ $loginLink }}" class="button">Começar Agora</a>
            </div>

            <div class="features">
                <div class="feature-item">
                    <h4>Flashcards</h4>
                    <p>Aprenda com cartões interativos</p>
                </div>
                <div class="feature-item">
                    <h4>Quizzes</h4>
                    <p>Teste seus conhecimentos</p>
                </div>
                <div class="feature-item">
                    <h4>Prompts com IA</h4>
                    <p>Pratique conversação</p>
                </div>
                <div class="feature-item">
                    <h4>Progresso</h4>
                    <p>Acompanhe sua evolução</p>
                </div>
            </div>

            <p style="font-size: 14px; color: #888;">
                Dica: Comece pelos flashcards para criar uma base sólida!
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>BeReady</strong> - Aprenda de forma inteligente</p>
            <p style="font-size: 11px; color: #aaa;">
                Este e-mail foi enviado automaticamente. Não responda.<br>
                © {{ $ano }} BeReady. Todos os direitos reservados.
            </p>
        </div>
    </div>
</body>
</html>