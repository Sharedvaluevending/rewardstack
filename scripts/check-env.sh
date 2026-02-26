#!/usr/bin/env bash
set -euo pipefail

ENV_FILE="${1:-.env}"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "ERROR: $ENV_FILE not found"
  exit 1
fi

# shellcheck disable=SC1090
set -a
source "$ENV_FILE"
set +a

fail=0
req() {
  local k="$1"
  if [[ -z "${!k:-}" ]]; then
    echo "MISSING: $k"
    fail=1
  fi
}

echo "Checking required production-like settings in: $ENV_FILE"

# Always-required
req APP_ENV
req APP_KEY
req APP_URL
req DB_CONNECTION
req DB_HOST
req DB_DATABASE
req DB_USERNAME

# Enterprise/scaling defaults
req CACHE_DRIVER
req QUEUE_CONNECTION
req SESSION_DRIVER

# Redis vars are required if redis is selected
if [[ "${CACHE_DRIVER:-}" == "redis" || "${QUEUE_CONNECTION:-}" == "redis" || "${SESSION_DRIVER:-}" == "redis" ]]; then
  req REDIS_HOST
  req REDIS_PORT
fi

if [[ "$fail" -eq 1 ]]; then
  echo "
FAIL: missing required values"
  exit 1
fi

echo "OK: env looks sane"
