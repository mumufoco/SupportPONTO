# Package 469 — Installer Phase 4: Biometric Dependencies

Versão: **SupportPONTO v1.1.476**

## Escopo

Entrega da Fase 4 do roadmap do instalador: dependências biométricas, DeepFace, diagnóstico operacional e padronização de perfil recomendado para produção.

## Arquivos principais alterados/criados

- `tools/installer/SupportPontoZeroInstaller.php`
- `install/runtime/biometric-doctor.sh`
- `install/runtime/dependencies.catalog.json`
- `install/runtime/install-dependencies.sh`
- `tools/quality/dependency-catalog-audit.php`
- `tests/Feature/InstallerPhase4BiometricDependenciesStaticTest.php`
- `docs/installer/FASE_4_DEPENDENCIAS_BIOMETRICAS.md`
- `release.json`
- `public/version.json`

## Resultado técnico

- Criado script `biometric-doctor.sh` com JSON puro e logs separados.
- Criada ponte CLI `php tools/installer/install_cli.php --biometric-doctor`.
- Diagnóstico do instalador passou a incluir `biometric_runtime_diagnosis`.
- Catálogo recebeu `production_deepface_external_recommended`.
- Catálogo recebeu dependência `biometric-doctor`.
- Instalador web continua sem checkbox de DeepFace/Node.
- Relatório biométrico sanitizado é gravado em `writable/installer/biometric-doctor-last.json`.

## Validações executadas

```text
php -l tools/installer/SupportPontoZeroInstaller.php
php -l tools/quality/dependency-catalog-audit.php
bash -n install/runtime/biometric-doctor.sh
bash -n install/runtime/install-dependencies.sh
php tests/Feature/InstallerPhase4BiometricDependenciesStaticTest.php
php tools/quality/dependency-catalog-audit.php
php tools/quality/installer-wizard-audit.php
php tools/release/audit-version-consistency.php
unzip -t do pacote final
```

## Observação

O healthcheck de API externa não força falha por padrão para permitir instalação sem DeepFace ativo no primeiro boot. Use `--strict` em homologação/produção quando a biometria facial for requisito obrigatório do go-live.
