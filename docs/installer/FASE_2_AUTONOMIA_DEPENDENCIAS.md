# Fase 2 — Autonomia controlada de dependências do instalador

Versão: **SupportPONTO v1.1.467**

## Objetivo

A Fase 2 torna o instalador mais autônomo sem abrir mão da segurança de produção. O foco é resolver o bloqueio operacional causado por `vendor/autoload.php` ausente, mantendo execução de Composer controlada, auditável e explicitamente autorizada.

## Mudanças principais

### 1. Instalação controlada de `vendor/`

O instalador principal agora pode executar `composer install` quando `vendor/autoload.php` estiver ausente, mas somente com autorização explícita.

- CLI: usar `--install-vendor` ou `--install-composer`.
- Web: marcar a opção de instalação de vendor e configurar `INSTALLER_ALLOW_WEB_VENDOR_INSTALL=true` no ambiente.

Sem essa autorização, o instalador gera `writable/installer/composer-provision-required.json` com plano de correção.

### 2. Composer com verificação criptográfica

Quando Composer não existe local/globalmente, o instalador baixa o instalador oficial do Composer e valida a assinatura SHA384 usando `https://composer.github.io/installer.sig` antes de gerar `writable/composer/composer.phar`.

O arquivo `composer.phar` não é baixado diretamente sem verificação.

### 3. Relatório de execução do Composer

Cada execução automática de Composer grava:

- `writable/installer/composer-install-last.json`
- `writable/composer/composer-download-last.json`

Os relatórios são sanitizados e mantêm apenas cauda de saída para suporte técnico.

### 4. Política de segurança para Web

O modo Web continua bloqueado por padrão para instalação de vendor. A liberação exige dois fatores operacionais:

1. opção marcada no wizard;
2. variável de ambiente `INSTALLER_ALLOW_WEB_VENDOR_INSTALL=true`.

Isso evita que um POST acidental execute Composer em produção.

### 5. Comando de produção

O comando executado usa:

```bash
composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --classmap-authoritative
```

## Fluxos suportados

### CLI recomendado

```bash
php tools/installer/install_cli.php --install --install-vendor --mode=clean_install \
  --app-url=https://ponto.exemplo.com/ \
  --db-host=127.0.0.1 --db-name=supportponto --db-user=usuario --db-pass=senha \
  --admin-name="Administrador" --admin-email=admin@exemplo.com --admin-cpf=00000000000
```

### Web controlado

Configurar no ambiente do domínio:

```env
INSTALLER_ALLOW_WEB_VENDOR_INSTALL=true
```

Depois marcar no wizard:

```text
Instalar vendor automaticamente se estiver ausente
```

Após finalizar, remover a variável do ambiente.

## Arquivos alterados

- `tools/installer/SupportPontoZeroInstaller.php`
- `tools/quality/dependency-catalog-audit.php`
- `tests/Feature/InstallerPhase2AutonomyStaticTest.php`
- metadados de versão para `1.1.467`

## Critérios de validação

- O instalador continua abrindo sem `vendor/`.
- Sem autorização explícita, a instalação não executa Composer.
- Com autorização explícita, baixa Composer com SHA384 verificado.
- Falha de Composer gera relatório persistido.
- Instalação de produção usa `--no-dev` e autoload otimizado.
- Scripts de auditoria passam sem erro.
