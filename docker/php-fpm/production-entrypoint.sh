#!/bin/sh
set -eu

export APP_KEY="$(cat /run/secrets/app_key)"
export DB_PASSWORD="$(cat /run/secrets/db_password)"

exec "$@"
