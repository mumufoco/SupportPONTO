# Pacote 452 — Teste de instalação limpa real

## Objetivo

Validar o sistema SupportPONTO do zero em ambiente limpo, cobrindo banco, instalador, migrations, seeds, administrador inicial, rotas e smoke tests HTTP.

## Problema resolvido

Pacotes anteriores validavam sintaxe, estrutura e gates estáticos, mas não havia um fluxo padronizado e reproduzível para provar uma instalação limpa real em PostgreSQL novo.

## Entregas

- `docker-compose.clean-install.yml`
- `tools/testing/clean-install-e2e.sh`
- `tools/testing/clean-install-postcheck.php`
- `tests/Feature/Package452CleanInstallRealTest.php`
- `docs/testing/TESTE_INSTALACAO_LIMPA_REAL.md`
- Scripts Composer:
  - `test:clean-install`
  - `test:clean-install:postcheck`
- `public/version.json` atualizado para `1.1.452`
- `Dockerfile` atualizado para label `1.1.452`

## Fluxo coberto

1. Ambiente limpo temporário.
2. PostgreSQL novo.
3. Instalação de dependências Composer quando necessário.
4. Instalador CLI real.
5. Migrations.
6. Seeds.
7. Criação do admin.
8. Smoke de login.
9. Smoke de dashboard.
10. Validação de rotas.
11. Pós-checagem de arquivos, `.env`, lock, relatório e contratos críticos.
12. Relatório final JSON/Markdown.

## Segurança

- O teste roda em workspace isolado por padrão.
- O banco usado é descartável.
- A confirmação destrutiva fica restrita ao banco limpo de teste.
- A pós-checagem confirma que `ADMIN_INITIAL_PASSWORD` e `temporary_password` não ficam persistidos no `.env` ou relatório final do instalador.

## Validação local realizada no sandbox

- `php -l tools/testing/clean-install-postcheck.php`
- `php -l tests/Feature/Package452CleanInstallRealTest.php`
- `bash -n tools/testing/clean-install-e2e.sh`
- JSON de `composer.json` validado.
- JSON de `public/version.json` validado.
- `.zip` validado com `unzip -t`.

## Limitação do sandbox

O teste completo com Docker/PostgreSQL/Composer/vendor não foi executado dentro do sandbox porque o ambiente da conversa não oferece Docker daemon ativo nem dependências completas do projeto. A entrega inclui o fluxo real para execução em servidor/local com Docker.
