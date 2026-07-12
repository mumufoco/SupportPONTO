# Pacote 446 — LGPD e dados sensíveis

## Objetivo

Corrigir riscos de privacidade no tratamento de CPF, dados trabalhistas, geolocalização, autenticação e biometria.

## Entregas

- Inventário centralizado de dados pessoais/sensíveis.
- Política de retenção configurável por `.env`.
- Exportação LGPD enriquecida com inventário e retenção.
- Redação segura de biometria nas exportações: sem template bruto, hash completo ou caminho físico.
- Registro de solicitações do titular em `lgpd_subject_requests`.
- Endpoints administrativos protegidos para desativação, anonimização e expurgo biométrico.
- Evidência de consentimento com hash, motivo de revogação e contexto de processamento.
- Migração incremental `2026-05-17-0446_LgpdPrivacyControls.php`.
- Checklist operacional em `docs/security/LGPD_DADOS_SENSIVEIS_CHECKLIST.md`.

## Arquivos principais

- `app/Services/LGPD/PersonalDataInventoryService.php`
- `app/Services/LGPD/DataRetentionPolicyService.php`
- `app/Services/LGPD/DataSubjectRightsService.php`
- `app/Services/LGPD/BiometricPrivacyGuardService.php`
- `app/Services/LGPD/PrivacyAuditLoggerService.php`
- `app/Controllers/LGPDController.php`
- `app/Config/Routes/70_reports_compliance.php`
- `app/Database/Migrations/2026-05-17-0446_LgpdPrivacyControls.php`
- `app/Views/lgpd/consents.php`

## Validação

- Rodar migrations em ambiente com PostgreSQL pronto.
- Validar `/lgpd/consents` com usuário autenticado.
- Validar `/lgpd/inventory` e `/lgpd/retention` autenticados.
- Validar que funcionário comum não acessa `/lgpd/admin/*`.
- Validar exportação LGPD e conferir que biometria aparece apenas como metadado redigido.
- Validar anonimização administrativa apenas com confirmação `ANONIMIZAR TITULAR {id}`.
