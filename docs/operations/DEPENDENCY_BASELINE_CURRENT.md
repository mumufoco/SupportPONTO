# Dependency Baseline Current

Release atual: **v1.1.500**

> A partir da limpeza completa (v1.1.498 → v1.1.500), o ambiente de build/runtime
> deixou de depender de imagens Docker (`php:8.3-fpm-alpine`, `composer:2.7` etc.)
> e do diretório `tools/` (removido). A aplicação roda direto no host via aaPanel
> (PHP-FPM, PostgreSQL e Nginx nativos do servidor) e o deploy é feito por
> `scripts/release/deploy.sh` (rsync/SSH) — ver `docs/releases/ARTIFACT_STRATEGY.md`.

## Baseline de runtime e build (produção — aaPanel/host, sem Docker)
- PHP runtime: `8.3` (PHP-FPM)
- Composer: `2.7+`
- Node.js: `>=18.0.0` (apenas para build de assets em desenvolvimento)
- npm: `>=9.0.0`
- PostgreSQL: `16`
- Redis: `7` (sessões/filas em produção multi-réplica)

## Dependências PHP governadas
- `codeigniter4/framework`: faixa `^4.6.5`
- `codeigniter4/shield`: faixa `^1.3`
- `guzzlehttp/guzzle`: faixa `^7.8`
- `phpoffice/phpspreadsheet`: faixa `^1.30.3`
- `tecnickcom/tcpdf`: faixa `^6.6`
- php-webdriver/webdriver: faixa `^1.15`
- phpstan/phpstan: faixa `^2.1`

## Dependências frontend/tooling governadas
- `cypress`: faixa `^15.13.1`
- `start-server-and-test`: faixa `^2.0.3`
- `fast-glob`: faixa `^3.3.3`
- `fs-extra`: faixa `^11.3.4`
- `puppeteer`: faixa `^24.42.0`

## Regras operacionais
- `package.json` e `package-lock.json` devem permanecer sincronizados com `release.json`.
- Toda alteração de baseline deve passar por `bash scripts/testing/dependency-baseline-check.sh` e `bash scripts/testing/build-toolchain-consistency.sh`.
- O deploy só pode ser executado após `composer install --no-dev --optimize-autoloader` bem-sucedido no servidor (etapa "instalar" de `scripts/release/deploy.sh`).

## Hardening do toolkit
- O toolkit interno usa `fast-glob` (não `glob`) para eliminar lockfile com dependências deprecated.
- `package-lock.json` deve permanecer sem entradas `deprecated`.
