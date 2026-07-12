#!/usr/bin/env bash
set -euo pipefail
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
php "$ROOT_DIR/tools/release/audit-timesheet-module-integrity.php" --root="$ROOT_DIR" --report="$ROOT_DIR/build/runtime-validation/timesheet-module-integrity-gate.json"
