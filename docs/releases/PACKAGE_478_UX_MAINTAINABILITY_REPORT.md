# Package 478 — UX e Manutenibilidade

Release: `v1.1.478`

## Entrega

A Fase 13 introduz uma camada reutilizável de UX operacional para reduzir repetição de mensagens, melhorar acessibilidade básica e preparar a quebra progressiva de views grandes.

## Alterações aplicadas

- Novo componente `ux_guidance_panel`.
- Novo componente `action_feedback_region`.
- Novo CSS `supportponto-ux-maintenance-478.css`.
- Novo JS `supportponto-ux-maintenance-478.js`.
- Layout principal atualizado para carregar assets e feedback global.
- Layout kiosk atualizado para carregar assets.
- Dashboard administrativo, configurações, biometria facial e kiosk receberam orientação contextual.
- Auditoria estática e teste de fase adicionados.

## Risco controlado

As mudanças foram limitadas à camada visual e de orientação. Não foram alteradas queries, migrations, regras de NSR, HMAC, cálculo de jornada ou endpoints públicos.

## Validação

O pacote deve passar por:

```bash
php tests/Feature/TimesheetPhase13UxMaintainabilityStaticTest.php
php tools/release/audit-ux-maintainability-static.php
php tools/release/audit-version-consistency.php
```
