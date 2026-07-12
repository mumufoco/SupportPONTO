#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

if [[ ! -f "vendor/autoload.php" ]]; then
  echo "[ERROR] Dependências Composer não instaladas (vendor/autoload.php ausente)."
  echo "[HINT] Caminho canônico: composer run test:setup"
  echo "[HINT] Alternativo manual: composer install"
  echo "[HINT] Evite --no-dev em ambiente de testes."
  exit 1
fi

if ! php -r "require 'vendor/autoload.php'; exit(class_exists('PHPUnit\\TextUI\\Application') ? 0 : 1);" >/dev/null 2>&1; then
  echo "[ERROR] O autoload atual não contém PHPUnit."
  if [[ -f "vendor/bin/phpunit" ]]; then
    echo "[DETAIL] vendor/bin/phpunit existe, mas o pacote phpunit/phpunit não está carregado no autoload (snapshot/vendor inconsistente)."
  fi
  echo "[HINT] Normalize o ambiente: composer run test:setup"
  echo "[HINT] Se persistir, limpe vendor e reinstale: rm -rf vendor && composer install"
  echo "[HINT] Ambiente com lock incompatível ao PHP local: composer install --ignore-platform-req=php"
  exit 1
fi

if [[ -f "vendor/bin/phpunit" ]]; then
  exec php "vendor/bin/phpunit" --configuration phpunit.xml "$@"
fi

if [[ -f "vendor/phpunit/phpunit/phpunit" ]]; then
  exec php "vendor/phpunit/phpunit/phpunit" --configuration phpunit.xml "$@"
fi

echo "[ERROR] Nenhum entrypoint local do PHPUnit foi encontrado em vendor/."
echo "[HINT] Execute: composer run test:setup"
echo "[HINT] Verifique o lock: composer show phpunit/phpunit --locked"
exit 1
