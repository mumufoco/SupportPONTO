#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

python3 - <<'PY'
import json, pathlib, sys
root = pathlib.Path('.')
composer = json.loads((root / 'composer.json').read_text())
package = json.loads((root / 'package.json').read_text())
tools = json.loads((root / 'tools/package.json').read_text())
release = json.loads((root / 'release.json').read_text())
checks = {
    'composer:php': (composer['require'].get('php'), '^8.3'),
    'composer:codeigniter4/framework': (composer['require'].get('codeigniter4/framework'), '^4.6.5'),
    'composer:codeigniter4/shield': (composer['require'].get('codeigniter4/shield'), '^1.3'),
    'composer:guzzlehttp/guzzle': (composer['require'].get('guzzlehttp/guzzle'), '^7.8'),
    'composer:phpoffice/phpspreadsheet': (composer['require'].get('phpoffice/phpspreadsheet'), '^1.30.3'),
    'composer:tecnickcom/tcpdf': (composer['require'].get('tecnickcom/tcpdf'), '^6.6'),
    'package:cypress': (package['devDependencies'].get('cypress'), '^15.13.1'),
    'package:start-server-and-test': (package['devDependencies'].get('start-server-and-test'), '^2.0.3'),
    'package:node-engine': (package['engines'].get('node'), '>=18.0.0'),
    'package:npm-engine': (package['engines'].get('npm'), '>=9.0.0'),
    'tools:fast-glob': (tools['dependencies'].get('fast-glob'), '^3.3.3'),
    'tools:fs-extra': (tools['dependencies'].get('fs-extra'), '^11.3.4'),
    'tools:puppeteer': (tools['dependencies'].get('puppeteer'), '^24.42.0'),
}
fail = []
for label, (actual, expected) in checks.items():
    if actual != expected:
        fail.append(f'{label}={actual!r} (esperado {expected!r})')
release_version = release.get('version')
if package.get('version') != release_version:
    fail.append(f'package.json version={package.get("version")!r} diverge de release.json={release_version!r}')
if tools.get('version') != release_version:
    fail.append(f'tools/package.json version={tools.get("version")!r} diverge de release.json={release_version!r}')

for lock_name in ['package-lock.json', 'tools/package-lock.json']:
    lock = json.loads((root / lock_name).read_text())
    deprecated = [name for name, meta in lock.get('packages', {}).items() if meta.get('deprecated')]
    if deprecated:
        fail.append(f'{lock_name} contém dependências deprecated: {deprecated[:5]!r}')
if fail:
    print('Baseline divergente:')
    for item in fail:
        print(' - ' + item)
    sys.exit(1)
print(f'Baseline de dependências {release.get("release", release_version)} validada.')
PY
