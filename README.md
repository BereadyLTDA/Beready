# 📘 BeReady — Plataforma de Aprendizado de Inglês por Contextualização Visual

**BeReady** é uma aplicação web desenvolvida em **PHP**, focada em facilitar o aprendizado de inglês por meio de flashcards visuais, organização por tags, acompanhamento de progresso e histórico de estudo.  
O sistema segue o padrão arquitetural **MVC (Model-View-Controller)**, garantindo organização, modularidade e facilidade de manutenção.

---

## 🚀 Tecnologias Utilizadas

- **PHP 8.x** — Lógica do servidor
- **MySQL** — Armazenamento de dados
- **XAMPP** — Ambiente de desenvolvimento local com Apache e MySQL
- **HTML/CSS** — Construção das views (em andamento)
- **Git/GitHub** — Controle de versão e colaboração em equipe

---

## 📁 Estrutura do Projeto

O projeto está estruturado da seguinte forma:

```plaintext
/
├── config/         # Arquivos de configuração (ex: database.php)
├── controllers/    # Camada responsável pelo processamento das requisições
├── core/           # Funcionalidades centrais, como conexão com o banco
├── exceptions/     # Classes de exceções personalizadas
├── models/         # Regras de negócio e manipulação dos dados
├── router/         # Roteador principal da aplicação
├── views/          # Telas e componentes visuais (ex: login, dashboard)
└── index.php       # Arquivo inicial do projeto


 adicione essas informacoes no arquivo: ⚙️ Pré-requisitos para Execução

Certifique-se de ter o seguinte ambiente configurado:

    XAMPP instalado

    PHP na versão 8 ou superior

    MySQL ativado (phpMyAdmin incluso no XAMPP)

    Git instalado (Download Git)

🔄 Configuração e Execução no XAMPP com GitHub
1️⃣ Clonando o repositório para o XAMPP

Para rodar o projeto localmente e sincronizar com o GitHub:

    Abra o Git Bash.

    Vá até a pasta htdocs do XAMPP:

cd /c/xampp/htdocs

Clone o repositório da organização:

    git clone https://github.com/BereadyLTDA/Beready.git

    Agora a pasta do projeto estará dentro do htdocs, pronta para rodar no navegador.

2️⃣ Executando no navegador

    Inicie o Apache e MySQL no XAMPP Control Panel.

    No navegador, acesse:

    http://localhost/Beready

3️⃣ Mantendo sincronizado com o GitHub

Se você alterar qualquer arquivo e quiser enviar para o GitHub:

git add .
git commit -m "Descrição das alterações"
git push origin main

Para atualizar o código local com o que está no GitHub:

git pull origin main

👥 Permissões para todos os membros da organização fazerem commit

    No GitHub, acesse o repositório.

    Vá em Settings > Collaborators and teams.

    Adicione o time ou usuário e defina a permissão Write ou Admin.

    Todos os membros com permissão Write podem fazer commits diretamente na branch.

💻 Criando o projeto direto no XAMPP e vinculando ao GitHub

Caso prefira criar o projeto no seu próprio PC já dentro da pasta htdocs e sincronizar com o GitHub:

    Crie a pasta do projeto:

cd /c/xampp/htdocs
mkdir Beready
cd Beready

Inicialize o Git:

git init

Conecte ao repositório remoto:

git remote add origin https://github.com/BereadyLTDA/Beready.git

Baixe os arquivos já existentes no GitHub para sua pasta:

git pull origin main

Agora você pode alterar o código, rodar no localhost e sincronizar usando:

    git add .
    git commit -m "Alterações feitas"
    git push origin main

📌 Observação sobre as pastas views

Se as pastas dentro de views não estiverem aparecendo no navegador, verifique:

    Se o roteador (router) está configurado para redirecionar corretamente.

    Se o Apache possui permissão de leitura na pasta.

    Se os arquivos .htaccess ou regras de reescrita não estão bloqueando o acesso direto.


⚙️ Pré-requisitos para Execução

Certifique-se de ter o seguinte ambiente configurado:

    XAMPP instalado

    PHP na versão 8 ou superior

    MySQL ativado (phpMyAdmin incluso no XAMPP)

    Git instalado (Download Git)

🔄 Configuração e Execução no XAMPP com GitHub
1️⃣ Clonando o repositório para o XAMPP

Para rodar o projeto localmente e sincronizar com o GitHub:

    Abra o Git Bash.

    Vá até a pasta htdocs do XAMPP:

cd /c/xampp/htdocs

Clone o repositório da organização:

    git clone https://github.com/BereadyLTDA/Beready.git

    Agora a pasta do projeto estará dentro do htdocs, pronta para rodar no navegador.

2️⃣ Executando no navegador

    Inicie o Apache e MySQL no XAMPP Control Panel.

    No navegador, acesse:

    http://localhost/Beready

3️⃣ Mantendo sincronizado com o GitHub

Se você alterar qualquer arquivo e quiser enviar para o GitHub:

git add .
git commit -m "Descrição das alterações"
git push origin main

Para atualizar o código local com o que está no GitHub:

git pull origin main

👥 Permissões para todos os membros da organização fazerem commit

    No GitHub, acesse o repositório.

    Vá em Settings > Collaborators and teams.

    Adicione o time ou usuário e defina a permissão Write ou Admin.

    Todos os membros com permissão Write podem fazer commits diretamente na branch.

💻 Criando o projeto direto no XAMPP e vinculando ao GitHub

Caso prefira criar o projeto no seu próprio PC já dentro da pasta htdocs e sincronizar com o GitHub:

    Crie a pasta do projeto:

cd /c/xampp/htdocs
mkdir Beready
cd Beready

Inicialize o Git:

git init

Conecte ao repositório remoto:

git remote add origin https://github.com/BereadyLTDA/Beready.git

Baixe os arquivos já existentes no GitHub para sua pasta:

git pull origin main

Agora você pode alterar o código, rodar no localhost e sincronizar usando:

    git add .
    git commit -m "Alterações feitas"
    git push origin main

📌 Observação sobre as pastas views

Se as pastas dentro de views não estiverem aparecendo no navegador, verifique:

    Se o roteador (router) está configurado para redirecionar corretamente.

    Se o Apache possui permissão de leitura na pasta.

    Se os arquivos .htaccess ou regras de reescrita não estão bloqueando o acesso direto.