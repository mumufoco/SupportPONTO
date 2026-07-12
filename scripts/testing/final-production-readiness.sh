#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

REPORT_DIR="${FINAL_READINESS_REPORT_DIR:-$ROOT_DIR/build/final-production-readiness}"
STRICT=0
RUN_SMOKE=0
WITH_CONNECTIONS=1
TIMESTAMP="$(date '+%Y%m%d-%H%M%S')"
SUMMARY_MD="$REPORT_DIR/final-production-readiness-${TIMESTAMP}.md"
SUMMARY_JSON="$REPORT_DIR/final-production-readiness-${TIMESTAMP}.json"
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
  python3 - "$1" <<'PYJSON'
import json, sys
print(json.dumps(sys.argv[1], ensure_ascii=False))
PYJSON
}

write_reports() {
  mkdir -p "$REPORT_DIR"
  {
    echo "# Final Production Readiness"
    echo
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
    echo "- auditoria do source package e release manifesto"
    echo "- baseline de dependências e consistência de toolchain"
    echo "- gate final de go-live documentado em scripts/testing/go-live-gate.sh"
    echo "- smoke browser/runtime opcional quando o ambiente estiver completo"
  } > "$SUMMARY_MD"

  {
    echo '{'
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

run_step() {
  local label="$1"; shift
  local log="$REPORT_DIR/${TIMESTAMP}-$(echo "$label" | tr '[:upper:]' '[:lower:]' | tr ' /' '__').log"
  if "$@" >"$log" 2>&1; then
    append_row PASS "$label" "Executado com sucesso"
  else
    append_row FAIL "$label" "Consulte $log"
  fi
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --report-dir)
      REPORT_DIR="$2"; shift 2 ;;
    --strict)
      STRICT=1; shift ;;
    --run-smoke)
      RUN_SMOKE=1; shift ;;
    --no-connections)
      WITH_CONNECTIONS=0; shift ;;
    *)
      echo "[ERROR] Argumento desconhecido: $1" >&2
      exit 1 ;;
  esac
done

mkdir -p "$REPORT_DIR"
append_row PASS "Contexto" "Iniciando validação final integrada"

run_step "Source package audit" bash scripts/testing/source-package-audit.sh
run_step "Release audit" bash scripts/testing/release-audit.sh
run_step "Dependency baseline" bash scripts/testing/dependency-baseline-check.sh
run_step "Toolchain consistency" bash scripts/testing/build-toolchain-consistency.sh
run_step "Go-live gate reference" grep -q 'go-live-gate.sh' docs/operations/GO_LIVE_SMOKE_MATRIX_CURRENT.md

if [[ ! -f vendor/autoload.php ]]; then
  append_row WARN "Runtime mode" "vendor/autoload.php ausente; prontidão final validada em modo source-package"
else
  GATE_ARGS=()
  [[ "$WITH_CONNECTIONS" -eq 0 ]] && GATE_ARGS+=(--no-connections)
  [[ "$RUN_SMOKE" -eq 1 ]] && GATE_ARGS+=(--run-smoke)
  [[ "$STRICT" -eq 1 ]] && GATE_ARGS+=(--strict)
  run_step "Go-live gate" bash scripts/testing/go-live-gate.sh "${GATE_ARGS[@]}"
fi

write_reports

echo "[INFO] Relatório Markdown: $SUMMARY_MD"
echo "[INFO] Relatório JSON: $SUMMARY_JSON"

if [[ "$STRICT" -eq 1 ]]; then
  [[ $fail_count -eq 0 && $warn_count -eq 0 ]]
else
  [[ $fail_count -eq 0 ]]
fi
