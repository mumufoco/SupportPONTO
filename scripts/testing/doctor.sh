#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

status_ok() { echo "[OK] $1"; }
status_warn() { echo "[WARN] $1"; }
status_err() { echo "[ERROR] $1"; }

if [[ ! -f composer.json ]]; then
  status_err "composer.json não encontrado no diretório atual: $ROOT_DIR"
  exit 1
fi
status_ok "Projeto detectado em $ROOT_DIR"

if [[ ! -f composer.lock ]]; then
  status_warn "composer.lock ausente; não é possível garantir reprodutibilidade da suíte"
fi

if [[ -f composer.lock ]]; then
  if php -r '$l=json_decode(file_get_contents("composer.lock"),true); foreach(($l["packages-dev"]??[]) as $p){ if(($p["name"]??"")=="phpunit/phpunit"){ echo $p["version"]; exit(0);} } exit(1);' >/tmp/.phpunit_lock_version 2>/dev/null; then
    status_ok "Lock inclui phpunit/phpunit ($(cat /tmp/.phpunit_lock_version))"
  else
    status_err "composer.lock não inclui phpunit/phpunit em packages-dev"
    echo "[HINT] Rode: composer update phpunit/phpunit --dev"
    exit 1
  fi
fi

if [[ ! -f vendor/autoload.php ]]; then
  status_err "Dependências ausentes (vendor/autoload.php não encontrado)."
  echo "[HINT] Caminho canônico: composer run test:setup"
  echo "[HINT] Alternativo manual: composer install"
  exit 1
fi
status_ok "vendor/autoload.php presente"

autoload_has_phpunit=0
if php -r "require 'vendor/autoload.php'; exit(class_exists('PHPUnit\\TextUI\\Application') ? 0 : 1);" >/dev/null 2>&1; then
  autoload_has_phpunit=1
  status_ok "Autoload do PHPUnit validado"
else
  status_err "Autoload atual não expõe classes do PHPUnit"
fi

if [[ -f vendor/bin/phpunit ]]; then
  status_ok "vendor/bin/phpunit presente"
else
  status_warn "vendor/bin/phpunit ausente"
fi

if [[ "$autoload_has_phpunit" -eq 0 && -f vendor/bin/phpunit ]]; then
  status_warn "Inconsistência detectada: binário presente, mas classes do PHPUnit ausentes no autoload"
  echo "[DETAIL] Isso normalmente indica snapshot/vendor parcial ou drift de vendor em relação ao composer.lock"
  echo "[HINT] Execute: composer run test:setup"
  echo "[HINT] Se persistir: rm -rf vendor && composer install"
  exit 1
fi

if [[ -f phpunit.xml ]]; then
  status_ok "phpunit.xml encontrado"
else
  status_err "phpunit.xml ausente"
  exit 1
fi

status_ok "Diagnóstico concluído"
echo "[NEXT] Setup canônico: composer run test:setup"
echo "[NEXT] Executar suíte principal: composer test"
