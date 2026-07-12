#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

BASE_URL="${SMOKE_BASE_URL:-http://localhost:8080}"
REPORT_DIR="${SMOKE_REPORT_DIR:-$ROOT_DIR/build/smoke}"
REPORT_BASENAME="${SMOKE_REPORT_BASENAME:-install-first-use}"
RUN_CONNECTION_CHECKS=1
RUN_CYPRESS=1
RUN_PREPARE=1
RUN_PHPUNIT=1
CYPRESS_SPEC="cypress/e2e/smoke-install-first-use.cy.js"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --base-url)
      BASE_URL="$2"; shift 2 ;;
    --report-dir)
      REPORT_DIR="$2"; shift 2 ;;
    --skip-connections)
      RUN_CONNECTION_CHECKS=0; shift ;;
    --skip-cypress)
      RUN_CYPRESS=0; shift ;;
    --skip-prepare)
      RUN_PREPARE=0; shift ;;
    --skip-phpunit)
      RUN_PHPUNIT=0; shift ;;
    --cypress-spec)
      CYPRESS_SPEC="$2"; shift 2 ;;
    *)
      echo "[ERROR] Argumento desconhecido: $1" >&2
      exit 1 ;;
  esac
 done

mkdir -p "$REPORT_DIR"
TIMESTAMP="$(date '+%Y%m%d-%H%M%S')"
JSON_REPORT="$REPORT_DIR/${REPORT_BASENAME}-${TIMESTAMP}.json"
MD_REPORT="$REPORT_DIR/${REPORT_BASENAME}-${TIMESTAMP}.md"

pass_count=0
warn_count=0
fail_count=0
REPORT_ROWS=()

append_row() {
  local status="$1"; shift
  local label="$1"; shift
  local details="$*"
  REPORT_ROWS+=("$status|$label|$details")
  case "$status" in
    PASS) pass_count=$((pass_count + 1)) ;;
    WARN) warn_count=$((warn_count + 1)) ;;
    FAIL) fail_count=$((fail_count + 1)) ;;
  esac
  printf '[%s] %s - %s\n' "$status" "$label" "$details"
}


json_escape() {
  python3 - <<'PY' "$1"
import json, sys
print(json.dumps(sys.argv[1], ensure_ascii=False))
PY
}

write_reports() {
  {
    echo "# Smoke Test — Instalação ao Primeiro Uso"
    echo
    echo "- Base URL: \\`$BASE_URL\\`"
    echo "- Gerado em: $(date -Is)"
    echo "- Aprovados: $pass_count"
    echo "- Avisos: $warn_count"
    echo "- Falhas: $fail_count"
    echo
    echo "| Status | Etapa | Detalhes |"
    echo "|---|---|---|"
    for row in "${REPORT_ROWS[@]}"; do
      IFS='|' read -r status label details <<<"$row"
      echo "| $status | $label | ${details//|/\\|} |"
    done
    echo
    if [[ $fail_count -eq 0 ]]; then
      echo "## Resultado"
      echo "Aprovado nos smoke tests executados."
    else
      echo "## Resultado"
      echo "Reprovado. Há falhas que precisam ser corrigidas antes do go-live."
    fi
  } > "$MD_REPORT"

  {
    echo '{'
    echo "  \"base_url\": $(json_escape "$BASE_URL"),"
    echo "  \"generated_at\": $(json_escape "$(date -Is)"),"
    echo "  \"passed\": $pass_count,"
    echo "  \"warnings\": $warn_count,"
    echo "  \"failed\": $fail_count,"
    echo '  "checks": ['
    local first=1
    for row in "${REPORT_ROWS[@]}"; do
      IFS='|' read -r status label details <<<"$row"
      [[ $first -eq 0 ]] && echo ','
      first=0
      echo -n "    {\"status\": $(json_escape "$status"), \"label\": $(json_escape "$label"), \"details\": $(json_escape "$details")}"
    done
    echo
    echo '  ]'
    echo '}'
  } > "$JSON_REPORT"
}

append_row PASS "Contexto" "Iniciando smoke test de instalação ao primeiro uso"

