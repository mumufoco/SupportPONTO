#!/usr/bin/env bash
#
# SupportPONTO — Deploy único (substitui o pipeline Docker/source-package descontinuado)
#
# Ciclo completo automatizado: empacota o repositório, transfere via rsync/SSH
# para o servidor de produção (aaPanel), instala (composer/migrations/permissões)
# e reinicia os serviços (php-fpm/queue worker), nessa ordem.
#
# O repositório É o que roda em produção — não existe mais a distinção
# "source-package" vs "release-package": o deploy é direto do checkout local
# (ou de um worktree limpo de uma tag/branch) para o servidor via SSH.
#
# Configuração via variáveis de ambiente (ou arquivo --env-file):
#   DEPLOY_HOST           (obrigatório) ex.: 192.168.31.101
#   DEPLOY_USER           (obrigatório) ex.: mumufoco
#   DEPLOY_PATH           (obrigatório) ex.: /www/wwwroot/ponto.supportsondagens.com.br
#   DEPLOY_PHP_BIN        (opcional)    default: /www/server/php/83/bin/php
#   DEPLOY_PHP_FPM_SVC    (opcional)    default: php8.3-fpm
#   DEPLOY_WEB_USER       (opcional)    default: www
#   DEPLOY_SSH_PORT       (opcional)    default: 22
#
# Uso:
#   scripts/release/deploy.sh [opções]
#
# Opções:
#   --env-file <arquivo>   Carrega variáveis DEPLOY_* de um arquivo (não versionado)
#   --dry-run              Mostra o que seria feito, sem alterar o servidor
#   --skip-composer        Não roda "composer install --no-dev" no servidor
#   --skip-migrate         Não roda "php spark migrate"
#   --skip-restart         Não recarrega php-fpm / reinicia o queue worker
#   --yes                  Não pede confirmação interativa antes de aplicar
#   -h, --help             Mostra esta ajuda
#
# Pré-requisitos locais: bash, rsync, ssh
# Pré-requisitos no servidor: php, composer, sudo (para chown/systemctl), aaPanel
#
set -euo pipefail

# ---------------------------------------------------------------------------
# 0. Localização do projeto e parsing de argumentos
# ---------------------------------------------------------------------------
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

DRY_RUN=0
SKIP_COMPOSER=0
SKIP_MIGRATE=0
SKIP_RESTART=0
ASSUME_YES=0
ENV_FILE=""

print_help() { sed -n '2,33p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; }

while [[ $# -gt 0 ]]; do
  case "$1" in
    --env-file) ENV_FILE="${2:-}"; shift 2 ;;
    --dry-run) DRY_RUN=1; shift ;;
    --skip-composer) SKIP_COMPOSER=1; shift ;;
    --skip-migrate) SKIP_MIGRATE=1; shift ;;
    --skip-restart) SKIP_RESTART=1; shift ;;
    --yes|-y) ASSUME_YES=1; shift ;;
    -h|--help) print_help; exit 0 ;;
    *) echo "[ERRO] Opção desconhecida: $1" >&2; print_help; exit 1 ;;
  esac
done

if [[ -n "$ENV_FILE" ]]; then
  if [[ ! -f "$ENV_FILE" ]]; then
    echo "[ERRO] Arquivo de configuração não encontrado: $ENV_FILE" >&2
    exit 1
  fi
  # shellcheck source=/dev/null
  source "$ENV_FILE"
fi

: "${DEPLOY_HOST:?Defina DEPLOY_HOST (host/IP do servidor de produção)}"
: "${DEPLOY_USER:?Defina DEPLOY_USER (usuário SSH no servidor)}"
: "${DEPLOY_PATH:?Defina DEPLOY_PATH (diretório do projeto no servidor, ex.: /www/wwwroot/ponto.supportsondagens.com.br)}"
DEPLOY_PHP_BIN="${DEPLOY_PHP_BIN:-/www/server/php/83/bin/php}"
DEPLOY_PHP_FPM_SVC="${DEPLOY_PHP_FPM_SVC:-php8.3-fpm}"
DEPLOY_WEB_USER="${DEPLOY_WEB_USER:-www}"
DEPLOY_SSH_PORT="${DEPLOY_SSH_PORT:-22}"

SSH_TARGET="${DEPLOY_USER}@${DEPLOY_HOST}"
SSH_OPTS=(-p "$DEPLOY_SSH_PORT")
RSYNC_RSH="ssh -p $DEPLOY_SSH_PORT"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BLUE='\033[0;34m'; NC='\033[0m'
step()  { echo -e "\n${BLUE}==> $1${NC}"; }
ok()    { echo -e "${GREEN}[OK]${NC} $1"; }
warn()  { echo -e "${YELLOW}[AVISO]${NC} $1"; }
fail()  { echo -e "${RED}[ERRO]${NC} $1" >&2; exit 1; }

run_remote() {
  local cmd="$1"
  if [[ "$DRY_RUN" -eq 1 ]]; then
    echo "    [dry-run][remoto] $cmd"
  else
    ssh "${SSH_OPTS[@]}" "$SSH_TARGET" "$cmd"
  fi
}

run_remote_sudo() {
  local cmd="$1"
  if [[ "$DRY_RUN" -eq 1 ]]; then
    echo "    [dry-run][remoto/sudo] $cmd"
  else
    ssh "${SSH_OPTS[@]}" "$SSH_TARGET" "sudo bash -lc '$cmd'"
  fi
}

