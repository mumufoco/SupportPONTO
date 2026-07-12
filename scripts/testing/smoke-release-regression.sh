#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

BASE_URL="${RELEASE_REGRESSION_BASE_URL:-http://localhost:${APP_PORT:-80}}"
REPORT_DIR="${RELEASE_REGRESSION_REPORT_DIR:-$ROOT_DIR/build/release-regression}"
START_COMPOSE=0
STOP_AFTER=0
STRICT=0
SKIP_CONNECTIONS=0
SKIP_PHPUNIT=0
SKIP_CYPRESS=0
TIMESTAMP="$(date '+%Y%m%d-%H%M%S')"
SUMMARY_MD=""
SUMMARY_JSON=""
pass_count=0
warn_count=0
fail_count=0
ROWS=()
CYPRESS_SPECS=(
  "cypress/e2e/smoke-install-first-use.cy.js"
  "cypress/e2e/smoke-critical-flows.cy.js"
  "cypress/e2e/punch-admin-settings.cy.js"
)
PHPUNIT_FILTER='Package372ATwoFactorCoverageStaticTest|Package372DSettingsImportHardeningStaticTest|Package373BRateLimitHardeningStaticTest|Package373CErrorSanitizationStaticTest|Package373DPublicEndpointHardeningStaticTest|Package374CCspControlledHardeningStaticTest|Package375AHealthLayeringStaticTest|Package375BDependencyCompatibilityStaticTest|AuthenticationAndAccessTest|PublicSurfaceHardeningTest|HealthEndpointsTest|SettingsCanonicalRouteTest'

append_row() {
  local status="$1"; shift
  local label="$1"; shift
  local details="$*"
  ROWS+=("$status|$label|$details")
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
  mkdir -p "$REPORT_DIR"
  SUMMARY_MD="$REPORT_DIR/release-regression-${TIMESTAMP}.md"
  SUMMARY_JSON="$REPORT_DIR/release-regression-${TIMESTAMP}.json"

  {
    echo "# Release Regression Smoke"
    echo
    echo "- Base URL: \`$BASE_URL\`"
    echo "- Gerado em: $(date -Is)"
    echo "- Aprovados: $pass_count"
    echo "- Avisos: $warn_count"
    echo "- Falhas: $fail_count"
    echo
    echo "| Status | Etapa | Detalhes |"
    echo "|---|---|---|"
    for row in "${ROWS[@]}"; do
      IFS='|' read -r status label details <<<"$row"
      echo "| $status | $label | ${details//|/\\|} |"
    done
    echo
    echo "## Cobertura"
    echo "- runtime, liveness e readiness"
    echo "- autenticação, 2FA e superfície pública endurecida"
    echo "- settings/admin e rotas canônicas"
    echo "- smoke browser de instalação/uso inicial, fluxos críticos e painel de ponto"
  } > "$SUMMARY_MD"

  {
    echo '{'
    echo "  \"base_url\": $(json_escape "$BASE_URL"),"
    echo "  \"generated_at\": $(json_escape "$(date -Is)"),"
    echo "  \"passed\": $pass_count,"
    echo "  \"warnings\": $warn_count,"
    echo "  \"failed\": $fail_count,"
    echo '  "checks": ['
    local first=1
    for row in "${ROWS[@]}"; do
      IFS='|' read -r status label details <<<"$row"
      [[ $first -eq 0 ]] && echo ','
      first=0
      echo -n "    {\"status\": $(json_escape "$status"), \"label\": $(json_escape "$label"), \"details\": $(json_escape "$details")}" 
    done
    echo
    echo '  ]'
    echo '}'
  } > "$SUMMARY_JSON"
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --base-url)
      BASE_URL="$2"; shift 2 ;;
    --report-dir)
      REPORT_DIR="$2"; shift 2 ;;
    --start-compose)
      START_COMPOSE=1; shift ;;
    --stop-after)
      STOP_AFTER=1; shift ;;
    --strict)
      STRICT=1; shift ;;
    --skip-connections)
      SKIP_CONNECTIONS=1; shift ;;
    --skip-phpunit)
      SKIP_PHPUNIT=1; shift ;;
    --skip-cypress)
      SKIP_CYPRESS=1; shift ;;
    *)
      echo "[ERROR] Argumento desconhecido: $1" >&2
      exit 1 ;;
  esac
