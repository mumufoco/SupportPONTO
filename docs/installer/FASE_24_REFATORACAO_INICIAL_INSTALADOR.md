# Fase 24 — Refatoração inicial do instalador monolítico

Versão: `1.1.489`

## Objetivo

Reduzir o acoplamento do instalador automático sem alterar o fluxo público já estabilizado nas fases anteriores.

Esta fase inicia a extração do arquivo monolítico `tools/installer/SupportPontoZeroInstaller.php` para componentes autocontidos que continuam funcionando antes de Composer, CodeIgniter e `vendor/autoload.php` existirem.

## Alterações principais

### 1. Paths e executáveis

Criado:

- `tools/installer/Core/InstallerPaths.php`

Responsabilidades:

- localizar executáveis em ambientes com `PATH` limitado;
- considerar caminhos típicos de aaPanel;
- validar `open_basedir`;
- normalizar paths para comparação segura;
- localizar `bash` sem depender exclusivamente do `PATH` do PHP-FPM.

### 2. PHP CLI

Criado:

- `tools/installer/Core/PhpCliDetector.php`

Responsabilidades:

- detectar PHP CLI real;
- rejeitar `php-fpm`;
- preferir PHP do aaPanel quando disponível;
- validar `PHP_SAPI === cli`;
- validar versão mínima;
- validar extensões obrigatórias no mesmo PHP usado por Composer, `spark`, migrations e seeders.

### 3. Fronteiras futuras

Criadas classes de fronteira para continuidade da refatoração:

- `tools/installer/Diagnostics/CoreDiagnostic.php`
- `tools/installer/Diagnostics/AdvancedDiagnostic.php`
- `tools/installer/Steps/ComposerStep.php`
- `tools/installer/Steps/DatabaseStep.php`

Nesta fase elas funcionam como marcadores estruturais para a próxima extração incremental, sem quebrar compatibilidade.

## Compatibilidade

Entradas mantidas:

- `tools/installer/install_cli.php`
- `tools/installer/install_web.php`
- `/install`
- `public/install.php`
- `public/install/index.php`

Comandos existentes mantidos:

- `--diagnose-core --json`
- `--diagnose-full --json`
- `--offline-resources --json`
- `--server-provision-plan --json`

## Validação esperada

```bash
php -l tools/installer/SupportPontoZeroInstaller.php
php -l tools/installer/Core/InstallerPaths.php
php -l tools/installer/Core/PhpCliDetector.php
php tools/installer/install_cli.php --diagnose-core --json
php tests/Feature/InstallerPhase24InitialRefactorStaticTest.php
```

## Resultado

O instalador mantém o comportamento externo, mas passa a ter base inicial para decomposição segura em componentes menores.
