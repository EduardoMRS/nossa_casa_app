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

mkdir -p \
    storage/app/private/backups \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache
chown -R laravel:laravel storage/app
chown -R laravel:laravel storage/framework bootstrap/cache

# The project is bind-mounted from the host. Keep the log writable by PHP-FPM
# while allowing the host user to inspect and edit it.
mkdir -p storage/logs
touch storage/logs/laravel.log
chown -R laravel:laravel storage/logs
chmod 0775 storage/logs
chmod 0664 storage/logs/laravel.log

composer_manifest="composer.lock"
[ -f "$composer_manifest" ] || composer_manifest="composer.json"
composer_manifest_hash="$(sha256sum "$composer_manifest" | awk '{print $1}')"
app_environment="${APP_ENV:-}"

if [ -z "$app_environment" ]; then
    while IFS='=' read -r env_key env_value; do
        if [ "$env_key" = "APP_ENV" ]; then
            app_environment="$env_value"
            break
        fi
    done < .env
fi

app_environment="$(printf '%s' "$app_environment" | tr -d "\"'")"
composer_install_mode="development"

if [ "$app_environment" = "production" ]; then
    composer_install_mode="production"
    export BOOST_ENABLED=false
    export BOOST_BROWSER_LOGS_WATCHER=false
fi

composer_state_hash="${composer_manifest_hash}:${composer_install_mode}"
installed_composer_hash="$(cat vendor/.composer-manifest.sha256 2>/dev/null || true)"

if [ ! -f vendor/autoload.php ] || [ "$composer_state_hash" != "$installed_composer_hash" ]; then
    echo "Instalando dependências do Composer..."

    if [ "$composer_install_mode" = "production" ]; then
        composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
    else
        composer install --no-interaction --prefer-dist --optimize-autoloader
    fi

    printf '%s' "$composer_state_hash" > vendor/.composer-manifest.sha256
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

if [ "$1" = "php-fpm" ]; then
    echo "Gerando os assets frontend..."
    npm run build
fi

exec "$@"
