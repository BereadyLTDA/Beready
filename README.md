# 📘 BeReady — Plataforma de Aprendizado de Inglês por Contextualização Visual

**BeReady** é uma aplicação web desenvolvida em **PHP**, focada em facilitar o aprendizado de inglês por meio de flashcards visuais, organização por tags, acompanhamento de progresso e histórico de estudo. O sistema segue o padrão arquitetural **MVC (Model-View-Controller)**, garantindo organização, modularidade e facilidade de manutenção.

---

## 🚀 Tecnologias Utilizadas

- **PHP 8.x** — Lógica do servidor
- **MySQL** — Armazenamento de dados
- **XAMPP** — Ambiente de desenvolvimento local com Apache e MySQL
- **HTML/CSS** — Construção das views (em andamento)

---

## 📁 Estrutura do Projeto

O projeto está estruturado da seguinte forma:

/
├── config/ 
# Arquivos de configuração (ex: database.php)
├── controllers/ 
# Camada responsável pelo processamento das requisições
├── core/ 
# Funcionalidades centrais, como conexão com o banco
├── exceptions/ 
# Classes de exceções personalizadas
├── models/ 
# Regras de negócio e manipulação dos dados
├── router/ 
# Roteador principal da aplicação
├── views/ 
# Telas e componentes visuais (ex: login, dashboard)


---

## ⚙️ Pré-requisitos para Execução

Certifique-se de ter o seguinte ambiente configurado:

- [XAMPP](https://www.apachefriends.org/) instalado
- PHP na versão 8 ou superior
- MySQL ativado (phpMyAdmin incluso no XAMPP)
