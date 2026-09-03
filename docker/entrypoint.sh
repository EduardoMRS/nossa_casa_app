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
prebuilt_frontend_dir="/opt/nossa-casa/public-build"
use_prebuilt_frontend=false

if [ "$app_environment" = "production" ]; then
    composer_install_mode="production"
    export BOOST_ENABLED=false
    export BOOST_BROWSER_LOGS_WATCHER=false

    if [ -f "$prebuilt_frontend_dir/manifest.json" ] && [ -f "$prebuilt_frontend_dir/.production" ]; then
        use_prebuilt_frontend=true
    fi
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
fi
php artisan route:clear
php artisan config:clear
php artisan wayfinder:generate --with-form --no-interaction

node_manifest="package-lock.json"
[ -f "$node_manifest" ] || node_manifest="package.json"
node_manifest_hash="$(sha256sum "$node_manifest" | awk '{print $1}')"
installed_node_hash="$(cat node_modules/.node-manifest.sha256 2>/dev/null || true)"

if [ "$use_prebuilt_frontend" != "true" ] && [ "${SKIP_NODE_INSTALL:-false}" != "true" ] && { [ ! -x node_modules/.bin/vite ] || [ "$node_manifest_hash" != "$installed_node_hash" ]; }; then
    echo "Instalando dependências do NPM..."
    npm install
    printf '%s' "$node_manifest_hash" > node_modules/.node-manifest.sha256
fi

if [ "$1" = "php-fpm" ] && [ "$app_environment" = "production" ] && { [ "${RUN_MIGRATIONS:-true}" != "false" ] || [ "${RUN_SEEDERS:-true}" != "false" ]; }; then
    migration_attempt=1
    migration_max_attempts="${MIGRATION_MAX_ATTEMPTS:-12}"
    database_ready_command='require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $app->make("db")->connection()->getPdo();'

    until php -r "$database_ready_command" >/dev/null 2>&1; do
        if [ "$migration_attempt" -ge "$migration_max_attempts" ]; then
            echo "Erro: não foi possível abrir uma conexão PDO com o banco após $migration_attempt tentativa(s)." >&2
            php -r "$database_ready_command" >&2 || true
            exit 1
        fi

        echo "Banco ainda indisponível; nova tentativa em 5 segundos ($migration_attempt/$migration_max_attempts)..."
        migration_attempt=$((migration_attempt + 1))
        sleep 5
    done

    if [ "${RUN_MIGRATIONS:-true}" != "false" ]; then
        echo "Aplicando migrations pendentes..."
        php artisan migrate --force --no-interaction
    fi

    if [ "${RUN_SEEDERS:-true}" != "false" ]; then
        echo "Executando seeders de produção..."
        php artisan db:seed --force --no-interaction
    fi
fi

if [ "$1" = "php-fpm" ]; then
    if [ "$use_prebuilt_frontend" = "true" ]; then
        echo "Publicando os assets frontend gerados pela imagem..."
        rm -rf public/build
        mkdir -p public/build
        cp -R "$prebuilt_frontend_dir/." public/build/
        chown -R laravel:laravel public/build
    else
        echo "Gerando os assets frontend..."
        npm run build
    fi
fi

exec "$@"
