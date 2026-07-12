# Pacote 459 — Release candidate completo

## Objetivo

Gerar o **SupportPONTO v1.1.459 RC** como pacote completo para homologação controlada antes de produção.

## Escopo validado

Este release candidate consolida os pacotes anteriores e adiciona uma camada final de fechamento com validação de:

- auditoria de pacote;
- versionamento;
- testes essenciais sem `vendor`;
- hardening de produção;
- documentação operacional;
- segurança OWASP final;
- artefatos de instalação limpa;
- smoke tests dos fluxos principais;
- bloqueio do instalador após instalação por `installed.lock`;
- geração do `.zip` completo.

## Fluxos críticos cobertos

| Fluxo | Cobertura no RC |
| --- | --- |
| Instalação limpa | `tools/testing/clean-install-e2e.sh` e `docker-compose.clean-install.yml` |
| PostgreSQL | serviço `postgres` dedicado no compose limpo |
| Instalador CLI | execução pelo script E2E |
| Migrations | execução e pós-checagem via `migrate:status` |
| Seeds/admin | execução pelo instalador e validação pós-instalação |
| Login | smoke/postcheck em `/auth/login` |
| Dashboard | smoke/postcheck em `/dashboard` |
| Funcionário | smoke asset cobre rotas de `employees`/cadastro |
| Ponto | smoke asset cobre fluxo operacional de ponto/timesheet |
| Relatório | smoke asset cobre `reports` |
| Instalador bloqueado | validação por `installed.lock` e guard de produção |
| Versão | `audit-version-consistency.php` e metadados `1.1.459` |

## Geração de pacote

Artefato esperado:

```bash
SupportPONTO-v1.1.459-rc-completo.zip
```

## Gates executáveis no pacote

```bash
bash scripts/testing/essential-regression-gate.sh
bash scripts/testing/package-integrity-gate.sh
bash scripts/testing/route-integrity-gate.sh
bash scripts/testing/model-schema-integrity-gate.sh
php tools/quality/production-hardening-audit.php
php tools/quality/documentation-audit.php
php tools/quality/security-final-audit.php
php tools/quality/release-candidate-audit.php
php tools/release/audit-version-consistency.php --root=.
```

## Instalação limpa real

Executar em máquina com Docker/PostgreSQL disponível:

```bash
bash tools/testing/clean-install-e2e.sh
```

O script deve gerar relatório final com o resultado da instalação limpa, incluindo health check, login, dashboard, cadastro de funcionário, ponto, relatório e validação do bloqueio do instalador após instalação.

## Observação sobre o sandbox

No sandbox de geração do pacote, não há `vendor/codeigniter4/framework/system/Boot.php`, Composer global nem Docker/PostgreSQL disponíveis. Por isso, os testes que dependem de boot real do CodeIgniter ou de containers devem ser executados no servidor/local de homologação. Os gates estáticos e sem `vendor` foram executados para bloquear regressões conhecidas antes da entrega.

## Critério de aceite para homologação

O pacote está apto para homologação quando:

1. todos os gates estáticos passarem;
2. `bash tools/testing/clean-install-e2e.sh` passar em ambiente com Docker;
3. login, dashboard, funcionário, ponto e relatório forem validados pelo técnico;
4. o instalador web estiver bloqueado após `installed.lock`;
5. `public/version.json`, `release.json` e `artifact-manifest.json` declararem `1.1.459`.
