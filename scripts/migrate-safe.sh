#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

LOG_DIR="${MIGRATE_SAFE_LOG_DIR:-$ROOT_DIR/writable/logs}"
TIMESTAMP="$(date '+%Y%m%d-%H%M%S')"
MODE="migrate"
WITH_ALL=1
EXTRA_ARGS=()

while [[ $# -gt 0 ]]; do
  case "$1" in
    --status-only)
      MODE="status"; shift ;;
    --no-all)
      WITH_ALL=0; shift ;;
    --)
      shift
      EXTRA_ARGS+=("$@")
      break ;;
    *)
      EXTRA_ARGS+=("$1")
      shift ;;
  esac
 done

mkdir -p "$LOG_DIR"
STDOUT_LOG="$LOG_DIR/migrate-${MODE}-${TIMESTAMP}.out.log"
STDERR_LOG="$LOG_DIR/migrate-${MODE}-${TIMESTAMP}.err.log"
META_LOG="$LOG_DIR/migrate-${MODE}-${TIMESTAMP}.meta.log"

if [[ ! -f vendor/autoload.php ]]; then
  echo "[ERROR] vendor/autoload.php ausente. Execute composer install antes do Spark." | tee -a "$META_LOG" >&2
  exit 1
fi

CMD=(php spark)
if [[ "$MODE" == "status" ]]; then
  CMD+=(migrate:status)
else
  CMD+=(migrate)
  if [[ "$WITH_ALL" -eq 1 ]]; then
    CMD+=(--all)
  fi
fi
CMD+=("${EXTRA_ARGS[@]}")

{
  echo "timestamp=$(date -Is)"
  echo "cwd=$ROOT_DIR"
  echo "mode=$MODE"
  echo "php_version=$(php -r 'echo PHP_VERSION;')"
  echo "environment=${CI_ENVIRONMENT:-${APP_ENV:-unknown}}"
  echo "disable_functions=$(php -r 'echo (string) ini_get("disable_functions");')"
  printf 'command='
  printf '%q ' "${CMD[@]}"
  printf '\n'
} > "$META_LOG"

set +e
"${CMD[@]}" > >(tee "$STDOUT_LOG") 2> >(tee "$STDERR_LOG" >&2)
EXIT_CODE=$?
set -e

echo "exit_code=$EXIT_CODE" >> "$META_LOG"
if php spark support:config-audit --json --save > "$LOG_DIR/config-audit-${TIMESTAMP}.json" 2>>"$STDERR_LOG"; then
  echo "config_audit=$LOG_DIR/config-audit-${TIMESTAMP}.json" >> "$META_LOG"
else
  echo "config_audit=failed" >> "$META_LOG"
fi

if [[ "$EXIT_CODE" -ne 0 ]]; then
  echo "[ERROR] Spark falhou. stdout: $STDOUT_LOG | stderr: $STDERR_LOG | meta: $META_LOG" >&2
else
  echo "[INFO] Spark concluído. stdout: $STDOUT_LOG | stderr: $STDERR_LOG | meta: $META_LOG"
fi

exit "$EXIT_CODE"
