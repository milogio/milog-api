#!/bin/sh
set -eu

export APP_KEY="$(cat /run/secrets/app_key)"
export DB_PASSWORD="$(cat /run/secrets/db_password)"
export PASSPORT_PRIVATE_KEY="$(base64 -d /run/secrets/passport_private_key_b64)"
export PASSPORT_PUBLIC_KEY="$(base64 -d /run/secrets/passport_public_key_b64)"
export MAIL_PASSWORD="$(cat /run/secrets/mail_password)"

exec "$@"
