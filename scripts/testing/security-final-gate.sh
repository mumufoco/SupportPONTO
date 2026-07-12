#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"

cd "$ROOT_DIR"

echo "[security-final-gate] syntax check"
"$PHP_BIN" -l tools/quality/security-final-audit.php >/dev/null
"$PHP_BIN" -l tests/Feature/Package458FinalSecurityOwaspStaticTest.php >/dev/null

echo "[security-final-gate] running final OWASP/corporate security audit"
"$PHP_BIN" tools/quality/security-final-audit.php

if [[ -f vendor/bin/phpunit && -f vendor/codeigniter4/framework/system/Test/bootstrap.php ]]; then
  echo "[security-final-gate] vendor detected; running PHPUnit package 458 test"
  vendor/bin/phpunit --filter Package458FinalSecurityOwaspStaticTest
else
  echo "[security-final-gate] vendor not found; skipped PHPUnit runtime test after static gate"
fi
