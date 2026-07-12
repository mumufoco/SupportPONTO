# Pacote 425 — Normalização total de versionamento

## Objetivo

Eliminar divergências de versão corrente entre metadados, Docker, OpenAPI, service worker, CSS, fallback PHP, instalador, pacote NPM raiz, pacote NPM de ferramentas e documentação operacional `CURRENT`.

## Fonte única de verdade

A versão corrente passa a ser coordenada por:

- `release.json`
- `tools/release/sync-version.php`
- `tools/release/audit-version-consistency.php`
- `tools/release/version-surfaces.php`

## Superfícies normalizadas

- `release.json`
- `artifact-manifest.json`
- `public/version.json`
- `package.json`
- `package-lock.json`
- `tools/package.json`
- `tools/package-lock.json`
- `Dockerfile`
- `deepface-api/Dockerfile`
- `openapi.yaml`
- `public/sw.js`
- `public/css/app.css`
- `app/Support/ReleaseMetadata.php`
- `tools/installer/SupportPontoZeroInstaller.php`
- `deepface-api/app.py`
- `deepface-api/README.md`
- `README_APLICACAO.md`
- documentos operacionais `*CURRENT.md`

## Gates bloqueantes

O pacote adiciona o gate:

```bash
php tools/release/audit-version-consistency.php
```

E o wrapper:

```bash
bash scripts/testing/version-consistency-gate.sh
```

Também foram adicionados scripts Composer:

```bash
composer run sync:version -- --version=1.1.xxx
composer run audit:version
composer run test:version-consistency
```

## Observação

Referências históricas em documentos de pacotes antigos, comentários de migrations e testes de regressão antigos não são tratadas como declaração da versão corrente. O gate valida somente as superfícies oficiais que indicam a versão atual do artefato.
