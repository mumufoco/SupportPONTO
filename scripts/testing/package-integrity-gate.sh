#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
REPORT_PATH="${1:-$ROOT_DIR/build/runtime-validation/package-integrity-gate.json}"

mkdir -p "$(dirname "$REPORT_PATH")"
php "$ROOT_DIR/tools/release/audit-package-integrity.php" --root="$ROOT_DIR" --report="$REPORT_PATH"
