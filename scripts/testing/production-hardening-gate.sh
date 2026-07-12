#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
php "$ROOT/tools/quality/production-hardening-audit.php" --root="$ROOT"
