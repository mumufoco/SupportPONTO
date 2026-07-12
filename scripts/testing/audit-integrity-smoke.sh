#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

PHPUNIT_BIN="${PHPUNIT_BIN:-vendor/bin/phpunit}"
REPORT_DIR="${REPORT_DIR:-build/audit}"
mkdir -p "$REPORT_DIR"

if [[ ! -x "$PHPUNIT_BIN" ]]; then
  echo "phpunit não encontrado em $PHPUNIT_BIN" >&2
  exit 1
fi

"$PHPUNIT_BIN" tests/integration/AuditIntegrityIntegrationTest.php | tee "$REPORT_DIR/audit-integrity-smoke.txt"
