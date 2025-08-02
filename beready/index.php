<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BeReady - Início</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #2c0047;
            color: white;
        }
        header {
            background-color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 30px;
        }
        header img {
            height: 40px;
        }
        nav {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        nav a {
            color: black;
            text-decoration: none;
            font-weight: bold;
        }
        .btn-criar {
            background-color: orange;
            padding: 8px 16px;
            border-radius: 20px;
            color: white;
            border: none;
            cursor: pointer;
            font-weight: bold;
        }
        .hero {
            background-color: #751aff;
            margin: 60px auto;
            padding: 40px 20px;
            text-align: center;
            width: fit-content;
            border-radius: 20px;
        }
        .cards {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin: 40px 20px;
            flex-wrap: wrap;
        }
        .card {
            background-color: #eeeeee;
            color: black;
            padding: 20px;
            border-radius: 15px;
            max-width: 300px;
            text-align: center;
        }
    </style>
</head>
<body>
    <header>
        <img src="logo.png" alt="Logo BeReady">
        <nav>
            <a href="#">Seus flash card</a>
            <a href="#">Gerador de flash card</a>
            <a href="#">Criador de Quizes</a>
            <a href="#">Gramática</a>
            <a href="#">Outras funcionalidades</a>
            <a href="#"><img src="settings-icon.png" alt="Configurações" style="height: 20px;"></a>
            <button class="btn-criar">+ Criar</button>
            <span>Samuel</span>
        </nav>
    </header>

    <div class="hero">
        <h1>Beready</h1>
        <p>Experimente uma nova maneira de aprender<br>com o poder do Beready</p>
    </div>

    <div class="cards">
        <div class="card">
            <p>Aprimore seus estudos ou desenvolvimento profissional gerando rapidamente flashcards, questionários e resumos personalizados usando nossas ferramentas baseadas em IA. Transforme facilmente suas anotações em recursos interativos em apenas alguns minutos.</p>
        </div>
        <div class="card">
            <p>Fortaleça sua compreensão com explicações personalizadas, baseadas nos conteúdos que você escolher. Aproveite nossos recursos de flashcards ou explicações detalhadas, tudo com o apoio da nossa IA.</p>
        </div>
        <div class="card">
            <p>Compartilhe facilmente seu conteúdo gerado com colegas ou instrutores. Envie questionários, notas e flashcards para aprimorar as sessões de estudo em grupo ou compartilhe suas ideias com outras pessoas em algumas etapas fáceis.</p>
        </div>
    </div>
</body>
</html>
