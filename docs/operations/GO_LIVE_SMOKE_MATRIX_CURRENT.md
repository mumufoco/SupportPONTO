# Go-Live Smoke Matrix Current

Release atual: **v1.1.498**

## Trilha final recomendada
1. `bash scripts/testing/source-package-audit.sh`
2. `bash scripts/testing/release-audit.sh`
3. `bash scripts/testing/dependency-baseline-check.sh`
4. `bash scripts/testing/build-toolchain-consistency.sh`
5. `bash scripts/testing/go-live-gate.sh`
6. `bash scripts/testing/final-production-readiness.sh`

## Modo source-package
Use quando o artefato ainda não possui `vendor/autoload.php`.

Cobertura:
- manifesto e estratégia do artefato
- baseline de dependências
- consistência de toolchain
- checklist documental de go-live

## Modo runtime
Use quando o ambiente estiver completo com dependências instaladas.

Cobertura adicional:
- `php spark install:doctor`
- `php spark biometric:doctor`
- `php spark support:diagnostics`
- `php spark release:gate`
- smoke browser/runtime opcional com `--run-smoke`
