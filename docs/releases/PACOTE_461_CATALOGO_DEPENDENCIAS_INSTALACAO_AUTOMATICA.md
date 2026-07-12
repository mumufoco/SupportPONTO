# Pacote 461 — Catálogo de dependências e instalação automática

## Objetivo

Catalogar todas as dependências e aplicações de terceiros necessárias/recomendadas para o SupportPONTO e integrá-las ao fluxo de instalação automática.

## Problema resolvido

Antes, o instalador validava várias dependências, mas não havia um catálogo único e operacional separando:

- dependências que o PHP consegue instalar;
- dependências que precisam de root/sudo;
- dependências opcionais/recomendadas;
- dependências de DeepFace;
- dependências Node/testes;
- serviços de banco/cache/proxy;
- imagens Docker.

## Arquivos principais

- `install/runtime/dependencies.catalog.json`
- `install/runtime/install-dependencies.sh`
- `install/runtime/provision-server.sh`
- `tools/installer/SupportPontoZeroInstaller.php`
- `tools/quality/dependency-catalog-audit.php`
- `scripts/testing/dependency-catalog-gate.sh`
- `docs/deployment/CATALOGO_DEPENDENCIAS_INSTALACAO_AUTOMATICA.md`
- `docs/quality/DEPENDENCY_CATALOG_GATE.md`

## Implementado

- Catálogo JSON versionado com dependências de:
  - sistema operacional;
  - comandos base;
  - PHP runtime;
  - extensões PHP;
  - Composer/vendor;
  - PostgreSQL;
  - Redis;
  - PgBouncer;
  - Nginx/Apache;
  - DeepFace/Python;
  - Node/npm;
  - Docker/Compose;
  - imagens Docker.
- Script instalador de dependências com modos:
  - `--diagnose`;
  - `--install-system`;
  - `--install-composer`;
  - `--install-vendor`;
  - `--install-deepface`;
  - `--install-node`;
  - `--install-docker`;
  - `--all`;
  - `--dry-run`;
  - `--json`.
- Integração no instalador CLI:
  - `--dependency-catalog`;
  - `--install-dependencies`.
- Diagnóstico do instalador passou a incluir categoria de catálogo de dependências.
- Provisionamento do servidor passou a reconhecer o catálogo e chamar o script de dependências quando solicitado.
- Gate automatizado de dependências criado e integrado ao release critical.

## Comandos principais

```bash
bash install/runtime/install-dependencies.sh --diagnose
bash install/runtime/install-dependencies.sh --install-composer --install-vendor
bash install/runtime/install-dependencies.sh --install-deepface
php tools/installer/install_cli.php --dependency-catalog
php tools/installer/install_cli.php --install-dependencies --diagnose
bash scripts/testing/dependency-catalog-gate.sh
```

## Segurança

- O instalador web não executa `sudo`.
- Pacotes de sistema continuam restritos ao CLI com root/sudo.
- DeepFace permanece isolado em venv/serviço/Docker, evitando acoplar carga pesada ao PHP.
- Composer local fica em `writable/composer`.

## Resultado esperado

Um técnico consegue consultar, diagnosticar e instalar dependências de forma guiada e previsível antes de executar a instalação da aplicação.
