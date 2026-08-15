#!/bin/sh
set -eu

config_directory=/config
env_file="$config_directory/.env"
config_template="$config_directory/mediamtx.yml"

mkdir -p "$config_directory"

if [ ! -f "$env_file" ]; then
    cp /mediamtx.env.example "$env_file"
fi

while IFS= read -r env_line || [ -n "$env_line" ]; do
    case "$env_line" in
        ''|'#'*) continue ;;
    esac

    env_key=${env_line%%=*}
    env_value=${env_line#*=}

    case "$env_key" in
        *[!A-Za-z0-9_]*) continue ;;
    esac

    eval "is_configured=\${$env_key+x}"

    if [ -z "$is_configured" ]; then
        export "$env_key=$env_value"
    fi
done < "$env_file"

if [ ! -f "$config_template" ]; then
    cp /mediamtx.template.yml "$config_template"
fi

MEDIA_CORE_URL="${MEDIA_CORE_URL:-http://webserver}"
MEDIA_RECORDING_WEBHOOK_URL="${MEDIA_RECORDING_WEBHOOK_URL:-http://webserver/api/internal/media/recording-segment-completed}"

escaped_core_url=$(printf '%s' "$MEDIA_CORE_URL" | sed 's/[&|]/\\&/g')
escaped_recording_webhook_url=$(printf '%s' "$MEDIA_RECORDING_WEBHOOK_URL" | sed 's/[&|]/\\&/g')

sed \
    -e "s|__MEDIA_CORE_URL__|$escaped_core_url|g" \
    -e "s|__MEDIA_RECORDING_WEBHOOK_URL__|$escaped_recording_webhook_url|g" \
    "$config_template" > /tmp/mediamtx.yml

exec /mediamtx /tmp/mediamtx.yml
