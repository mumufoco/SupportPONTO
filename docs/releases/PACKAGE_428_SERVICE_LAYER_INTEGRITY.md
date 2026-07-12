# Pacote 428 — Integridade da camada de Services

## Objetivo

Estabilizar a camada `app/Services` e impedir que o pacote completo seja gerado quando houver service com namespace incorreto, classe principal ausente, importação `App\\*`/`Config\\*` quebrada ou referência `service()` não rastreável.

## Problemas tratados

- A camada de services era extensa e sem gate próprio.
- Dependências internas ausentes poderiam permanecer invisíveis até execução real.
- `DataExportCollectorService` e `DataExportService` importavam `AttendanceModel` e `VacationModel`, classes que não existem na árvore atual.
- `TimePunchFlowService` usava `service('faceRecognition')`, mas `Config\\Services` não expunha esse serviço explicitamente.

## Alterações principais

### Novo gate bloqueante

Criado:

```text
tools/release/audit-service-integrity.php
scripts/testing/service-integrity-gate.sh
```

O gate valida:

- existência de módulos críticos em `app/Services`;
- quantidade mínima de services;
- namespace PSR-4 esperado por caminho;
- classe/interface/trait/enum principal com nome compatível com o arquivo;
- imports `App\\*` e `Config\\*` resolvíveis na árvore local;
- referências diretas `new \\App\\*`, `\\Config\\*::` e tipos FQCN evidentes;
- referências `service('...')` contra `Config\\Services` e traits em `app/Config/Concerns`.

### Correções de services

- Removidas dependências inexistentes de `AttendanceModel` e `VacationModel` nos services LGPD.
- `collectAttendance()` passou a consultar `time_punches` com proteção `tableExists()`.
- `collectVacations()` passou a retornar lista vazia quando a tabela `vacations` não existir, evitando quebra por model ausente.
- Adicionado `Config\\Services::faceRecognition()` para registrar explicitamente `App\\Services\\Biometric\\FaceRecognitionService`.

### Integração com release

Atualizados:

```text
composer.json
scripts/release/build-release-package.sh
scripts/release/build-source-package.sh
tools/release/audit-package-integrity.php
release.json
artifact-manifest.json
public/version.json
```

Novos comandos:

```bash
composer run audit:services
composer run test:service-integrity
php tools/release/audit-service-integrity.php
bash scripts/testing/service-integrity-gate.sh
```

## Resultado esperado

A camada de services passa a ter validação bloqueante própria e o pacote completo não deve ser gerado quando houver service com dependência interna ausente.
