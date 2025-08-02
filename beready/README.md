# Projeto BeReady

## Requisitos
- XAMPP
- PHP 8.x
- MySQL (phpMyAdmin incluso no XAMPP)

## Como configurar

1. Clone ou baixe este projeto.

2. Copie a pasta para o diretório do XAMPP:
```bash
C:\xampp\htdocs
```

3. Crie o banco de dados:
   - Acesse o phpMyAdmin
   - Crie um banco com o nome: `beready`
   - Ainda não há tabelas definidas.

4. Ajuste o arquivo de conexão `config/db.php` se necessário:
```php
$host = "localhost";
$user = "root";
$password = "";
$dbname = "beready";
```
