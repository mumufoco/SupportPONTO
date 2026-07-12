#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
REPORT_DIR="$ROOT_DIR/build/runtime-validation"
mkdir -p "$REPORT_DIR"

php "$ROOT_DIR/tools/release/audit-service-integrity.php" \
  --root="$ROOT_DIR" \
  --report="$REPORT_DIR/service-integrity-gate.json"
