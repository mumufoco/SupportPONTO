#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

BASE_URL="${STACK_VALIDATION_BASE_URL:-http://localhost:${APP_PORT:-80}}"
REPORT_DIR="${STACK_VALIDATION_REPORT_DIR:-$ROOT_DIR/build/runtime-validation}"
START_COMPOSE=0
STOP_AFTER=0
STRICT=0
SKIP_RUNTIME=0
SKIP_SMOKE=0
WAIT_TIMEOUT="${STACK_VALIDATION_WAIT_TIMEOUT:-180}"
TIMESTAMP="$(date '+%Y%m%d-%H%M%S')"
SUMMARY_MD=""
SUMMARY_JSON=""
pass_count=0
warn_count=0
fail_count=0
ROWS=()

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
  SUMMARY_MD="$REPORT_DIR/stack-runtime-smoke-${TIMESTAMP}.md"
  SUMMARY_JSON="$REPORT_DIR/stack-runtime-smoke-${TIMESTAMP}.json"

  {
    echo "# Stack Runtime Smoke Validation"
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
    echo "## Artefatos esperados"
    echo "- runtime-validation-*.md/json"
    echo "- critical-flows-*.md/json"
    echo "- install-doctor-*.json"
    echo "- support-diagnostics-*.json"
    echo "- release-gate-*.json"
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
    --skip-runtime)
      SKIP_RUNTIME=1; shift ;;
    --skip-smoke)
      SKIP_SMOKE=1; shift ;;
    --wait-timeout)
      WAIT_TIMEOUT="$2"; shift 2 ;;
    *)
      echo "[ERROR] Argumento desconhecido: $1" >&2
      exit 1 ;;
  esac
done

append_row PASS "Contexto" "Iniciando validação operacional consolidada do stack"

if command -v docker >/dev/null 2>&1; then
  append_row PASS "Docker" "CLI docker disponível no ambiente"
else
  append_row WARN "Docker" "CLI docker não disponível; execução real do stack não ocorrerá neste ambiente"
fi

if ! command -v docker >/dev/null 2>&1 || ! docker info >/dev/null 2>&1; then
  if [[ "$STRICT" -eq 1 ]]; then
    append_row FAIL "Daemon Docker" "Docker indisponível no ambiente; modo estrito reprovado"
    write_reports
    echo "[INFO] Relatório Markdown: $SUMMARY_MD"
    echo "[INFO] Relatório JSON: $SUMMARY_JSON"
    exit 1
  else
    append_row WARN "Daemon Docker" "Docker indisponível no ambiente; gere este relatório novamente no ambiente alvo"
  fi
fi

mkdir -p "$REPORT_DIR"

if [[ "$SKIP_RUNTIME" -eq 0 ]]; then
  runtime_args=(--base-url "$BASE_URL" --report-dir "$REPORT_DIR" --wait-timeout "$WAIT_TIMEOUT")
  [[ "$START_COMPOSE" -eq 1 ]] && runtime_args+=(--start-compose)
  [[ "$STOP_AFTER" -eq 1 ]] && runtime_args+=(--stop-after)

  if bash scripts/testing/runtime-operational-validation.sh "${runtime_args[@]}" >/tmp/supportponto-stack-runtime.log 2>&1; then
    append_row PASS "Runtime validation" "Validação operacional base executada"
  else
    if grep -qi 'docker' /tmp/supportponto-stack-runtime.log 2>/dev/null; then
      append_row WARN "Runtime validation" "Validação base não executada completamente neste ambiente; consulte /tmp/supportponto-stack-runtime.log"
    else
      append_row FAIL "Runtime validation" "Consulte /tmp/supportponto-stack-runtime.log"
    fi
  fi
else
  append_row WARN "Runtime validation" "Etapa ignorada por parâmetro"
fi

if [[ "$SKIP_SMOKE" -eq 0 ]]; then
  if bash scripts/testing/smoke-critical-flows.sh --base-url "$BASE_URL" --report-dir "$REPORT_DIR" >/tmp/supportponto-stack-smoke.log 2>&1; then
    append_row PASS "Smoke crítico" "Fluxos críticos executados"
  else
    if grep -Eqi 'docker|vendor/autoload.php ausente|node_modules/cypress ausente|Falha no GET' /tmp/supportponto-stack-smoke.log 2>/dev/null; then
      append_row WARN "Smoke crítico" "Execução incompleta neste ambiente; consulte /tmp/supportponto-stack-smoke.log"
    else
      append_row FAIL "Smoke crítico" "Consulte /tmp/supportponto-stack-smoke.log"
    fi
  fi
else
  append_row WARN "Smoke crítico" "Etapa ignorada por parâmetro"
fi

write_reports

echo "[INFO] Relatório Markdown: $SUMMARY_MD"
echo "[INFO] Relatório JSON: $SUMMARY_JSON"

if [[ "$STRICT" -eq 1 ]]; then
  [[ $fail_count -eq 0 && $warn_count -eq 0 ]]
else
  [[ $fail_count -eq 0 ]]
fi
