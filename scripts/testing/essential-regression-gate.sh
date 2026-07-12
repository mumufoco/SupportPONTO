#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"

cd "$ROOT_DIR"

echo "[essential-gate] PHP syntax check for critical test tooling"
"$PHP_BIN" -l tools/quality/essential-test-runner.php >/dev/null
"$PHP_BIN" -l tests/Feature/Package451EssentialAutomatedTestsStaticTest.php >/dev/null
"$PHP_BIN" -l tests/Feature/Package452CleanInstallRealTest.php >/dev/null
"$PHP_BIN" -l tools/testing/clean-install-postcheck.php >/dev/null
"$PHP_BIN" -l tests/Feature/Package455CleanCodeSolidStaticTest.php >/dev/null
"$PHP_BIN" -l app/DTO/Queue/AsyncJobStatusData.php >/dev/null
"$PHP_BIN" -l app/Services/Queue/Support/AsyncJobTypeCatalog.php >/dev/null
"$PHP_BIN" -l app/Exceptions/DomainOperationException.php >/dev/null
"$PHP_BIN" -l tools/quality/clean-code-audit.php >/dev/null
"$PHP_BIN" -l tools/quality/security-final-audit.php >/dev/null
"$PHP_BIN" -l tests/Feature/Package458FinalSecurityOwaspStaticTest.php >/dev/null

echo "[essential-gate] running vendor-independent static regression gate"
"$PHP_BIN" tools/quality/essential-test-runner.php
"$PHP_BIN" tools/quality/clean-code-audit.php

if [[ -f vendor/bin/phpunit && -f vendor/codeigniter4/framework/system/Test/bootstrap.php ]]; then
  echo "[essential-gate] vendor detected; running Package451/452/455/458 PHPUnit tests"
  vendor/bin/phpunit --filter Package451EssentialAutomatedTestsStaticTest
  vendor/bin/phpunit --filter Package452CleanInstallRealTest
  vendor/bin/phpunit --filter Package455CleanCodeSolidStaticTest
vendor/bin/phpunit --filter Package458FinalSecurityOwaspStaticTest
else
  echo "[essential-gate] vendor not found; skipped PHPUnit runtime test after static gate"
fi
