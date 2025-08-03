<?php include '../header.php'; ?>
<div style="background-color: #2c0047; color: white; min-height: 100vh; padding: 40px;">
    <h2>Tags</h2>
    <div style="display: flex; gap: 10px;">
        <span style="background-color: red; padding: 5px 10px; border-radius: 10px;">Vermelho</span>
        <span style="background-color: blue; padding: 5px 10px; border-radius: 10px;">Azul</span>
    </div>
    <h2 style="margin-top: 30px;">Flashcards</h2>
    <div style="background-color: #eee; color: black; padding: 10px; border-radius: 10px; max-width: 250px;">
        <strong>Animais em inglês</strong><br>8 cartas<br><small>2 dias atrás</small><br>
        <a href="flashcard_view.php">Ver</a> | <a href="#">Editar</a>
    </div>
    <h2 style="margin-top: 30px;">Quizzes</h2>
    <p>Nenhum quiz encontrado</p>
    <button style="background-color: white; color: #751aff; padding: 10px 20px; border-radius: 10px;">Criar quiz IA</button>
</div>
<?php include '../footer.php'; ?>
