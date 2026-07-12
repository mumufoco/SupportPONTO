# Pacote 424 — Auditoria bloqueante de integridade do artefato

## Objetivo

Criar uma barreira automática para impedir a geração ou entrega de um `.zip` completo com aplicação incompleta na raiz, metadados divergentes ou árvore paralela aninhada em `build/artifacts/source-package`.

## Problema corrigido

A auditoria da versão anterior identificou que o pacote podia ser declarado como completo mesmo quando a aplicação real estava aninhada em `build/artifacts/source-package`, enquanto a raiz executável continha menos controllers, services, views e rotas.

Esse pacote adiciona um gate independente de Composer/CodeIgniter para validar a árvore antes da entrega.

## Arquivos criados

- `tools/release/audit-package-integrity.php`
- `scripts/testing/package-integrity-gate.sh`
- `tests/Feature/Package424ArtifactIntegrityGateStaticTest.php`
- `docs/releases/PACKAGE_424_ARTIFACT_INTEGRITY_GATE.md`
- `docs/releases/PACKAGE_424_VALIDATION_REPORT.md`

## Arquivos alterados

- `scripts/release/build-release-package.sh`
- `scripts/release/build-source-package.sh`
- `composer.json`
- `release.json`
- `artifact-manifest.json`
- `public/version.json`
- `package.json`
- `package-lock.json`
- `tools/package.json`
- `tools/package-lock.json`
- `app/Support/ReleaseMetadata.php`
- `tools/installer/SupportPontoZeroInstaller.php`
- `public/css/app.css`
- `public/sw.js`
- `Dockerfile`
- `tests/Feature/Package422InstallerCompleteRewriteStaticTest.php`
- `tests/Feature/Package423CompleteRootPackageStaticTest.php`

## Validações bloqueantes implementadas

O gate reprova o pacote se encontrar:

- `release.json`, `artifact-manifest.json` ou `public/version.json` ausentes ou inválidos;
- versões divergentes entre metadados principais;
- `artifact_type` diferente de `complete-source-package`;
- `deployable` diferente de `true` para o pacote completo;
- `package.json` com versão diferente da versão principal;
- arquivos essenciais ausentes;
- diretórios essenciais ausentes;
- rotas modulares ausentes;
- contagens mínimas insuficientes de aplicação;
- `build/artifacts/source-package` dentro do pacote final;
- `.zip` de source/release aninhado dentro de `build/artifacts`;
- árvore paralela de aplicação fora da raiz executável;
- scripts de release sem chamada ao gate.

## Contagens mínimas exigidas

| Item | Mínimo |
|---|---:|
| Arquivos PHP em `app/` | 750 |
| Rotas modulares | 11 |
| Controllers | 70 |
| Services | 250 |
| Models | 25 |
| Views | 190 |
| Migrations | 55 |
| Testes Feature | 100 |

## Uso manual

```bash
php tools/release/audit-package-integrity.php
```

Com relatório JSON:

```bash
php tools/release/audit-package-integrity.php --report=build/runtime-validation/package-integrity-gate.json
```

Via wrapper:

```bash
bash scripts/testing/package-integrity-gate.sh
```

Via Composer:

```bash
composer run audit:package
composer run test:package-integrity
```

## Integração com release

Os scripts abaixo agora executam o gate antes de continuar:

- `scripts/release/build-release-package.sh`
- `scripts/release/build-source-package.sh`

## Resultado esperado

Se o pacote voltar a ficar incompleto, com rotas ausentes, source-package aninhado ou metadados incoerentes, o release falha imediatamente antes da entrega.
