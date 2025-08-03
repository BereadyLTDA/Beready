<?php include '../header.php'; ?>
<div style="background-color: #2c0047; color: white; min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 40px;">
    <form action="../controllers/UsuarioController.php?action=registrar" method="POST" style="background-color: #751aff; padding: 30px; border-radius: 20px; display: flex; flex-direction: column; gap: 10px; width: 300px;">
        <h2 style="text-align: center;">Registrar-se</h2>
        <input type="email" name="email" placeholder="E-mail" required>
        <input type="email" name="email_confirm" placeholder="Confirmação de e-mail" required>
        <input type="password" name="senha" placeholder="Senha" required>
        <input type="password" name="senha_confirm" placeholder="Confirmação da senha" required>
        <label><input type="checkbox" required> Aceito os termos de uso e privacidade</label>
        <label><input type="checkbox"> Receber novidades por e-mail</label>
        <button type="submit" style="background-color: white; color: #751aff; padding: 10px; border: none; border-radius: 10px;">Registrar</button>
    </form>
</div>
<?php include '../footer.php'; ?>
