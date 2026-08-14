#!/bin/sh
set -eu

MEDIA_CORE_URL="${MEDIA_CORE_URL:-http://webserver}"
MEDIA_RECORDING_WEBHOOK_URL="${MEDIA_RECORDING_WEBHOOK_URL:-http://webserver/api/internal/media/recording-completed}"

escaped_core_url=$(printf '%s' "$MEDIA_CORE_URL" | sed 's/[&|]/\\&/g')
escaped_recording_webhook_url=$(printf '%s' "$MEDIA_RECORDING_WEBHOOK_URL" | sed 's/[&|]/\\&/g')

sed \
    -e "s|__MEDIA_CORE_URL__|$escaped_core_url|g" \
    -e "s|__MEDIA_RECORDING_WEBHOOK_URL__|$escaped_recording_webhook_url|g" \
    /mediamtx.template.yml > /tmp/mediamtx.yml

exec /mediamtx /tmp/mediamtx.yml
