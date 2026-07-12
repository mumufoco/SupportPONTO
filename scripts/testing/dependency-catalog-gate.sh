#!/usr/bin/env bash
set -Eeuo pipefail
php tools/quality/dependency-catalog-audit.php "$@"
