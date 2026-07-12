# Package 489 — Installer initial refactor

Versão: `1.1.489`

## Resumo

O Pacote 489 inicia a refatoração do instalador automático, extraindo responsabilidades técnicas críticas do arquivo monolítico `SupportPontoZeroInstaller.php` para classes autocontidas em `tools/installer/Core`.

## Entregas

- `InstallerPaths`: paths, `open_basedir`, localização de executáveis e `bash`.
- `InstallerPhpCliDetector`: detecção e validação do PHP CLI correto.
- Fronteiras iniciais para diagnósticos e steps:
  - `InstallerCoreDiagnostic`
  - `InstallerAdvancedDiagnostic`
  - `InstallerComposerStep`
  - `InstallerDatabaseStep`
- Teste estático de refatoração:
  - `tests/Feature/InstallerPhase24InitialRefactorStaticTest.php`

## Riscos mitigados

- Dependência excessiva de métodos privados dentro do instalador principal.
- Regressão futura ao alterar detecção de PHP CLI.
- Duplicação futura de lógica de path/executáveis.
- Crescimento ilimitado do arquivo principal.

## Observação técnica

Esta fase é incremental. A extração de diagnóstico, Composer e banco para classes operacionais completas deve continuar nos próximos pacotes. O comportamento público foi preservado para reduzir risco de quebra.