# ---------------------------------------------------------------------------
# 1. Empacotar — gera lista de exclusões e confere a versão local
# ---------------------------------------------------------------------------
step "1/4 — Empacotar"

RELEASE_VERSION="desconhecida"
if [[ -f "$ROOT_DIR/release.json" ]]; then
  RELEASE_VERSION=$(grep -m1 '"version"' "$ROOT_DIR/release.json" | sed -E 's/.*"version"\s*:\s*"([^"]+)".*/\1/')
fi
ok "Repositório local: $ROOT_DIR"
ok "Versão declarada em release.json: $RELEASE_VERSION"

EXCLUDES=(
  '.git/' '.gitignore'
  'node_modules/'
  'build/'
  'tests/'
  'writable/cache/*' 'writable/session/*' 'writable/logs/*' 'writable/uploads/*'
  'writable/installer/*'
  '.env' '.env.*'
  '*.zip' '*.tar.gz'
  '.phpunit.result.cache'
)
EXCLUDE_ARGS=()
for pattern in "${EXCLUDES[@]}"; do
  EXCLUDE_ARGS+=(--exclude="$pattern")
done

ok "Exclusões aplicadas: ${EXCLUDES[*]}"
[[ "$DRY_RUN" -eq 1 ]] && warn "Modo --dry-run ativo: nada será alterado no servidor."

if [[ "$ASSUME_YES" -ne 1 && "$DRY_RUN" -ne 1 ]]; then
  echo ""
  echo "Você está prestes a enviar este checkout local para:"
  echo "  ${SSH_TARGET}:${DEPLOY_PATH}"
  read -r -p "Confirma o deploy para PRODUÇÃO? (digite 'sim' para continuar) " CONFIRM
  [[ "$CONFIRM" == "sim" ]] || fail "Deploy cancelado pelo usuário."
fi

# ---------------------------------------------------------------------------
# 2. Transferir — rsync incremental via SSH (NUNCA sobrescreve .env remoto)
# ---------------------------------------------------------------------------
step "2/4 — Transferir (rsync via SSH)"

RSYNC_CMD=(rsync -az --delete-after
  "${EXCLUDE_ARGS[@]}"
  -e "$RSYNC_RSH"
  "$ROOT_DIR/" "${SSH_TARGET}:${DEPLOY_PATH}/")

if [[ "$DRY_RUN" -eq 1 ]]; then
  echo "    [dry-run] ${RSYNC_CMD[*]} --dry-run"
  rsync -az --delete-after "${EXCLUDE_ARGS[@]}" -e "$RSYNC_RSH" --dry-run \
    "$ROOT_DIR/" "${SSH_TARGET}:${DEPLOY_PATH}/" | tail -n 40
else
  "${RSYNC_CMD[@]}"
fi
ok "Transferência concluída."

# ---------------------------------------------------------------------------
# 3. Instalar — dependências, migrations, cache, permissões
# ---------------------------------------------------------------------------
step "3/4 — Instalar"

if [[ "$SKIP_COMPOSER" -eq 0 ]]; then
  ok "Rodando composer install --no-dev no servidor…"
  run_remote "cd '$DEPLOY_PATH' && composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader"
else
  warn "composer install pulado (--skip-composer)."
fi

if [[ "$SKIP_MIGRATE" -eq 0 ]]; then
  ok "Rodando migrations…"
  run_remote "cd '$DEPLOY_PATH' && $DEPLOY_PHP_BIN spark migrate --all --no-interaction"
else
  warn "migrations puladas (--skip-migrate)."
fi

ok "Limpando caches da aplicação…"
run_remote "cd '$DEPLOY_PATH' && $DEPLOY_PHP_BIN spark cache:clear || true"

ok "Normalizando dono/permissões (www:www)…"
run_remote_sudo "chown -R ${DEPLOY_WEB_USER}:${DEPLOY_WEB_USER} '$DEPLOY_PATH/writable' && find '$DEPLOY_PATH/writable' -type d -exec chmod 750 {} \\; && find '$DEPLOY_PATH/writable' -type f -exec chmod 640 {} \\;"

# ---------------------------------------------------------------------------
# 4. Reiniciar serviços
# ---------------------------------------------------------------------------
step "4/4 — Reiniciar serviços"

if [[ "$SKIP_RESTART" -eq 0 ]]; then
  ok "Recarregando ${DEPLOY_PHP_FPM_SVC}…"
  run_remote_sudo "systemctl reload ${DEPLOY_PHP_FPM_SVC}"

  ok "Reiniciando worker de filas (se configurado via systemd: supportponto-queue)…"
  run_remote_sudo "systemctl restart supportponto-queue 2>/dev/null || echo '[info] serviço supportponto-queue não encontrado — ignorando'"
else
  warn "Reinício de serviços pulado (--skip-restart)."
fi

# ---------------------------------------------------------------------------
# Verificação pós-deploy
# ---------------------------------------------------------------------------
step "Verificação pós-deploy"
if [[ "$DRY_RUN" -eq 0 ]]; then
  ok "Rodando smoke check de saúde (scripts/verify-deployment.sh equivalente: /health/readiness)…"
  run_remote "curl -ks -o /dev/null -w 'HTTP %{http_code}\\n' https://localhost/health/readiness || true"
fi

echo ""
ok "Deploy concluído. Versão enviada: $RELEASE_VERSION → ${SSH_TARGET}:${DEPLOY_PATH}"
[[ "$DRY_RUN" -eq 1 ]] && warn "Lembrete: isso foi um --dry-run. Nada foi alterado no servidor."
exit 0
