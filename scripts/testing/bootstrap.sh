#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

log() { echo "[test:setup] $1"; }
err() { echo "[ERROR] $1"; }

if ! command -v composer >/dev/null 2>&1; then
  err "composer não encontrado no PATH."
  exit 1
fi

if [[ ! -f composer.json ]]; then
  err "composer.json não encontrado em $ROOT_DIR"
  exit 1
fi

has_phpunit_in_lock=0
if [[ -f composer.lock ]]; then
  if php -r '$l=json_decode(file_get_contents("composer.lock"),true); foreach(($l["packages-dev"]??[]) as $p){ if(($p["name"]??"")=="phpunit/phpunit"){ exit(0);} } exit(1);' >/dev/null 2>&1; then
    has_phpunit_in_lock=1
  fi
fi

has_autoload=0
[[ -f vendor/autoload.php ]] && has_autoload=1

has_phpunit_binary=0
if [[ -f vendor/bin/phpunit || -f vendor/phpunit/phpunit/phpunit ]]; then
  has_phpunit_binary=1
fi

autoload_has_phpunit=0
if [[ "$has_autoload" -eq 1 ]]; then
  if php -r "require 'vendor/autoload.php'; exit(class_exists('PHPUnit\\TextUI\\Application') ? 0 : 1);" >/dev/null 2>&1; then
    autoload_has_phpunit=1
  fi
fi

needs_install=0
install_reason=""

if [[ "$has_autoload" -eq 0 ]]; then
  needs_install=1
  install_reason="vendor/autoload.php ausente"
elif [[ "$autoload_has_phpunit" -eq 0 ]]; then
  needs_install=1
  if [[ "$has_phpunit_binary" -eq 1 ]]; then
    install_reason="vendor/bin/phpunit presente mas autoload sem PHPUnit (snapshot/vendor inconsistente)"
  else
    install_reason="autoload sem classes do PHPUnit"
  fi
elif [[ "$has_phpunit_binary" -eq 0 ]]; then
  needs_install=1
  install_reason="binário do PHPUnit ausente"
fi

if [[ "$has_phpunit_in_lock" -eq 0 ]]; then
  err "composer.lock não contém phpunit/phpunit em packages-dev."
  err "Rode 'composer update phpunit/phpunit --dev' e comite o lock correto antes de executar a suíte."
  exit 1
fi

if [[ "$needs_install" -eq 1 ]]; then
  log "Dependências de teste precisam ser normalizadas: $install_reason"
  log "Executando composer install com dependências dev (COMPOSER_NO_DEV=0)."

  if COMPOSER_NO_DEV=0 composer install --prefer-dist --no-interaction --no-progress; then
    log "Dependências instaladas com sucesso."
  else
    PHP_MM="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
    if [[ "$PHP_MM" == "8.5" || "$PHP_MM" > "8.5" ]]; then
      log "Fallback para PHP ${PHP_MM}: composer install --ignore-platform-req=php"
      COMPOSER_NO_DEV=0 composer install --prefer-dist --no-interaction --no-progress --ignore-platform-req=php
    else
      err "Falha no composer install."
      exit 1
    fi
  fi
else
  log "Dependências de teste já disponíveis e consistentes."
fi

if [[ ! -f vendor/autoload.php ]]; then
  err "vendor/autoload.php ausente após setup."
  exit 1
fi

if ! php -r "require 'vendor/autoload.php'; exit(class_exists('PHPUnit\\TextUI\\Application') ? 0 : 1);" >/dev/null 2>&1; then
  err "Autoload final ainda não expõe PHPUnit."
  err "Isso indica vendor inconsistente com composer.lock ou instalação incompleta."
  err "Execute: rm -rf vendor composer install"
  exit 1
fi

if [[ ! -f vendor/bin/phpunit && ! -f vendor/phpunit/phpunit/phpunit ]]; then
  err "PHPUnit indisponível no vendor após setup."
  exit 1
fi

if [[ ! -f phpunit.xml ]]; then
  err "phpunit.xml não encontrado."
  exit 1
fi

log "Setup de testes concluído."
