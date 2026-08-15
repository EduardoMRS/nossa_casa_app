#!/bin/sh
set -e

created_project_env=false
env_template=".env.example"

if [ ! -f "$env_template" ] && [ -f /usr/local/share/nossa-casa.env.example ]; then
    env_template=/usr/local/share/nossa-casa.env.example
fi

if [ ! -f .env ]; then
    if [ ! -f "$env_template" ]; then
        echo "Erro: .env não encontrado e .env.example não está disponível." >&2
        echo "Crie o arquivo .env a partir do .env.example antes de iniciar o container." >&2
        exit 1
    fi

    echo "Copiando $env_template para .env..."
    cp "$env_template" .env
    created_project_env=true
fi

if [ -f "$env_template" ]; then
    while IFS= read -r env_line || [ -n "$env_line" ]; do
        case "$env_line" in
            ''|'#'*) continue ;;
        esac

        env_key=${env_line%%=*}

        case "$env_key" in
            *[!A-Za-z0-9_]*) continue ;;
        esac

        if ! grep -q "^${env_key}=" .env; then
            printf '%s\n' "$env_line" >> .env
        fi
    done < "$env_template"
fi

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache
chown -R www-data:www-data storage/framework bootstrap/cache

composer_manifest="composer.lock"
[ -f "$composer_manifest" ] || composer_manifest="composer.json"
composer_manifest_hash="$(sha256sum "$composer_manifest" | awk '{print $1}')"
installed_composer_hash="$(cat vendor/.composer-manifest.sha256 2>/dev/null || true)"

if [ ! -f vendor/autoload.php ] || [ "$composer_manifest_hash" != "$installed_composer_hash" ]; then
    echo "Instalando dependências do Composer..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
    printf '%s' "$composer_manifest_hash" > vendor/.composer-manifest.sha256
fi

if [ "$created_project_env" = "true" ] || grep -Eq '^APP_KEY=$' .env; then
    php artisan key:generate --no-interaction
    php artisan wayfinder:generate --with-form --no-interaction
fi

node_manifest="package-lock.json"
[ -f "$node_manifest" ] || node_manifest="package.json"
node_manifest_hash="$(sha256sum "$node_manifest" | awk '{print $1}')"
installed_node_hash="$(cat node_modules/.node-manifest.sha256 2>/dev/null || true)"

if [ "${SKIP_NODE_INSTALL:-false}" != "true" ] && { [ ! -x node_modules/.bin/vite ] || [ "$node_manifest_hash" != "$installed_node_hash" ]; }; then
    echo "Instalando dependências do NPM..."
    npm install
    printf '%s' "$node_manifest_hash" > node_modules/.node-manifest.sha256
fi

exec "$@"
