#!/bin/bash

set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." && pwd)
# shellcheck source=/dev/null
source "$SCRIPT_DIR/lib/compose.sh"
cd "$ROOT_DIR"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'
CHECKS_PASSED=0
CHECKS_FAILED=0

print_success() { echo -e "${GREEN}✓${NC} $1"; CHECKS_PASSED=$((CHECKS_PASSED + 1)); }
print_error() { echo -e "${RED}✗${NC} $1"; CHECKS_FAILED=$((CHECKS_FAILED + 1)); }
print_info() { echo -e "${BLUE}ℹ${NC} $1"; }
print_warning() { echo -e "${YELLOW}⚠${NC} $1"; }
print_header() { echo -e "\n${BLUE}========================================${NC}\n${BLUE}$1${NC}\n${BLUE}========================================${NC}\n"; }

print_header "Checking Docker Services"
docker info >/dev/null 2>&1 && print_success "Docker está rodando" || { print_error "Docker não está rodando"; exit 1; }
print_success "Compose disponível: $(docker compose version 2>/dev/null || docker-compose --version)"

for service in postgres redis app deepface; do
    if compose ps --status running "$service" | tail -n +2 | grep -q "$service"; then
        print_success "Serviço '$service' está rodando"
    else
        print_error "Serviço '$service' não está rodando"
    fi
done

print_header "Checking Service Health"
compose exec -T postgres pg_isready -U "${DB_USERNAME:-postgres}" -d "${DB_DATABASE:-supportponto}" >/dev/null 2>&1 && print_success "PostgreSQL saudável" || print_error "PostgreSQL indisponível"
if [ -n "${REDIS_PASSWORD:-}" ]; then
    compose exec -T redis redis-cli -a "${REDIS_PASSWORD}" ping 2>/dev/null | grep -q PONG && print_success "Redis saudável" || print_warning "Redis não respondeu ao ping autenticado"
else
    compose exec -T redis redis-cli ping 2>/dev/null | grep -q PONG && print_success "Redis saudável (sem senha)" || print_warning "Redis não respondeu ao ping"
fi
compose exec -T app php -v >/dev/null 2>&1 && print_success "PHP disponível no container app" || print_error "PHP indisponível no container app"
compose exec -T app nginx -t >/dev/null 2>&1 && print_success "Nginx válido" || print_error "Configuração do Nginx inválida"
curl -fsS http://localhost:5000/health >/dev/null 2>&1 && print_success "DeepFace saudável" || print_warning "DeepFace não respondeu ao health check"
APP_STATUS=$(curl -fsS http://localhost:80/health/readiness 2>/dev/null | grep -o '"status":"[^"]*' | head -n1 | cut -d'"' -f4 || true)
[ "${APP_STATUS:-}" = "ready" ] && print_success "Aplicação reportou status ready" || print_warning "Aplicação não reportou status ready (${APP_STATUS:-desconhecido})"

print_header "Checking Application Health"
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:80/health/readiness 2>/dev/null || echo "000")
[[ "$HTTP_CODE" =~ ^200$ ]] && print_success "Aplicação pronta para tráfego (HTTP $HTTP_CODE)" || print_error "Aplicação não pronta para tráfego (HTTP $HTTP_CODE)"
if compose exec -T app bash scripts/migrate-safe.sh --status-only >/tmp/supportponto-health-migrate.log 2>&1; then
    print_success "Conexão com banco OK"
else
    print_error "Falha na verificação de banco (consulte /tmp/supportponto-health-migrate.log)"
fi
for dir in writable/cache writable/logs writable/session writable/uploads; do
    compose exec -T app test -w "$dir" 2>/dev/null && print_success "Diretório '$dir' gravável" || print_error "Diretório '$dir' sem permissão de escrita"
done

print_header "Summary"
TOTAL_CHECKS=$((CHECKS_PASSED + CHECKS_FAILED))
echo "Total checks: $TOTAL_CHECKS"
print_success "Passed: $CHECKS_PASSED"
if [ "$CHECKS_FAILED" -gt 0 ]; then
    print_error "Failed: $CHECKS_FAILED"
    exit 1
fi
