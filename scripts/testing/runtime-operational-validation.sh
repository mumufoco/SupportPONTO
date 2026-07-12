#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

# shellcheck source=/dev/null
source scripts/lib/compose.sh

BASE_URL="${RUNTIME_BASE_URL:-http://localhost:${APP_PORT:-80}}"
REPORT_DIR="${RUNTIME_REPORT_DIR:-$ROOT_DIR/build/runtime-validation}"
START_COMPOSE=0
STOP_AFTER=0
WITH_CONNECTIONS=1
SAVE_ARTIFACTS=1
WAIT_TIMEOUT="${RUNTIME_WAIT_TIMEOUT:-180}"
COOKIE_JAR=""
TIMESTAMP="$(date '+%Y%m%d-%H%M%S')"
JSON_REPORT=""
MD_REPORT=""

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
    --no-connections)
      WITH_CONNECTIONS=0; shift ;;
    --no-save)
      SAVE_ARTIFACTS=0; shift ;;
    --wait-timeout)
      WAIT_TIMEOUT="$2"; shift 2 ;;
    *)
      echo "[ERROR] Argumento desconhecido: $1" >&2
      exit 1 ;;
  esac
 done

mkdir -p "$REPORT_DIR"
COOKIE_JAR="$REPORT_DIR/runtime-session-${TIMESTAMP}.cookiejar"
JSON_REPORT="$REPORT_DIR/runtime-validation-${TIMESTAMP}.json"
MD_REPORT="$REPORT_DIR/runtime-validation-${TIMESTAMP}.md"

pass_count=0
warn_count=0
fail_count=0
REPORT_ROWS=()
COMPOSE_STARTED=0

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
  if [[ "$SAVE_ARTIFACTS" -ne 1 ]]; then
    return
  fi

  {
    echo "# Runtime Operational Validation"
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
      echo "Validação operacional aprovada nos checks executados."
    else
      echo "## Resultado"
      echo "Validação operacional reprovada. Há falhas antes do go-live."
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

cleanup() {
  if [[ "$STOP_AFTER" -eq 1 && "$COMPOSE_STARTED" -eq 1 ]]; then
    compose down >/tmp/supportponto-runtime-compose-down.log 2>&1 || true
  fi
}
trap cleanup EXIT

wait_for_health() {
  local service="$1"
  local deadline=$((SECONDS + WAIT_TIMEOUT))
  while (( SECONDS < deadline )); do
    local status
    status="$(compose ps --format json "$service" 2>/dev/null | python3 - <<'PY'
import json,sys
raw=sys.stdin.read().strip()
if not raw:
    print('')
    raise SystemExit
rows=[]
for line in raw.splitlines():
    line=line.strip()
    if not line:
        continue
    try:
        rows.append(json.loads(line))
    except Exception:
        pass
if rows:
    health = rows[0].get('Health','') or rows[0].get('State','')
    print(health)
else:
    print('')
PY
)"
    if [[ "$status" == *healthy* || "$status" == *running* ]]; then
      return 0
    fi
    sleep 3
  done
  return 1
}

append_row PASS "Contexto" "Iniciando validação operacional real"

if compose config >/tmp/supportponto-runtime-compose-config.log 2>&1; then
  append_row PASS "Compose config" "docker compose config executado com sucesso"
else
  append_row FAIL "Compose config" "Consulte /tmp/supportponto-runtime-compose-config.log"
fi

if [[ "$START_COMPOSE" -eq 1 ]]; then
  if compose up -d >/tmp/supportponto-runtime-compose-up.log 2>&1; then
    COMPOSE_STARTED=1
    append_row PASS "Compose up" "Serviços iniciados com docker compose up -d"
  else
    append_row FAIL "Compose up" "Consulte /tmp/supportponto-runtime-compose-up.log"
  fi
fi

for service in postgres redis deepface app; do
  if wait_for_health "$service"; then
    append_row PASS "Serviço saudável" "$service reportado como saudável/rodando"
  else
    append_row FAIL "Serviço saudável" "$service não ficou saudável dentro de ${WAIT_TIMEOUT}s"
  fi
done

if curl -fsS "$BASE_URL/health" >/tmp/supportponto-runtime-health.json 2>/tmp/supportponto-runtime-health.err; then
  append_row PASS "Liveness endpoint" "GET $BASE_URL/health respondeu com sucesso"
else
  append_row FAIL "Liveness endpoint" "Falha no GET $BASE_URL/health"
fi

if curl -fsS "$BASE_URL/health/liveness" >/tmp/supportponto-runtime-liveness.json 2>/tmp/supportponto-runtime-liveness.err; then
  append_row PASS "Health liveness endpoint" "GET $BASE_URL/health/liveness respondeu com sucesso"
else
  append_row FAIL "Health liveness endpoint" "Falha no GET $BASE_URL/health/liveness"
fi

