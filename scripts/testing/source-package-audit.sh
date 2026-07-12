#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

php -r '
$path = "artifact-manifest.json";
if (!is_file($path)) {
    fwrite(STDERR, "artifact-manifest.json ausente\n");
    exit(1);
}
$manifest = json_decode(file_get_contents($path), true);
if (!is_array($manifest)) {
    fwrite(STDERR, "artifact-manifest.json inválido\n");
    exit(1);
}
if (($manifest["artifact_type"] ?? null) !== "source-package") {
    fwrite(STDERR, "artifact-manifest.json deve declarar source-package neste artefato de código-fonte\n");
    exit(1);
}
if (($manifest["deployable"] ?? null) !== false) {
    fwrite(STDERR, "source-package não pode ser marcado como deployable=true\n");
    exit(1);
}
echo "[OK] Manifesto do pacote-fonte válido\n";
'

[[ -f docs/releases/ARTIFACT_STRATEGY.md ]] || { echo 'Documento de estratégia de artefatos ausente' >&2; exit 1; }
[[ -f docs/releases/RELEASE_BUILD_RUNBOOK.md ]] || { echo 'Runbook de build de release ausente' >&2; exit 1; }
[[ -f scripts/release/build-source-package.sh ]] || { echo 'Script de build do source package ausente' >&2; exit 1; }
[[ -f scripts/release/build-release-package.sh ]] || { echo 'Script de build do release package ausente' >&2; exit 1; }

echo '[OK] Auditoria do pacote-fonte concluída'
