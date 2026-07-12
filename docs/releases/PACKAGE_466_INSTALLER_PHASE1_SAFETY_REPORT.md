# Pacote 466 — Fase 1 do Instalador: segurança, modos e recuperação

## Escopo

Este pacote executa a Fase 1 definida na auditoria do instalador automático do SupportPONTO.

## Arquivos principais alterados

- `tools/installer/SupportPontoZeroInstaller.php`
- `tools/installer/production_installer_guard.php`
- `tests/Feature/InstallerPhase1SafetyStaticTest.php`
- `docs/installer/FASE_1_SEGURANCA_FLUXO_INSTALADOR.md`
- metadados de versão `1.1.467`

## Entregas concluídas

### 1. Separação de modos de instalação

Foram adicionados modos explícitos:

- `clean_install`
- `update`
- `destructive_reinstall`

O modo `clean_install` não destrói schema existente. Se houver tabelas no schema `public`, a instalação limpa é bloqueada.

O modo `update` preserva dados e não executa `DROP SCHEMA` nem seeders iniciais.

O modo `destructive_reinstall` mantém o fluxo destrutivo, mas agora é isolado, explícito e auditado.

### 2. Journal de instalação

Foi criado journal estruturado em:

```text
writable/installer/install-journal.json
```

Ele registra etapa atual, eventos, modo, install_id, falha final e orientação de recuperação.

### 3. Backup do `.env`

Antes de sobrescrever `.env`, o instalador grava backup em:

```text
writable/installer/backups/pre-install-env-*.env.bak
```

### 4. Reinstalação destrutiva mais segura

Em ambiente `production`, se `pg_dump` não gerar backup SQL real e restaurável, a reinstalação destrutiva é bloqueada.

O manifesto JSON continua existindo como diagnóstico, mas não substitui backup SQL em produção.

### 5. Primeira abertura segura sem `.env`

O guard web permite abertura GET/read-only quando não existe `.env` nem `installed.lock`.

Para POST e ações de instalação, token continua obrigatório. Quando não há `.env`, é gerado:

```text
writable/installer/bootstrap.token
```

### 6. Validações adicionadas

Foi criado teste estático dedicado:

```text
tests/Feature/InstallerPhase1SafetyStaticTest.php
```

## Validações executadas

- `php -l tools/installer/SupportPontoZeroInstaller.php`
- `php -l tools/installer/production_installer_guard.php`
- lint de todos os PHP em `tools/installer`
- lint dos testes Feature
- `php tools/quality/installer-wizard-audit.php`
- `php tools/quality/dependency-catalog-audit.php`
- `php tools/release/audit-version-consistency.php`
- assert estático dos novos marcadores da Fase 1

## Resultado

Fase 1 concluída com pacote completo v1.1.467.