if curl -fsS "$BASE_URL/health/readiness" >/tmp/supportponto-runtime-readiness.json 2>/tmp/supportponto-runtime-readiness.err; then
  READY_STATUS="$(grep -o '"status":"[^"]*' /tmp/supportponto-runtime-readiness.json | head -n1 | cut -d'"' -f4 || true)"
  append_row PASS "Readiness endpoint" "GET $BASE_URL/health/readiness respondeu com status ${READY_STATUS:-unknown}"
else
  append_row FAIL "Readiness endpoint" "Falha no GET $BASE_URL/health/readiness"
fi

LOGIN_CODE="$(curl -sS -c "$COOKIE_JAR" -o /tmp/supportponto-runtime-login.html -w '%{http_code}' "$BASE_URL/auth/login" || true)"
if [[ "$LOGIN_CODE" =~ ^(200|302)$ ]]; then
  append_row PASS "Tela de login" "GET $BASE_URL/auth/login retornou HTTP $LOGIN_CODE"
else
  append_row FAIL "Tela de login" "HTTP $LOGIN_CODE em $BASE_URL/auth/login"
fi

if [[ -s "$COOKIE_JAR" ]]; then
  append_row PASS "Sessão inicial" "Cookie jar criado após acesso inicial de login"
else
  append_row WARN "Sessão inicial" "Nenhum cookie de sessão foi observado no acesso inicial"
fi

if [[ -f vendor/autoload.php ]]; then
  INSTALL_ARGS=(--json --save)
  SUPPORT_ARGS=(--json --save)
  RELEASE_ARGS=(--json --save)
  if [[ "$WITH_CONNECTIONS" -eq 0 ]]; then
    INSTALL_ARGS+=(--no-connections)
    SUPPORT_ARGS+=(--no-connections)
    RELEASE_ARGS+=(--no-connections)
  fi

  if php spark install:doctor "${INSTALL_ARGS[@]}" > "$REPORT_DIR/install-doctor-${TIMESTAMP}.json" 2> /tmp/supportponto-runtime-install-doctor.err; then
    append_row PASS "Install doctor" "php spark install:doctor executado"
  else
    append_row FAIL "Install doctor" "Consulte /tmp/supportponto-runtime-install-doctor.err"
  fi

  if php spark support:migrations-audit --json --save > "$REPORT_DIR/migrations-audit-${TIMESTAMP}.json" 2> /tmp/supportponto-runtime-migrations-audit.err; then
    append_row PASS "Migrations audit" "php spark support:migrations-audit executado"
  else
    append_row FAIL "Migrations audit" "Consulte /tmp/supportponto-runtime-migrations-audit.err"
  fi

  if php spark support:schema-audit --json --save > "$REPORT_DIR/schema-audit-${TIMESTAMP}.json" 2> /tmp/supportponto-runtime-schema-audit.err; then
    append_row PASS "Schema audit" "php spark support:schema-audit executado"
  else
    append_row FAIL "Schema audit" "Consulte /tmp/supportponto-runtime-schema-audit.err"
  fi


  if php spark support:config-audit --json --save > "$REPORT_DIR/config-audit-${TIMESTAMP}.json" 2> /tmp/supportponto-runtime-config-audit.err; then
    append_row PASS "Config audit" "php spark support:config-audit executado"
  else
    append_row FAIL "Config audit" "Consulte /tmp/supportponto-runtime-config-audit.err"
  fi

  if bash scripts/migrate-safe.sh --status-only >/tmp/supportponto-runtime-migrate-status.log 2>&1; then
    append_row PASS "Migration status" "bash scripts/migrate-safe.sh --status-only executado"
  else
    append_row FAIL "Migration status" "Consulte /tmp/supportponto-runtime-migrate-status.log"
  fi

  if php spark support:diagnostics "${SUPPORT_ARGS[@]}" > "$REPORT_DIR/support-diagnostics-${TIMESTAMP}.json" 2> /tmp/supportponto-runtime-support.err; then
    append_row PASS "Support diagnostics" "php spark support:diagnostics executado"
  else
    append_row FAIL "Support diagnostics" "Consulte /tmp/supportponto-runtime-support.err"
  fi

  if php spark release:gate "${RELEASE_ARGS[@]}" > "$REPORT_DIR/release-gate-${TIMESTAMP}.json" 2> /tmp/supportponto-runtime-release.err; then
    append_row PASS "Release gate" "php spark release:gate executado"
  else
    append_row FAIL "Release gate" "Consulte /tmp/supportponto-runtime-release.err"
  fi
else
  append_row WARN "Comandos CI4" "vendor/autoload.php ausente; php spark completo não executado"
fi

write_reports

echo "[INFO] Relatório Markdown: $MD_REPORT"
echo "[INFO] Relatório JSON: $JSON_REPORT"

[[ $fail_count -eq 0 ]]
