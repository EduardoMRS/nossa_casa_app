#!/bin/sh
set -e

if [ ! -f .env ]; then
    echo "Copiando .env.example para .env..."
    cp .env.example .env
    php artisan key:generate
    php artisan wayfinder:generate --with-form
fi

if [ ! -d vendor ]; then
    echo "Instalando dependências do Composer..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
    php artisan key:generate
fi

if [ "${SKIP_NODE_INSTALL:-false}" != "true" ] && [ ! -d node_modules ]; then
    echo "Instalando dependências do NPM..."
    npm install
fi

exec "$@"
