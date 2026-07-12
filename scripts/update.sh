#!/bin/bash

set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." && pwd)
# shellcheck source=/dev/null
source "$SCRIPT_DIR/lib/compose.sh"
cd "$ROOT_DIR"

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

print_header() { echo -e "${BLUE}\n======================================================================\n  Sistema de Ponto Eletrônico - Atualização\n======================================================================\n${NC}"; }
print_success() { echo -e "${GREEN}✓ $1${NC}"; }
print_error() { echo -e "${RED}✗ $1${NC}"; }
print_info() { echo -e "${YELLOW}→ $1${NC}"; }

confirm_update() {
    echo
    print_info "Esta atualização criará backup, aplicará código novo, executará migrações e reiniciará serviços."
    read -r -p "Deseja continuar? (s/N): " -n 1 REPLY || true
    echo
    [[ ${REPLY:-} =~ ^[Ss]$ ]] || { print_error "Atualização cancelada"; exit 1; }
}

enable_maintenance_mode() {
    print_info "Ativando modo de manutenção..."
    touch writable/maintenance.lock
    compose exec -T app touch writable/maintenance.lock || true
    print_success "Modo de manutenção ativado"
}

disable_maintenance_mode() {
    print_info "Desativando modo de manutenção..."
    rm -f writable/maintenance.lock
    compose exec -T app rm -f writable/maintenance.lock 2>/dev/null || true
    print_success "Modo de manutenção desativado"
}

create_backup() {
    print_info "Criando backup antes da atualização..."
    bash "$SCRIPT_DIR/backup.sh"
    print_success "Backup criado"
}

pull_latest_code() {
    print_info "Baixando atualizações do repositório..."
    git fetch --all --prune
    CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)
    git pull --ff-only origin "$CURRENT_BRANCH"
    print_success "Código atualizado"
}

update_composer_dependencies() {
    print_info "Atualizando dependências do Composer..."
    docker run --rm -v "$ROOT_DIR":/app -w /app composer:2.7 install --no-dev --no-interaction --prefer-dist --optimize-autoloader
    print_success "Dependências atualizadas"
}

rebuild_docker_images() {
    print_info "Reconstruindo imagens Docker..."
    compose build --pull
    print_success "Imagens Docker reconstruídas"
}

run_migrations() {
    print_info "Executando migrações do banco de dados..."
    compose exec -T app bash scripts/migrate-safe.sh
    print_success "Migrações executadas"
}

clear_cache() {
    print_info "Limpando cache..."
    compose exec -T app php spark cache:clear || true
    compose exec -T redis redis-cli -a "${REDIS_PASSWORD:-redis_password}" FLUSHDB || true
    compose exec -T app rm -rf writable/cache/* || true
    print_success "Cache limpo"
}

restart_services() {
    print_info "Reiniciando serviços..."
    compose up -d --remove-orphans
    print_success "Serviços reiniciados"
    sleep 10
}

verify_update() {
    print_info "Verificando atualização..."
    compose ps
    compose exec -T app bash scripts/migrate-safe.sh --status-only >/tmp/supportponto-update-migrate.log 2>&1
    print_success "Aplicação e banco responderam após atualização"
}

rollback() {
    print_error "Erro durante a atualização!"
    disable_maintenance_mode
    exit 1
}

main() {
    trap rollback ERR
    print_header
    confirm_update
    enable_maintenance_mode
    create_backup
    pull_latest_code
    update_composer_dependencies
    rebuild_docker_images
    compose up -d
    run_migrations
    clear_cache
    restart_services
    verify_update
    disable_maintenance_mode
    print_success "Atualização concluída"
}

main "$@"
