#!/usr/bin/env bash
set -euo pipefail
php tools/quality/final-stable-production-audit.php "$@"
