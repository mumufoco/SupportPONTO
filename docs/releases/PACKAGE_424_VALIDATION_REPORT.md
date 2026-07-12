# Relatório de validação — Pacote 424

## Versão

- Sistema: SupportPONTO
- Pacote: 424
- Versão: `v1.1.424`
- Foco: auditoria bloqueante de integridade do artefato

## Validações executadas

```bash
php -l tools/release/audit-package-integrity.php
php tools/release/audit-package-integrity.php --report=/mnt/data/pkg424_integrity_report.json
bash scripts/testing/package-integrity-gate.sh /mnt/data/pkg424_wrapper_report.json
```

## Resultado do gate

O gate foi aprovado com as seguintes contagens:

| Item | Quantidade detectada |
|---|---:|
| Arquivos PHP em `app/` | 790 |
| Arquivos de rotas modulares | 11 |
| Controllers PHP | 74 |
| Services PHP | 270 |
| Models PHP | 31 |
| Views | 198 |
| Migrations PHP | 60 |
| Testes Feature PHP | 251 |

## Validação de artefato aninhado

Confirmado que o pacote final não deve conter:

- `build/artifacts/source-package`
- `build/artifacts/release-package/source-package`
- `build/artifacts/supportponto-source-package.zip`
- `build/artifacts/supportponto-release-package.zip`
- `source-package`

## Validação de metadados

Arquivos sincronizados para `v1.1.424`:

- `release.json`
- `artifact-manifest.json`
- `public/version.json`
- `package.json`
- `tools/package.json`
- `Dockerfile`
- `app/Support/ReleaseMetadata.php`
- `public/sw.js`
- `public/css/app.css`
- `tools/installer/SupportPontoZeroInstaller.php`

## Observação

Este pacote não corrige ainda migrations, models, permissões, segurança destrutiva do instalador ou instalação limpa real. Ele cria o bloqueio necessário para impedir que qualquer pacote futuro seja entregue com a raiz incompleta ou com aplicação paralela aninhada.