done

append_row PASS "Contexto" "Iniciando regressão pós-release consolidada"

runtime_args=(--base-url "$BASE_URL" --report-dir "$REPORT_DIR")
[[ "$START_COMPOSE" -eq 1 ]] && runtime_args+=(--start-compose)
[[ "$STOP_AFTER" -eq 1 ]] && runtime_args+=(--stop-after)
[[ "$SKIP_CONNECTIONS" -eq 1 ]] && runtime_args+=(--no-connections)

if bash scripts/testing/runtime-operational-validation.sh "${runtime_args[@]}" >/tmp/supportponto-release-runtime.log 2>&1; then
  append_row PASS "Runtime validation" "Validação operacional base executada"
else
  if grep -Eqi 'docker|vendor/autoload.php ausente|Falha no GET' /tmp/supportponto-release-runtime.log 2>/dev/null; then
    append_row WARN "Runtime validation" "Execução incompleta neste ambiente; consulte /tmp/supportponto-release-runtime.log"
  else
    append_row FAIL "Runtime validation" "Consulte /tmp/supportponto-release-runtime.log"
  fi
fi

critical_args=(--base-url "$BASE_URL" --report-dir "$REPORT_DIR")
[[ "$SKIP_CONNECTIONS" -eq 1 ]] && critical_args+=(--skip-connections)
[[ "$SKIP_CYPRESS" -eq 1 ]] && critical_args+=(--skip-cypress)
[[ "$SKIP_PHPUNIT" -eq 1 ]] && critical_args+=(--skip-phpunit)

if bash scripts/testing/smoke-critical-flows.sh "${critical_args[@]}" >/tmp/supportponto-release-critical.log 2>&1; then
  append_row PASS "Smoke crítico" "Fluxos críticos consolidados executados"
else
  if grep -Eqi 'docker|vendor/autoload.php ausente|node_modules/cypress ausente|Falha no GET' /tmp/supportponto-release-critical.log 2>/dev/null; then
    append_row WARN "Smoke crítico" "Execução incompleta neste ambiente; consulte /tmp/supportponto-release-critical.log"
  else
    append_row FAIL "Smoke crítico" "Consulte /tmp/supportponto-release-critical.log"
  fi
fi

if [[ "$SKIP_PHPUNIT" -eq 0 ]]; then
  if [[ -f vendor/autoload.php ]]; then
    if bash scripts/testing/run-phpunit.sh --filter "$PHPUNIT_FILTER" >/tmp/supportponto-release-phpunit.log 2>&1; then
      append_row PASS "PHPUnit regressão" "Suite focal de auth, settings, superfície pública e hardening executada"
    else
      append_row FAIL "PHPUnit regressão" "Consulte /tmp/supportponto-release-phpunit.log"
    fi
  else
    append_row WARN "PHPUnit regressão" "vendor/autoload.php ausente; etapa ignorada"
  fi
else
  append_row WARN "PHPUnit regressão" "Etapa ignorada por parâmetro"
fi

if [[ "$SKIP_CYPRESS" -eq 0 ]]; then
  if [[ -d node_modules/cypress ]]; then
    for spec in "${CYPRESS_SPECS[@]}"; do
      if CYPRESS_BASE_URL="$BASE_URL" npx cypress run --spec "$spec" >/tmp/supportponto-release-cypress.log 2>&1; then
        append_row PASS "Cypress regressão" "Spec $spec executada"
      else
        append_row FAIL "Cypress regressão" "Consulte /tmp/supportponto-release-cypress.log"
        break
      fi
    done
  else
    append_row WARN "Cypress regressão" "node_modules/cypress ausente; execute npm ci antes do smoke browser"
  fi
else
  append_row WARN "Cypress regressão" "Etapa ignorada por parâmetro"
fi

write_reports

echo "[INFO] Relatório Markdown: $SUMMARY_MD"
echo "[INFO] Relatório JSON: $SUMMARY_JSON"

if [[ "$STRICT" -eq 1 ]]; then
  [[ $fail_count -eq 0 && $warn_count -eq 0 ]]
else
  [[ $fail_count -eq 0 ]]
fi
