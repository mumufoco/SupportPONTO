#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

python3 - <<'PY'
import json, pathlib, sys
release = json.loads(pathlib.Path('release.json').read_text())
manifest = json.loads(pathlib.Path('artifact-manifest.json').read_text())
if not release.get('version'):
    print('release.json sem version', file=sys.stderr)
    sys.exit(1)
if not release.get('release'):
    print('release.json sem release', file=sys.stderr)
    sys.exit(1)
if manifest.get('artifact_type') not in {'source-package', 'release-package'}:
    print('artifact-manifest.json com tipo inválido', file=sys.stderr)
    sys.exit(1)
if release.get('artifact_type') != manifest.get('artifact_type'):
    print('release.json e artifact-manifest.json divergem no tipo de artefato', file=sys.stderr)
    sys.exit(1)
print('[OK] Metadados de release e manifesto consistentes')
PY

for file in \
  docs/releases/ARTIFACT_STRATEGY.md \
  docs/releases/RELEASE_BUILD_RUNBOOK.md \
  docs/operations/PRODUCTION_READINESS.md \
  docs/infrastructure/PRODUCTION_CHECKLIST.md \
  scripts/release/build-source-package.sh \
  scripts/release/build-release-package.sh \
  scripts/testing/source-package-audit.sh; do
  [[ -f "$file" ]] || { echo "Arquivo obrigatório ausente: $file" >&2; exit 1; }
done

echo "[OK] Release audit estrutural concluído"
