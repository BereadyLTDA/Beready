<?php include '../header.php'; ?>
<div style="background-color: #2c0047; color: white; min-height: 100vh; padding: 60px; display: flex; justify-content: center; align-items: center;">
    <form action="../controllers/UsuarioController.php?action=login" method="POST" style="background-color: #751aff; padding: 30px; border-radius: 20px; display: flex; flex-direction: column; gap: 15px; width: 300px;">
        <h2 style="text-align: center;">Entrar</h2>
        <input type="email" name="email" placeholder="E-mail" required>
        <input type="password" name="senha" placeholder="Senha" required>
        <a href="esqueci_senha.php" style="color: white; font-size: 0.9em;">Esqueceu sua senha?</a>
        <button type="submit" style="background-color: white; color: #751aff; font-weight: bold; padding: 10px; border: none; border-radius: 10px;">Entrar</button>
        <hr>
        <p style="text-align: center;">Não tem conta? <a href="registrar.php">Registrar-se</a></p>
    </form>
</div>
<?php include '../footer.php'; ?>
