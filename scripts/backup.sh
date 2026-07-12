#!/bin/bash

set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." && pwd)
# shellcheck source=/dev/null
source "$SCRIPT_DIR/lib/compose.sh"
cd "$ROOT_DIR"

BACKUP_DIR="./writable/backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_NAME="ponto_backup_${TIMESTAMP}"
RETENTION_DAYS=30
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

print_success() { echo -e "${GREEN}✓ $1${NC}"; }
print_info() { echo -e "${YELLOW}→ $1${NC}"; }
print_error() { echo -e "${RED}✗ $1${NC}"; }

create_backup_dir() { mkdir -p "${BACKUP_DIR}/${BACKUP_NAME}"; print_success "Diretório criado: ${BACKUP_DIR}/${BACKUP_NAME}"; }

backup_database() {
    print_info "Fazendo backup do banco de dados..."
    DB_NAME=$(grep "^database.default.database" .env | cut -d '=' -f2 | tr -d " '")
    DB_USER=$(grep "^database.default.username" .env | cut -d '=' -f2 | tr -d " '")
    DB_PASS=$(grep "^database.default.password" .env | cut -d '=' -f2 | tr -d " '")
    compose exec -T -e PGPASSWORD="${DB_PASS}" postgres pg_dump -U "${DB_USER}" -d "${DB_NAME}" --clean --if-exists --no-owner --no-privileges > "${BACKUP_DIR}/${BACKUP_NAME}/database.sql"
    gzip "${BACKUP_DIR}/${BACKUP_NAME}/database.sql"
    print_success "Backup do banco concluído"
}

backup_files() {
    print_info "Fazendo backup de arquivos..."
    tar -czf "${BACKUP_DIR}/${BACKUP_NAME}/writable.tar.gz" --exclude="${BACKUP_DIR}" writable/ 2>/dev/null || true
    [ -d public/uploads ] && tar -czf "${BACKUP_DIR}/${BACKUP_NAME}/uploads.tar.gz" public/uploads/ 2>/dev/null || true
    print_success "Backup de arquivos concluído"
}

backup_deepface() {
    print_info "Fazendo backup de biometrias faciais..."
    compose exec -T deepface tar -czf /tmp/faces_backup.tar.gz /app/faces 2>/dev/null || true
    docker cp "$(compose ps -q deepface)":/tmp/faces_backup.tar.gz "${BACKUP_DIR}/${BACKUP_NAME}/faces.tar.gz" 2>/dev/null || true
    print_success "Backup de biometrias concluído"
}

backup_config() {
    cp .env "${BACKUP_DIR}/${BACKUP_NAME}/.env.backup"
    cp docker-compose.yml "${BACKUP_DIR}/${BACKUP_NAME}/docker-compose.yml.backup"
    print_success "Backup de configurações concluído"
}

create_manifest() {
    cat > "${BACKUP_DIR}/${BACKUP_NAME}/manifest.txt" <<EOF
Data: $(date)
Conteúdo: database.sql.gz, writable.tar.gz, uploads.tar.gz, faces.tar.gz, .env.backup, docker-compose.yml.backup
Tamanho: $(du -sh "${BACKUP_DIR}/${BACKUP_NAME}" | cut -f1)
EOF
    print_success "Manifesto criado"
}

compress_backup() {
    (cd "${BACKUP_DIR}" && tar -czf "${BACKUP_NAME}.tar.gz" "${BACKUP_NAME}/" && rm -rf "${BACKUP_NAME}/")
    print_success "Backup final: ${BACKUP_NAME}.tar.gz"
}

cleanup_old_backups() {
    find "${BACKUP_DIR}" -name 'ponto_backup_*.tar.gz' -mtime +${RETENTION_DAYS} -delete
    print_success "Retenção aplicada (${RETENTION_DAYS} dias)"
}

main() {
    compose ps >/dev/null 2>&1 || { print_error "Ambiente Docker não está disponível"; exit 1; }
    create_backup_dir
    backup_database
    backup_files
    backup_deepface
    backup_config
    create_manifest
    compress_backup
    cleanup_old_backups
    print_success "Backup concluído: ${BACKUP_DIR}/${BACKUP_NAME}.tar.gz"
}

main "$@"
