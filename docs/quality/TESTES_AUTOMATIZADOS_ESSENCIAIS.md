# Testes automatizados essenciais — SupportPONTO

Este pacote cria uma base mínima bloqueante para reduzir regressões em releases.

## Camadas

1. **Gate estático independente de vendor**
   - Arquivo: `tools/quality/essential-test-runner.php`
   - Comando: `php tools/quality/essential-test-runner.php`
   - Não depende de Composer, CodeIgniter ou banco ativo.

2. **Script de release**
   - Arquivo: `scripts/testing/essential-regression-gate.sh`
   - Comando: `bash scripts/testing/essential-regression-gate.sh`
   - Executa lint do runner, gate estático e, quando `vendor/` existir, o teste PHPUnit do pacote.

3. **PHPUnit/CodeIgniter**
   - Arquivo: `tests/Feature/Package451EssentialAutomatedTestsStaticTest.php`
   - Comando: `composer test:essential` ou `vendor/bin/phpunit --filter Package451EssentialAutomatedTestsStaticTest`

## Suítes cobertas pelo gate essencial

- Estrutura de aplicação.
- Rotas modulares e rotas críticas.
- Migrations críticas recentes.
- Contrato básico model/schema.
- Instalador guiado e proteções destrutivas.
- Autenticação e primeiro login.
- Permissões/RBAC.
- Integridade do pacote e versão.

## Política de bloqueio

O comando `composer test:release-critical` deve falhar quando qualquer teste essencial falhar. Ele combina:

- `test:essential`;
- integridade de pacote;
- integridade de rotas;
- integridade model/schema.

## Uso recomendado antes de publicar ZIP

```bash
composer test:essential
composer test:release-critical
```

Quando o ambiente ainda não tiver `vendor/`, use:

```bash
php tools/quality/essential-test-runner.php
```

