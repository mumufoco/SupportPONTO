#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

python3 - <<'PY'
import json, pathlib, re, sys
root = pathlib.Path('.')
composer = json.loads((root / 'composer.json').read_text())
package = json.loads((root / 'package.json').read_text())
tools = json.loads((root / 'tools/package.json').read_text())
release = json.loads((root / 'release.json').read_text())
package_lock = json.loads((root / 'package-lock.json').read_text())
tools_lock = json.loads((root / 'tools/package-lock.json').read_text())
dockerfile = (root / 'Dockerfile').read_text()
compose = (root / 'docker-compose.yml').read_text()
errors = []
php_req = composer['require'].get('php', '')
php_match = re.search(r'^FROM\s+php:(\d+\.\d+)-fpm-alpine\s+AS\s+app-runtime', dockerfile, re.M)
if not php_match:
    errors.append('Dockerfile sem stage app-runtime baseada em php:<major.minor>-fpm-alpine')
else:
    docker_php = php_match.group(1)
    wanted = re.search(r'(\d+\.\d+)', php_req)
    if wanted and docker_php != wanted.group(1):
        errors.append(f'PHP do Dockerfile ({docker_php}) diverge do composer.json ({php_req})')
for label, manifest, lock in [('package.json', package, package_lock), ('tools/package.json', tools, tools_lock)]:
    lock_version = lock.get('version') or lock.get('packages', {}).get('', {}).get('version')
    if lock_version != manifest.get('version'):
        errors.append(f'{label} version={manifest.get("version")!r} diverge do lockfile={lock_version!r}')
if package.get('version') != release.get('version'):
    errors.append('package.json diverge de release.json')
if tools.get('version') != release.get('version'):
    errors.append('tools/package.json diverge de release.json')

for lock_name, lock in [('package-lock.json', package_lock), ('tools/package-lock.json', tools_lock)]:
    deprecated = [name for name, meta in lock.get('packages', {}).items() if meta.get('deprecated')]
    if deprecated:
        errors.append(f'{lock_name} contém dependências deprecated: {deprecated[:5]!r}')
required = {
    'image: postgres:16-alpine': 'PostgreSQL 16 Alpine',
    'image: redis:7-alpine': 'Redis 7 Alpine',
    'image: bitnami/pgbouncer:1.22': 'PgBouncer 1.22',
}
for needle, label in required.items():
    if needle not in compose:
        errors.append(f'docker-compose.yml sem baseline esperada para {label}')
if 'healthcheck:' not in compose or 'HEALTHCHECK' not in dockerfile:
    errors.append('healthchecks obrigatórios ausentes em Dockerfile ou docker-compose.yml')
if errors:
    print('Inconsistências de toolchain/build encontradas:', file=sys.stderr)
    for item in errors:
        print(' - ' + item, file=sys.stderr)
    sys.exit(1)
print(f'[OK] Toolchain/build consistentes para {release.get("release", release.get("version"))}')
PY
