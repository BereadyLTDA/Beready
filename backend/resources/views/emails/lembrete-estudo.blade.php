<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; padding: 30px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 30px; }
        .logo { font-size: 28px; font-weight: bold; color: #667eea; }
        .button { display: inline-block; background: linear-gradient(135deg, #667eea, #764ba2); color: white !important; padding: 12px 24px; text-decoration: none; border-radius: 8px; margin: 20px 0; }
        .footer { margin-top: 30px; font-size: 12px; color: #999; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">BeReady</div>
        </div>
        <h2>Olá, {{ $nome }}!</h2>
        <p>Você tem {{ $cards }} {{ $cards === 1 ? 'card esperando' : 'cards esperando' }} revisão. Alguns minutos hoje mantêm o que você aprendeu fresco na memória.</p>
        <div style="text-align: center;">
            <a href="{{ $link }}" class="button">Revisar agora</a>
        </div>
        <p>Não quer mais lembretes? Desligue as notificações nas <a href="{{ $preferencias }}">preferências</a>.</p>
        <div class="footer">
            BeReady - Aprendizado de Inglês<br>
            Este e-mail foi enviado automaticamente, não responda.
        </div>
    </div>
</body>
</html>