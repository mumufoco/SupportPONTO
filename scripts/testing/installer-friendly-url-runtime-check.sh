#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

fail() {
  echo "[ERRO] $*" >&2
  exit 1
}

require_file() {
  [[ -f "$1" ]] || fail "Arquivo obrigatório ausente: $1"
}

require_file public/install/index.php
require_file install/index.php
require_file tools/installer/install_web.php
require_file tools/installer/install_helpers.php
require_file docs/infrastructure/nginx/supportponto-install-public-root.conf
require_file docs/infrastructure/nginx/supportponto-install-project-root.conf

if [[ -f .env || -f writable/installer/installed.lock ]]; then
  fail "O check runtime de /install deve ser executado em source-package limpo, sem .env e sem installed.lock."
fi

mkdir -p build/installer-runtime-check writable/logs writable/session writable/installer

run_cli_entrypoint_case() {
  local name="$1"
  local entrypoint="$2"
  local body="build/installer-runtime-check/${name}.html"

  /usr/bin/timeout 20s php -r '
    $_SERVER["HTTP_HOST"] = "ponto.supportsondagens.com.br";
    $_SERVER["SERVER_NAME"] = "ponto.supportsondagens.com.br";
    $_SERVER["REMOTE_ADDR"] = "127.0.0.1";
    $_SERVER["REQUEST_URI"] = "/install";
    $_SERVER["REQUEST_METHOD"] = "GET";
    include $argv[1];
  ' "$entrypoint" > "$body"

  grep -Fq 'Instalador autônomo estilo WordPress' "$body" || grep -Fq 'Boas-vindas e checagem inicial' "$body" || {
    head -80 "$body" >&2
    fail "O entrypoint ${entrypoint} não renderizou o wizard bootstrapless no caso ${name}."
  }

  echo "[OK] /install renderiza o instalador no cenário ${name} via ${entrypoint}"
}

run_cli_entrypoint_case public-root public/install/index.php
run_cli_entrypoint_case project-root install/index.php

grep -Fq 'location = /install' docs/infrastructure/nginx/supportponto-install-public-root.conf || fail 'Snippet Nginx public-root sem location = /install.'
grep -Fq 'location ^~ /install/' docs/infrastructure/nginx/supportponto-install-public-root.conf || fail 'Snippet Nginx public-root sem location ^~ /install/.'
grep -Fq 'location = /install' docs/infrastructure/nginx/supportponto-install-project-root.conf || fail 'Snippet Nginx project-root sem location = /install.'
grep -Fq 'location ^~ /install/' docs/infrastructure/nginx/supportponto-install-project-root.conf || fail 'Snippet Nginx project-root sem location ^~ /install/.'

echo '[OK] Check runtime bootstrapless de /install concluído nos dois entrypoints.'
