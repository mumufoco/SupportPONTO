# Pacote 451 — Testes automatizados essenciais

## Objetivo

Criar uma base mínima de testes bloqueantes para impedir regressões críticas em estrutura, rotas, migrations, models/schema, instalador, autenticação, permissões e integridade do pacote.

## Problema tratado

O projeto tinha muitos testes estáticos incrementais, mas faltava um gate essencial único, fácil de executar e capaz de falhar o release mesmo quando o `vendor/` ainda não estivesse instalado no ambiente de empacotamento.

## Arquivos criados

- `tools/quality/essential-test-runner.php`
- `scripts/testing/essential-regression-gate.sh`
- `tests/Feature/Package451EssentialAutomatedTestsStaticTest.php`
- `docs/quality/TESTES_AUTOMATIZADOS_ESSENCIAIS.md`
- `docs/releases/PACOTE_451_TESTES_AUTOMATIZADOS_ESSENCIAIS.md`

## Arquivos atualizados

- `composer.json`
- `public/version.json`

## Scripts adicionados

- `composer test:essential`
- `composer test:release-critical`

## Cobertura mínima adicionada

- Testes de estrutura essencial.
- Testes de rotas críticas e filtros de API.
- Testes de migrations críticas.
- Testes de contrato model/schema.
- Testes de instalador, diagnóstico e segurança destrutiva.
- Testes de autenticação, primeiro acesso e Argon2id.
- Testes de permissões/RBAC.
- Testes de integridade do pacote completo.

## Validação executada

- `php -l tools/quality/essential-test-runner.php`
- `php -l tests/Feature/Package451EssentialAutomatedTestsStaticTest.php`
- `bash scripts/testing/essential-regression-gate.sh`
- `composer.json` validado como JSON.
- `.zip` validado com `unzip -t`.

## Observação

O gate essencial executa sem Composer/vendor. Quando `vendor/` estiver presente, o mesmo script também executa o teste PHPUnit do pacote.
