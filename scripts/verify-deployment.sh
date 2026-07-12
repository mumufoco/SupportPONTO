#!/bin/bash

set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
ROOT_DIR=$(cd "$SCRIPT_DIR/.." && pwd)
# shellcheck source=/dev/null
source "$SCRIPT_DIR/lib/compose.sh"
cd "$ROOT_DIR"

ENVIRONMENT=${1:-production}
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'
TESTS_PASSED=0
TESTS_FAILED=0

case "$ENVIRONMENT" in
  staging) BASE_URL="${VERIFY_BASE_URL:-https://staging.pontoeletronico.com.br}" ;;
  production) BASE_URL="${VERIFY_BASE_URL:-https://pontoeletronico.com.br}" ;;
  *) echo -e "${RED}Error: invalid environment '$ENVIRONMENT'${NC}"; exit 1 ;;
esac

print_success() { echo -e "${GREEN}✓${NC} $1"; TESTS_PASSED=$((TESTS_PASSED + 1)); }
print_error() { echo -e "${RED}✗${NC} $1"; TESTS_FAILED=$((TESTS_FAILED + 1)); }
print_warning() { echo -e "${YELLOW}⚠${NC} $1"; }
print_info() { echo -e "${BLUE}ℹ${NC} $1"; }
print_header() { echo -e "\n${BLUE}========================================${NC}\n${BLUE}$1${NC}\n${BLUE}========================================${NC}\n"; }

check_http() {
    local url=$1 expected=$2 label=$3
    local code
    code=$(curl -k -s -o /dev/null -w '%{http_code}' "$url" 2>/dev/null || echo 000)
    if [[ "$code" =~ $expected ]]; then
        print_success "$label (HTTP $code)"
    else
        print_error "$label (HTTP $code)"
    fi
}

print_header "Verifying Web Application - $ENVIRONMENT"
print_info "Base URL: $BASE_URL"
check_http "$BASE_URL/" '^(200|302)$' 'Homepage acessível'
check_http "$BASE_URL/login" '^200$' 'Página de login acessível'
check_http "$BASE_URL/health" '^200$' 'Endpoint /health (liveness) acessível'
check_http "$BASE_URL/health/liveness" '^200$' 'Endpoint /health/liveness acessível'
check_http "$BASE_URL/health/readiness" '^200$' 'Endpoint /health/readiness acessível'

print_header "Verifying Security Headers"
RESPONSE=$(curl -k -s -I "$BASE_URL" 2>/dev/null || true)
echo "$RESPONSE" | grep -qi 'X-Frame-Options' && print_success 'Header X-Frame-Options presente' || print_warning 'Header X-Frame-Options ausente'
echo "$RESPONSE" | grep -qi 'X-Content-Type-Options' && print_success 'Header X-Content-Type-Options presente' || print_warning 'Header X-Content-Type-Options ausente'
echo "$RESPONSE" | grep -qi 'Referrer-Policy' && print_success 'Header Referrer-Policy presente' || print_warning 'Header Referrer-Policy ausente'

print_header "Verifying Local Containers"
for service in postgres redis app deepface; do
    if compose ps --status running "$service" | tail -n +2 | grep -q "$service"; then
        print_success "Serviço '$service' está rodando"
    else
        print_warning "Serviço '$service' não está rodando localmente"
    fi
done

print_header "Summary"
echo "Passed: $TESTS_PASSED"
echo "Failed: $TESTS_FAILED"
[ "$TESTS_FAILED" -eq 0 ]
