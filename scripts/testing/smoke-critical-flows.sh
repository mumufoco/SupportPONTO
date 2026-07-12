#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

BASE_URL="${CRITICAL_SMOKE_BASE_URL:-http://localhost:8080}"
REPORT_DIR="${CRITICAL_SMOKE_REPORT_DIR:-$ROOT_DIR/build/smoke}"
REPORT_BASENAME="${CRITICAL_SMOKE_REPORT_BASENAME:-critical-flows}"
RUN_CONNECTION_CHECKS=1
RUN_CYPRESS=1
RUN_PHPUNIT=1
CYPRESS_SPEC="cypress/e2e/smoke-critical-flows.cy.js"

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
    --skip-phpunit)
      RUN_PHPUNIT=0; shift ;;
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
    echo "# Smoke Test — Fluxos Críticos"
    echo
    echo "- Base URL: \`$BASE_URL\`"
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
      echo "Aprovado nos smoke tests dos fluxos críticos executados."
    else
      echo "## Resultado"
      echo "Reprovado. Há falhas nos fluxos críticos antes do go-live."
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

append_row PASS "Contexto" "Iniciando smoke test dos fluxos críticos"

if [[ $RUN_CONNECTION_CHECKS -eq 1 ]]; then
  if bash scripts/testing/runtime-operational-validation.sh --base-url "$BASE_URL" --no-save >/tmp/supportponto-critical-runtime.log 2>&1; then
    append_row PASS "Runtime validation" "Validação operacional base executada"
  else
    append_row FAIL "Runtime validation" "Consulte /tmp/supportponto-critical-runtime.log"
  fi
else
  if bash scripts/testing/runtime-operational-validation.sh --base-url "$BASE_URL" --no-connections --no-save >/tmp/supportponto-critical-runtime.log 2>&1; then
    append_row PASS "Runtime validation" "Validação operacional base executada sem conexões"
  else
    append_row FAIL "Runtime validation" "Consulte /tmp/supportponto-critical-runtime.log"
  fi
fi

if [[ $RUN_PHPUNIT -eq 1 ]]; then
  if [[ -f vendor/autoload.php ]]; then
    if bash scripts/testing/run-phpunit.sh --filter 'AuthenticationFlowTest|TimePunchFlowTest|DashboardIntegrationTest|ReportGenerationTest|WarningsRoutingRefactorTest' >/tmp/supportponto-critical-phpunit.log 2>&1; then
      append_row PASS "PHPUnit critical" "Fluxos críticos de autenticação, ponto, dashboard e relatórios executados"
    else
      append_row FAIL "PHPUnit critical" "Consulte /tmp/supportponto-critical-phpunit.log"
    fi
  else
    append_row WARN "PHPUnit critical" "vendor/autoload.php ausente; etapa ignorada"
  fi
else
  append_row WARN "PHPUnit critical" "Etapa ignorada por parâmetro"
fi

if [[ $RUN_CYPRESS -eq 1 ]]; then
  if [[ -d node_modules/cypress ]]; then
    CYPRESS_BASE_URL="$BASE_URL" npx cypress run --spec "$CYPRESS_SPEC" >/tmp/supportponto-critical-cypress.log 2>&1 && append_row PASS "Cypress critical" "Spec $CYPRESS_SPEC executada" || append_row FAIL "Cypress critical" "Consulte /tmp/supportponto-critical-cypress.log"
  else
    append_row WARN "Cypress critical" "node_modules/cypress ausente; execute npm ci antes do smoke browser"
  fi
else
  append_row WARN "Cypress critical" "Etapa ignorada por parâmetro"
fi

write_reports

echo "[INFO] Relatório Markdown: $MD_REPORT"
echo "[INFO] Relatório JSON: $JSON_REPORT"

[[ $fail_count -eq 0 ]]
