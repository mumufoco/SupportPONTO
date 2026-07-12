#!/bin/sh

set -eu

if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
    COMPOSE_CMD='docker compose'
elif command -v docker-compose >/dev/null 2>&1; then
    COMPOSE_CMD='docker-compose'
else
    echo 'Docker Compose não está disponível (docker compose ou docker-compose).' >&2
    exit 1
fi

compose() {
    # shellcheck disable=SC2086
    $COMPOSE_CMD "$@"
}