if [[ $RUN_CONNECTION_CHECKS -eq 1 ]]; then
  if bash scripts/install-doctor.sh >/tmp/supportponto-smoke-doctor.log 2>&1; then
    append_row PASS "Pré-check shell" "scripts/install-doctor.sh executado com sucesso"
  else
    append_row FAIL "Pré-check shell" "Consulte /tmp/supportponto-smoke-doctor.log"
  fi
else
  if bash scripts/install-doctor.sh --no-connections >/tmp/supportponto-smoke-doctor.log 2>&1; then
    append_row PASS "Pré-check shell" "scripts/install-doctor.sh --no-connections executado com sucesso"
  else
    append_row FAIL "Pré-check shell" "Consulte /tmp/supportponto-smoke-doctor.log"
  fi
fi

if [[ -f vendor/autoload.php ]]; then
  if [[ $RUN_CONNECTION_CHECKS -eq 1 ]]; then
    php spark install:doctor --json > "$REPORT_DIR/install-doctor-${TIMESTAMP}.json" && append_row PASS "Pré-check CI4" "php spark install:doctor --json executado" || append_row FAIL "Pré-check CI4" "Falha ao executar php spark install:doctor"
    php spark support:migrations-audit --json --save > "$REPORT_DIR/migrations-audit-${TIMESTAMP}.json" && append_row PASS "Auditoria de migrations" "php spark support:migrations-audit --json --save executado" || append_row FAIL "Auditoria de migrations" "Falha ao executar php spark support:migrations-audit"
    php spark support:schema-audit --json --save > "$REPORT_DIR/schema-audit-${TIMESTAMP}.json" && append_row PASS "Auditoria de schema" "php spark support:schema-audit --json --save executado" || append_row FAIL "Auditoria de schema" "Falha ao executar php spark support:schema-audit"
    php spark support:config-audit --json --save > "$REPORT_DIR/config-audit-${TIMESTAMP}.json" && append_row PASS "Auditoria de configuração" "php spark support:config-audit --json --save executado" || append_row FAIL "Auditoria de configuração" "Falha ao executar php spark support:config-audit"
    bash scripts/migrate-safe.sh --status-only >/tmp/supportponto-smoke-migrate-status.log 2>&1 && append_row PASS "Status do migrate" "bash scripts/migrate-safe.sh --status-only executado" || append_row FAIL "Status do migrate" "Consulte /tmp/supportponto-smoke-migrate-status.log"
  else
    php spark install:doctor --json --no-connections > "$REPORT_DIR/install-doctor-${TIMESTAMP}.json" && append_row PASS "Pré-check CI4" "php spark install:doctor --json --no-connections executado" || append_row FAIL "Pré-check CI4" "Falha ao executar php spark install:doctor --no-connections"
    php spark support:migrations-audit --json --save --no-connections > "$REPORT_DIR/migrations-audit-${TIMESTAMP}.json" && append_row PASS "Auditoria de migrations" "php spark support:migrations-audit --json --save --no-connections executado" || append_row FAIL "Auditoria de migrations" "Falha ao executar php spark support:migrations-audit --no-connections"
    php spark support:schema-audit --json --save --no-connections > "$REPORT_DIR/schema-audit-${TIMESTAMP}.json" && append_row PASS "Auditoria de schema" "php spark support:schema-audit --json --save --no-connections executado" || append_row FAIL "Auditoria de schema" "Falha ao executar php spark support:schema-audit --no-connections"
    php spark support:config-audit --json --save > "$REPORT_DIR/config-audit-${TIMESTAMP}.json" && append_row PASS "Auditoria de configuração" "php spark support:config-audit --json --save executado" || append_row FAIL "Auditoria de configuração" "Falha ao executar php spark support:config-audit"
    bash scripts/migrate-safe.sh --status-only >/tmp/supportponto-smoke-migrate-status.log 2>&1 && append_row PASS "Status do migrate" "bash scripts/migrate-safe.sh --status-only executado" || append_row FAIL "Status do migrate" "Consulte /tmp/supportponto-smoke-migrate-status.log"
  fi

  if [[ $RUN_PREPARE -eq 1 ]]; then
    php spark e2e:prepare >/tmp/supportponto-smoke-e2e.log 2>&1 && append_row PASS "Dataset E2E" "php spark e2e:prepare executado" || append_row FAIL "Dataset E2E" "Consulte /tmp/supportponto-smoke-e2e.log"
  else
    append_row WARN "Dataset E2E" "Etapa ignorada por parâmetro"
  fi
