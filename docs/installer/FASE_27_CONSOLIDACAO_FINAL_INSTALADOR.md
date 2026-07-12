# Fase 27 — Consolidação final do instalador automático

Versão: **SupportPONTO v1.1.496**

## Objetivo

Consolidar em um pacote estável as correções do ciclo de estabilização do instalador automático, cobrindo os pacotes 481 a 491.

## Itens consolidados

- Detecção segura do PHP CLI do domínio.
- Execução de processos com timeout e proteção contra travamentos.
- Diagnóstico avançado leve, com biometria fora do bloqueio principal.
- Etapa explícita para Vendor/Composer.
- Pipeline oficial para pacote production-with-vendor.
- Separação visual entre bloqueios, avisos, recomendados e opcionais.
- Separação entre instalação da aplicação e provisionamento do servidor.
- Suporte a Composer offline validado por checksum.
- Refatoração inicial do instalador em componentes core.
- Artefatos de homologação clean-install.
- UX final do wizard com orientação contextual.

## Observação sobre vendor

Este pacote é o pacote fonte completo. Ele não inclui `vendor/` quando o ambiente de build não possui Composer/vendor materializado. Para gerar o pacote final com dependências PHP embutidas, use:

```bash
php tools/release/build-production-with-vendor.php --json
```

Quando `vendor/autoload.php` existir, o build gerará o artefato `production-with-vendor` e os arquivos de manifesto/checksum.

## Resultado esperado

O instalador deixa de misturar diagnóstico pesado, preparo do servidor e instalação da aplicação em um único bloqueio confuso. O fluxo final passa a ser guiado, auditável e adequado para suporte técnico.
