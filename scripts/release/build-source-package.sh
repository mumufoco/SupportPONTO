#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

php "$ROOT_DIR/tools/release/audit-version-consistency.php" --root="$ROOT_DIR" --report="$ROOT_DIR/build/runtime-validation/version-consistency-gate.json"

php "$ROOT_DIR/tools/release/audit-route-integrity.php" --root="$ROOT_DIR" --report="$ROOT_DIR/build/runtime-validation/route-integrity-gate.json"

php "$ROOT_DIR/tools/release/audit-mvc-integrity.php" --root="$ROOT_DIR" --report="$ROOT_DIR/build/runtime-validation/mvc-integrity-gate.json"

php "$ROOT_DIR/tools/release/audit-service-integrity.php" --root="$ROOT_DIR" --report="$ROOT_DIR/build/runtime-validation/service-integrity-gate.json"

php "$ROOT_DIR/tools/release/audit-view-integrity.php" --root="$ROOT_DIR" --report="$ROOT_DIR/build/runtime-validation/view-integrity-gate.json"

php "$ROOT_DIR/tools/release/audit-model-schema-integrity.php" --root="$ROOT_DIR" --report="$ROOT_DIR/build/runtime-validation/model-schema-integrity-gate.json"

php "$ROOT_DIR/tools/release/audit-employee-module-integrity.php" --root="$ROOT_DIR" --report="$ROOT_DIR/build/runtime-validation/employee-module-integrity-gate.json"

php "$ROOT_DIR/tools/release/audit-timesheet-module-integrity.php" --root="$ROOT_DIR" --report="$ROOT_DIR/build/runtime-validation/timesheet-module-integrity-gate.json"

php "$ROOT_DIR/tools/release/audit-report-module-integrity.php" --root="$ROOT_DIR" --report="$ROOT_DIR/build/runtime-validation/report-module-integrity-gate.json"

php "$ROOT_DIR/tools/release/audit-audit-log-integrity.php" --root="$ROOT_DIR" --report="$ROOT_DIR/build/runtime-validation/audit-log-integrity-gate.json"

php "$ROOT_DIR/tools/release/audit-package-integrity.php" --root="$ROOT_DIR" --report="$ROOT_DIR/build/runtime-validation/package-integrity-gate.json"

OUT_DIR="$ROOT_DIR/build/artifacts"
TMP_PARENT="$(mktemp -d)"
TMP_DIR="$TMP_PARENT/source-package"
ZIP_PATH="$OUT_DIR/supportponto-source-package.zip"
FILE_LIST="$TMP_PARENT/source-files.txt"

cleanup() {
  rm -rf "$TMP_PARENT"
}
trap cleanup EXIT

mkdir -p "$OUT_DIR" "$TMP_DIR"
rm -rf "$OUT_DIR/source-package" "$ZIP_PATH"

find . \
  -path './.git' -prune -o \
  -path './vendor' -prune -o \
  -path './node_modules' -prune -o \
  -path './build/artifacts' -prune -o \
  -path './writable/cache' -prune -o \
  -path './writable/session' -prune -o \
  -path './writable/logs' -prune -o \
  -name '*.zip' -prune -o \
  -type f -print > "$FILE_LIST"

tar -cf - -T "$FILE_LIST" | tar -xf - -C "$TMP_DIR"

php -r '
$path = $argv[1] . "/artifact-manifest.json";
$manifest = json_decode(file_get_contents($path), true);
if (!is_array($manifest)) {
    fwrite(STDERR, "artifact-manifest.json inválido\n");
    exit(1);
}
$manifest["artifact_type"] = "source-package";
$manifest["deployable"] = false;
$manifest["entrypoint"] = null;
file_put_contents($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
' "$TMP_DIR"

(
  cd "$TMP_PARENT"
  zip -qr "$ZIP_PATH" "source-package"
)

mv "$TMP_DIR" "$OUT_DIR/source-package"

echo "[OK] Pacote-fonte gerado em $ZIP_PATH"
