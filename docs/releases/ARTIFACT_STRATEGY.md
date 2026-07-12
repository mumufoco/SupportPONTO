# Estratégia de artefatos do SupportPONTO (histórico — conceito abandonado)

> **Status: DESCONTINUADO em 2026-06.** Este documento descreve um modelo de
> empacotamento (source-package vs release-package, `artifact-manifest.json`,
> builds Docker) que foi **deliberadamente removido** na limpeza completa entre
> as versões v1.1.498 e v1.1.500. Mantido apenas como registro histórico —
> **não reflete o processo atual** e não deve ser usado como referência operacional.

## Modelo atual (vigente desde v1.1.500)

Não existe mais distinção entre "pacote de código-fonte" e "pacote pronto para
produção". **O repositório É o que roda em produção.**

O deploy é feito diretamente do checkout local (ou de um worktree limpo de uma
tag/branch) para o servidor via SSH, com um único script automatizado:

- **Script**: `scripts/release/deploy.sh`
- **Configuração**: `scripts/release/deploy.env.example` (copiar para um arquivo
  não versionado e preencher os dados do servidor)
- **Ciclo**: empacotar (rsync com lista de exclusões) → transferir (rsync/SSH)
  → instalar (`composer install --no-dev`, `spark migrate`, `cache:clear`,
  permissões `www:www`) → reiniciar serviços (`systemctl reload php8.3-fpm` e
  o queue worker, se configurado)
- **Verificação**: `scripts/verify-deployment.sh` e checagem automática de
  `/health/readiness` ao final do `deploy.sh`

Não há mais `artifact-manifest.json`, `docker-compose*.yml`, `tools/release/*.php`
nem builds de "release package"/"source package" em zip. A validação de
qualidade do release é feita pelo gate único: `php spark release:gate`
(`app/Services/Release/ReleaseGateService.php`), que audita o código-fonte e a
documentação correntes diretamente — sem depender de scripts de empacotamento
externos.

---

## Conteúdo histórico (modelo Docker/source-package — REMOVIDO)

<details>
<summary>Clique para ver o contrato antigo, mantido apenas para referência histórica</summary>

### Contrato oficial (histórico)

O projeto passava a distinguir dois tipos de artefato:

#### 1. Source package
Pacote de código-fonte para auditoria, revisão e build controlado.

Características:
- podia não incluir `vendor/`
- podia não ser executável sozinho em ambiente limpo
- servia para revisão técnica, versionamento e preparação de build
- **não devia ser tratado como release pronto para produção**

#### 2. Release package
Pacote pronto para deploy controlado.

Características mínimas:
- incluía `vendor/`
- incluía `release.json`, `public/version.json` e `artifact-manifest.json`
- passava por auditoria de release
- passava por smoke test e validação operacional
- podia ser usado para empacotamento final ou imagem/container de produção

### Regra operacional (histórica)

Todo artefato devia declarar seu tipo em `artifact-manifest.json`.

- `source-package` → não deployável
- `release-package` → deployável apenas após auditoria e smoke

### Motivação original

Essa separação tentava eliminar ambiguidade entre:
- pacote entregue para revisão técnica
- pacote realmente apto para produção

E evitar que um artefato sem dependências bootstrapadas fosse confundido com
uma release pronta. Na prática, o modelo adicionou complexidade (Docker,
27 scripts de auditoria em `tools/release/`, builds em zip) sem proporcionalidade
ao ganho — daí a decisão de simplificar para deploy direto via SSH.

</details>