else
  append_row WARN "Pré-check CI4" "vendor/autoload.php ausente; etapa php spark não executada"
  append_row WARN "Dataset E2E" "vendor/autoload.php ausente; etapa php spark não executada"
fi

if curl -fsS "$BASE_URL/health" >/tmp/supportponto-smoke-health.json 2>/tmp/supportponto-smoke-health.err; then
  append_row PASS "Liveness endpoint" "GET $BASE_URL/health respondeu com sucesso"
else
  append_row FAIL "Liveness endpoint" "Falha no GET $BASE_URL/health"
fi

if curl -fsS "$BASE_URL/health/liveness" >/tmp/supportponto-smoke-liveness.json 2>/tmp/supportponto-smoke-liveness.err; then
  append_row PASS "Health liveness endpoint" "GET $BASE_URL/health/liveness respondeu com sucesso"
else
  append_row FAIL "Health liveness endpoint" "Falha no GET $BASE_URL/health/liveness"
fi

if curl -fsS "$BASE_URL/health/readiness" >/tmp/supportponto-smoke-readiness.json 2>/tmp/supportponto-smoke-readiness.err; then
  append_row PASS "Readiness endpoint" "GET $BASE_URL/health/readiness respondeu com sucesso"
else
  append_row FAIL "Readiness endpoint" "Falha no GET $BASE_URL/health/readiness"
fi

LOGIN_CODE="$(curl -s -o /dev/null -w '%{http_code}' "$BASE_URL/auth/login" || true)"
if [[ "$LOGIN_CODE" =~ ^(200|302)$ ]]; then
  append_row PASS "Tela de login" "GET $BASE_URL/auth/login retornou HTTP $LOGIN_CODE"
else
  append_row FAIL "Tela de login" "HTTP $LOGIN_CODE em $BASE_URL/auth/login"
fi

if [[ $RUN_PHPUNIT -eq 1 ]]; then
  if [[ -f vendor/autoload.php ]]; then
    if bash scripts/testing/run-phpunit.sh --filter 'HealthEndpointsTest|AuthenticationFlowTest|TimePunchFlowTest' >/tmp/supportponto-smoke-phpunit.log 2>&1; then
      append_row PASS "PHPUnit smoke" "HealthEndpointsTest, AuthenticationFlowTest e TimePunchFlowTest executados"
    else
      append_row FAIL "PHPUnit smoke" "Consulte /tmp/supportponto-smoke-phpunit.log"
    fi
  else
    append_row WARN "PHPUnit smoke" "vendor/autoload.php ausente; etapa ignorada"
  fi
else
  append_row WARN "PHPUnit smoke" "Etapa ignorada por parâmetro"
fi

if [[ $RUN_CYPRESS -eq 1 ]]; then
  if [[ -d node_modules/cypress ]]; then
    CYPRESS_BASE_URL="$BASE_URL" npx cypress run --spec "$CYPRESS_SPEC" >/tmp/supportponto-smoke-cypress.log 2>&1 && append_row PASS "Cypress smoke" "Spec $CYPRESS_SPEC executada" || append_row FAIL "Cypress smoke" "Consulte /tmp/supportponto-smoke-cypress.log"
  else
    append_row WARN "Cypress smoke" "node_modules/cypress ausente; execute npm ci antes do smoke browser"
  fi
else
  append_row WARN "Cypress smoke" "Etapa ignorada por parâmetro"
fi

write_reports

echo "[INFO] Relatório Markdown: $MD_REPORT"
echo "[INFO] Relatório JSON: $JSON_REPORT"

[[ $fail_count -eq 0 ]]
