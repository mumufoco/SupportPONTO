# Pacote 427 — Integridade MVC consolidada no pacote 428

## Objetivo

Validar o fluxo estático `rotas → controllers → views/services/dependências` sem carregar Composer ou CodeIgniter.

## Arquivos criados

```text
tools/release/audit-mvc-integrity.php
scripts/testing/mvc-integrity-gate.sh
tests/Feature/Package427MvcIntegrityStaticTest.php
```

## Validações

- quantidade mínima de controllers;
- controllers críticos presentes;
- namespace compatível com caminho;
- classe/trait principal compatível com arquivo;
- imports `App\\*` e `Config\\*` resolvíveis;
- views literais usadas em `view('...')` existentes;
- chamadas `service('...')` verificadas contra `Config\\Services` e services nativos conhecidos.

## Observação

O prefixo dinâmico `reports/` em `ReportController` é classificado como aviso controlado, porque o controller monta a view final dinamicamente a partir do tipo de relatório.
