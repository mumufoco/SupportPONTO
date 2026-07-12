# Pacote 426 — Restauração e validação completa das rotas

## Objetivo

Garantir que as rotas modulares do SupportPONTO estejam presentes na raiz executável, com controllers, métodos, views e filtros resolvíveis antes da geração do `.zip` completo.

## Problema tratado

Depois da reconstrução da raiz no Pacote 423 e dos gates estruturais dos Pacotes 424 e 425, ainda faltava um bloqueio específico para impedir que uma rota apontasse para classe, método, view ou filtro inexistente.

## Arquivos principais criados ou alterados

- `tools/release/audit-route-integrity.php`
- `scripts/testing/route-integrity-gate.sh`
- `tests/Feature/Package426RouteIntegrityStaticTest.php`
- `tools/release/audit-package-integrity.php`
- `scripts/release/build-release-package.sh`
- `scripts/release/build-source-package.sh`
- `composer.json`
- `release.json`
- `artifact-manifest.json`
- `public/version.json`

## Validações implementadas

O gate de rotas valida, sem depender do CodeIgniter ou do Composer:

- presença de `app/Config/Routes.php`;
- `AutoRoute` desativado;
- carregamento modular de `app/Config/Routes/*.php`;
- presença dos 11 módulos de rota canônicos;
- nomes de rotas essenciais;
- duplicidade de nomes de rota;
- classes de controllers referenciadas nas rotas;
- métodos de controllers referenciados nas rotas;
- views usadas por `$routes->view()`;
- filtros usados nas rotas;
- aliases e classes de filtros em `app/Config/Filters.php`.

## Comandos disponíveis

```bash
composer run audit:routes
composer run test:route-integrity
php tools/release/audit-route-integrity.php --report=build/runtime-validation/route-integrity-gate.json
```

## Integração de release

Os scripts abaixo agora executam o gate de rotas antes do gate geral de pacote:

- `scripts/release/build-release-package.sh`
- `scripts/release/build-source-package.sh`

O gate geral `tools/release/audit-package-integrity.php` também passou a executar o gate de rotas internamente.

## Contagens validadas no pacote

- módulos de rota: 11
- definições de rota: 464
- rotas nomeadas: 461
- referências controller::method: 372
- referências de view em rota: 3
- filtros usados em rotas: 32
- aliases de filtro: 19

## Resultado

O Pacote 426 bloqueia regressões de rota antes da entrega do `.zip` completo e reduz o risco de telas quebradas por rotas apontando para classes, métodos, views ou filtros inexistentes.
