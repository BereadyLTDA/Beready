#!/bin/sh
# Entrypoint da imagem de produção da API: cacheia a configuração lida do ambiente do container
# (as variáveis do backend/.env.example) e aplica as migrations antes de subir o servidor.
set -e

php artisan config:cache
php artisan route:cache

# Em um banco que veio da versão em CakePHP, a migration inicial só se registra como aplicada
php artisan migrate --force

exec "$@"
