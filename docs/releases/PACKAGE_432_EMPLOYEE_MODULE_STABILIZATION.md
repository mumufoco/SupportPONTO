# Pacote 432 — Estabilização do módulo de colaboradores

## Objetivo

Estabilizar o módulo de colaboradores para que o cadastro, edição, listagem, vínculo com usuário, vínculo com ponto e vínculo com biometria não quebrem por divergência entre formulário, validação, payload, model e banco.

## Problemas corrigidos

- O formulário enviava `department_id`, `position_id`, `work_unit_id`, `employee_code` e `pis`, mas o payload antigo esperava outros nomes.
- O cadastro exigia campos trabalhistas e documentais nas regras, mas a tela não expunha todos os campos obrigatórios.
- O serviço de cadastro bloqueava a criação apenas por warnings de catálogo, mesmo quando o usuário poderia informar texto manual.
- `EmployeeModel` mantinha validação rígida com `required`, o que podia quebrar instalador, seeders e rotinas internas que criam administradores ou funcionários de sistema.
- A tabela `employees` não preservava IDs de catálogo selecionados na interface.
- A tela de colaboradores não carregava jornadas (`work_shifts`) nas opções do formulário.
- Não existia gate específico para impedir regressões no módulo de colaboradores.

## Arquivos alterados

- `app/Models/EmployeeModel.php`
- `app/Services/Employees/Management/EmployeeFormSupportService.php`
- `app/Services/Employees/Management/EmployeePayloadBuilder.php`
- `app/Services/Employees/Management/EmployeeRegistrationService.php`
- `app/Views/employees/create.php`
- `app/Views/employees/edit.php`
- `app/Views/employees/partials/_personal_data.php`
- `app/Views/employees/partials/_professional_data.php`
- `app/Views/employees/partials/_operational_settings.php`
- `app/Views/employees/partials/create_scripts.php`
- `app/Views/employees/partials/edit_scripts.php`
- `tools/release/audit-package-integrity.php`
- `scripts/release/build-release-package.sh`
- `scripts/release/build-source-package.sh`
- `composer.json`
- `release.json`
- `artifact-manifest.json`
- `public/version.json`

## Arquivos criados

- `app/Database/Migrations/2026-05-12-0432_StabilizeEmployeeModuleSchema.php`
- `tools/release/audit-employee-module-integrity.php`
- `scripts/testing/employee-module-integrity-gate.sh`
- `tests/Feature/Package432EmployeeModuleIntegrityStaticTest.php`
- `docs/releases/PACKAGE_432_EMPLOYEE_MODULE_STABILIZATION.md`
- `docs/releases/PACKAGE_432_VALIDATION_REPORT.md`

## Mudanças funcionais

### Formulário de colaboradores

A tela de criação/edição agora expõe os campos exigidos pelo fluxo corporativo:

- identificação;
- documentos pessoais;
- endereço;
- unidade;
- departamento;
- cargo;
- contrato;
- jornada;
- CTPS/PIS;
- dados bancários;
- senha inicial;
- ativação;
- parâmetros de ponto remoto/geolocalização.

### Normalização de catálogos

`EmployeeFormSupportService` agora normaliza:

- `work_unit_id` → `work_unit`;
- `department_id` → `department`;
- `position_id` → `position`;
- `employee_code` → `unique_code`;
- `pis` → `pis_pasep`.

Quando o catálogo existe, o texto é resolvido pelo nome ativo da tabela. Quando não existe, o cadastro aceita texto manual.

### Payload de gravação

`EmployeePayloadBuilder` agora grava campos canônicos e aliases compatíveis:

- texto e ID de unidade/departamento/cargo;
- `pis` e `pis_pasep`;
- `unique_code` a partir de `employee_code`;
- horários canônicos e aliases;
- parâmetros de ponto;
- dados documentais e bancários.

### Banco de dados

A migration do pacote adiciona ao schema:

- `employees.work_unit_id`;
- `employees.department_id`;
- `employees.position_id`;
- índices de apoio para filtros por departamento/unidade/cargo.

### Segurança operacional do Model

`EmployeeModel` deixou de ter validação rígida com `required` no próprio Model. A validação completa do cadastro fica em `EmployeeValidationRulesProvider`, enquanto o Model permanece seguro para instalador, seeders, autocadastro, rotinas internas e atualizações parciais.

## Novo gate bloqueante

Foi criado:

```bash
php tools/release/audit-employee-module-integrity.php
bash scripts/testing/employee-module-integrity-gate.sh
composer run audit:employees
composer run test:employee-module-integrity
```

O gate valida:

- arquivos essenciais do módulo;
- rotas de colaboradores;
- métodos do controller;
- campos obrigatórios nos formulários;
- aliases tratados pelo payload builder;
- IDs de catálogo no Model e migration;
- ausência de bloqueio por warnings de catálogo;
- validação flexível do Model para não quebrar instalador/seeders.

## Resultado esperado

O módulo de colaboradores passa a ter contrato estático entre tela, validação, payload, model e banco, reduzindo falhas de cadastro, update e instalação.
