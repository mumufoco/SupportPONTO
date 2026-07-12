#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

WITH_CONNECTIONS=1
RUN_SMOKE=0
SAVE=1
STRICT=0
REPORT_DIR="${GO_LIVE_GATE_REPORT_DIR:-$ROOT_DIR/build/go-live-gate}"
TIMESTAMP="$(date '+%Y%m%d-%H%M%S')"
JSON_REPORT="$REPORT_DIR/go-live-gate-${TIMESTAMP}.json"
MD_REPORT="$REPORT_DIR/go-live-gate-${TIMESTAMP}.md"
ARTIFACT_MODE="runtime"
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
    echo "# Go-Live Gate"
    echo
    echo "- Modo: $ARTIFACT_MODE"
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
    echo "## Resultado"
    if [[ $fail_count -eq 0 && $warn_count -eq 0 ]]; then
      echo "Gate aprovado sem ressalvas."
    elif [[ $fail_count -eq 0 ]]; then
      echo "Gate aprovado com ressalvas documentadas."
    else
      echo "Gate reprovado. Corrija as falhas antes do go-live."
    fi
  } > "$MD_REPORT"

  {
    echo '{'
    echo "  \"artifact_mode\": $(json_escape "$ARTIFACT_MODE"),"
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
  } > "$JSON_REPORT"
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

run_warnable_step() {
  local label="$1"; shift
  local log="$REPORT_DIR/${TIMESTAMP}-$(echo "$label" | tr '[:upper:]' '[:lower:]' | tr ' /' '__').log"
  if "$@" >"$log" 2>&1; then
    append_row PASS "$label" "Executado com sucesso"
  else
    append_row WARN "$label" "Execução incompleta neste ambiente; consulte $log"
  fi
}

for arg in "$@"; do
  case "$arg" in
    --no-connections)
      WITH_CONNECTIONS=0 ;;
    --run-smoke)
      RUN_SMOKE=1 ;;
    --no-save)
      SAVE=0 ;;
    --strict)
      STRICT=1 ;;
    *) ;;
  esac
done

if [[ "$SAVE" -ne 1 ]]; then
  REPORT_DIR="$(mktemp -d)"
  JSON_REPORT="$REPORT_DIR/go-live-gate-${TIMESTAMP}.json"
  MD_REPORT="$REPORT_DIR/go-live-gate-${TIMESTAMP}.md"
fi
mkdir -p "$REPORT_DIR"

append_row PASS "Contexto" "Iniciando gate final de go-live"

run_step "Source package audit" bash scripts/testing/source-package-audit.sh
run_step "Release audit" bash scripts/testing/release-audit.sh
run_step "Dependency baseline" bash scripts/testing/dependency-baseline-check.sh
run_step "Toolchain consistency" bash scripts/testing/build-toolchain-consistency.sh

if [[ ! -f vendor/autoload.php ]]; then
  ARTIFACT_MODE="source-package"
  append_row WARN "Runtime mode" "vendor/autoload.php ausente; gate operando em modo source-package"
  if [[ "$RUN_SMOKE" -eq 1 ]]; then
    append_row WARN "Smoke runtime" "Ignorado no source-package sem vendor/autoload.php"
  fi
else
  ARTIFACT_MODE="runtime"
  INSTALL_ARGS=(--json)
  BIOMETRIC_ARGS=(--json)
  SUPPORT_ARGS=(--json)
  RELEASE_ARGS=()
  if [[ "$WITH_CONNECTIONS" -eq 0 ]]; then
    INSTALL_ARGS+=(--no-connections)
    BIOMETRIC_ARGS+=(--no-connections)
    SUPPORT_ARGS+=(--no-connections)
    RELEASE_ARGS+=(--no-connections)
  fi
  if [[ "$SAVE" -eq 1 ]]; then
    RELEASE_ARGS+=(--save)
  fi

  if [[ "$RUN_SMOKE" -eq 1 ]]; then
    run_warnable_step "Smoke install first use" bash scripts/testing/smoke-install-first-use.sh
    run_warnable_step "Smoke critical flows" bash scripts/testing/smoke-critical-flows.sh
    run_warnable_step "Runtime operational validation" bash scripts/testing/runtime-operational-validation.sh --no-save
    run_warnable_step "Release regression smoke" bash scripts/testing/smoke-release-regression.sh --skip-cypress --skip-phpunit
  fi

  run_step "Install doctor" php spark install:doctor "${INSTALL_ARGS[@]}"
  run_step "Biometric doctor" php spark biometric:doctor "${BIOMETRIC_ARGS[@]}"
  run_step "Support diagnostics" php spark support:diagnostics "${SUPPORT_ARGS[@]}"
  run_step "Release gate" php spark release:gate "${RELEASE_ARGS[@]}"
fi

write_reports
[[ "$SAVE" -eq 1 ]] && echo "[INFO] Relatório Markdown: $MD_REPORT" && echo "[INFO] Relatório JSON: $JSON_REPORT"

if [[ "$STRICT" -eq 1 ]]; then
  [[ $fail_count -eq 0 && $warn_count -eq 0 ]]
else
  [[ $fail_count -eq 0 ]]
fi
