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
TMP_DIR="$OUT_DIR/release-package"
ZIP_PATH="$OUT_DIR/supportponto-release-package.zip"

if [[ ! -d "$ROOT_DIR/vendor" ]]; then
  echo "[ERRO] vendor/ não encontrado. Este repositório/artefato atual é um source package, não um release pronto." >&2
  echo "[AÇÃO] Execute composer install --no-dev --optimize-autoloader em ambiente controlado antes de gerar o release package." >&2
  exit 1
fi

mkdir -p "$OUT_DIR"
rm -rf "$TMP_DIR" "$ZIP_PATH"
mkdir -p "$TMP_DIR"

rsync -a ./ "$TMP_DIR/" \
  --exclude '.git/' \
  --exclude 'node_modules/' \
  --exclude 'build/artifacts/' \
  --exclude '*.zip'

python3 - <<'PY'
import json, pathlib
root = pathlib.Path("build/artifacts/release-package")
manifest = json.loads((root / "artifact-manifest.json").read_text())
manifest["artifact_type"] = "release-package"
manifest["deployable"] = True
manifest["entrypoint"] = "/entrypoint.sh"
manifest["release_validation"] = {
  "requires_vendor": True,
  "requires_runtime_smoke": True,
  "requires_release_audit": True,
}
(root / "artifact-manifest.json").write_text(json.dumps(manifest, indent=2, ensure_ascii=False) + "\n")
PY

(
  cd "$OUT_DIR"
  zip -qr "$(basename "$ZIP_PATH")" "release-package"
)

echo "[OK] Release package gerado em $ZIP_PATH"
