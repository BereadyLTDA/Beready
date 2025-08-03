# 📘 BeReady — Plataforma de Aprendizado de Inglês por Contextualização Visual

O **BeReady** é uma aplicação web desenvolvida em PHP que ajuda usuários a aprender inglês utilizando flashcards, categorização por tags, progresso individual e histórico de estudo. O sistema foi criado com base no padrão MVC (Model-View-Controller) para manter a organização e facilitar a manutenção do código.

---

## 🚀 Tecnologias Utilizadas

- **PHP 8.x** — Lógica do lado servidor
- **MySQL** — Armazenamento dos dados (usuários, flashcards, progresso, etc.)
- **XAMPP** — Ambiente local com Apache + MySQL
- **HTML/CSS** — Views da aplicação (em construção)

---

## 📁 Estrutura do Projeto

O projeto está organizado da seguinte forma:
├── config/ # Arquivos de configuração (ex: database.php)
├── controllers/ # Controladores responsáveis por processar as requisições
├── core/ # Componentes centrais, como conexão com o banco
├── exceptions/ # Classes de tratamento de exceções personalizadas
├── models/ # Regras de negócio e acesso ao banco de dados
├── router/ # Arquivo principal de roteamento da aplicação
├── views/ # Interfaces visuais do sistema (login, painel, etc.)


---

## ⚙️ Requisitos para Executar

Para rodar o projeto em sua máquina local, você precisa de:

- [XAMPP](https://www.apachefriends.org/) instalado
- PHP versão 8 ou superior
- MySQL ativado (phpMyAdmin já incluso no XAMPP)

---

## 🛠️ Passo a Passo para Configuração

## 🌐 Endpoints da Aplicação
As rotas são simples e baseadas no método HTTP (GET, POST, etc.).

- /usuarios
1. GET: Lista todos os usuários

2. POST: Cria um novo usuário (email e senha)

- /login
1. POST: Realiza login com email e senha

- /flashcards
1. GET: Lista flashcards de um usuário

2. POST: Cria um novo flashcard

DELETE: Remove um flashcard (enviando o ID)

 - /progresso
1. GET: Lista o progresso de um usuário

2. POST: Marca um flashcard como concluído

- /historico
1. GET: Lista buscas anteriores de um usuário

2. POST: Registra uma nova busca

- /tags
GET: Lista todas as tags disponíveis

POST: Cria uma nova tag

## 📌 Funcionalidades em Desenvolvimento

1. Validação de entrada de dados

2. Autenticação por token ou sessão protegida

3. Criação de script SQL para estruturar o banco de dados automaticamente

4. Interface visual completa com responsividade

5. Testes automatizados e integração contínua
