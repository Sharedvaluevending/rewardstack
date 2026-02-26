#!/usr/bin/env bash
set -euo pipefail

# Safe Docker test runner:
# - Uses docker-compose.testing.yml (isolated MySQL volume/db)
# - Uses phpunit.docker.xml (MySQL settings)
# - Never runs worker/scheduler

COMPOSE_FILE="docker-compose.testing.yml"

echo "Building and starting test containers..."
docker compose -f "$COMPOSE_FILE" up -d --build db redis

echo "Waiting for MySQL to be ready..."
for i in {1..60}; do
  if docker compose -f "$COMPOSE_FILE" exec -T db mysqladmin ping -proot --silent >/dev/null 2>&1; then
    break
  fi
  sleep 1
done

if ! docker compose -f "$COMPOSE_FILE" exec -T db mysqladmin ping -proot --silent >/dev/null 2>&1; then
  echo "MySQL did not become ready in time."
  docker compose -f "$COMPOSE_FILE" logs db || true
  exit 1
fi

echo "Running PHPUnit inside the app container..."
docker compose -f "$COMPOSE_FILE" run --rm -T app sh -lc "./vendor/bin/phpunit -c phpunit.docker.xml"

echo "Done. Cleaning up test containers and volumes..."
docker compose -f "$COMPOSE_FILE" down -v

