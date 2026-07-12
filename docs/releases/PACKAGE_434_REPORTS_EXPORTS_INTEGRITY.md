# Pacote 434 — Relatórios, exportações e espelho de ponto

## Objetivo

Estabilizar a camada de relatórios do SupportPONTO, reduzindo risco de falhas por filtros inválidos, consultas amplas demais, exportações pesadas e acesso indevido a relatórios de outros departamentos.

## Correções aplicadas

- Criado `ReportRequestPolicyService` para normalizar e validar filtros antes da geração.
- Adicionado limite máximo de período por tipo de saída:
  - HTML: até 366 dias.
  - PDF/Excel/CSV/XML/TXT: até 186 dias.
  - AFD: até 92 dias.
- Reaplicado escopo de departamento para gestores em geração síncrona e assíncrona.
- `ReportController` agora repassa `currentUser` para `ReportCoordinatorService` em geração síncrona e enfileirada.
- Geradores de relatórios agora aplicam limite de registros nas consultas críticas.
- AFD passa a respeitar filtro de departamento quando não houver restrição superior resolvida pela autorização.
- Corrigida falha latente em `AuthorizationService::canAccessAsyncJob()` adicionando `normalizeInt()`, evitando fatal error ao consultar/download de job assíncrono.
- Criado gate bloqueante específico do módulo de relatórios.

## Arquivos principais alterados/criados

- `app/Services/Reports/ReportRequestPolicyService.php`
- `app/Services/Reports/ReportCoordinatorService.php`
- `app/Controllers/Report/ReportController.php`
- `app/Services/Reports/Concerns/ReportExecutionHelperTrait.php`
- `app/Services/Reports/Concerns/ReportExecutionGeneratorsTrait.php`
- `app/Services/AuthorizationService.php`
- `tools/release/audit-report-module-integrity.php`
- `scripts/testing/report-module-integrity-gate.sh`
- `tests/Feature/Package434ReportModuleIntegrityStaticTest.php`

## Gates integrados

- `composer run audit:reports`
- `composer run test:report-module-integrity`
- `tools/release/audit-package-integrity.php`
- `scripts/release/build-release-package.sh`
- `scripts/release/build-source-package.sh`

## Limites e decisões

Este pacote não executa instalação limpa real com PostgreSQL. Essa validação permanece prevista para o Pacote 452. A validação feita aqui é estática e estrutural, com foco em impedir regressões de relatórios/exportações.
