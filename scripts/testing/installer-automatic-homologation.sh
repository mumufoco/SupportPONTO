#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"
PHPBIN="${PHPBIN:-php}"

mkdir -p build/support

echo "[1/5] Pré-check do instalador"
$PHPBIN spark support:installer-precheck --json --save > build/support/installer-precheck.latest.json

echo "[2/5] Blueprint do instalador"
$PHPBIN spark support:installer-blueprint > build/support/installer-blueprint.txt

echo "[3/5] Plano de dependências"
$PHPBIN spark support:installer-deps --plan --json > build/support/installer-dependency-plan.json

echo "[4/5] Auditoria de configuração"
$PHPBIN spark support:config-audit --json --save > build/support/installer-config-audit.latest.json

echo "[5/5] Homologação final do instalador"
$PHPBIN spark support:installer-finalization --json --save > build/support/installer-finalization.latest.json

echo "Concluído. Relatórios em build/support/."
