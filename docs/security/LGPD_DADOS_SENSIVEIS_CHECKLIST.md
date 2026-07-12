# Checklist LGPD — Dados pessoais e sensíveis

## Inventário de tratamento

- [x] Mapear categorias de dados pessoais em `PersonalDataInventoryService`.
- [x] Separar dados sensíveis de dados pessoais comuns.
- [x] Registrar finalidade, base legal, retenção e perfis autorizados.
- [x] Evitar exportar templates biométricos, hashes completos e caminhos físicos.

## Consentimento e bases legais

- [x] Registrar aceite/revogação com IP, user-agent, versão e hash de evidência.
- [x] Diferenciar consentimento opcional de tratamento obrigatório por contrato/lei.
- [x] Revogação biométrica aciona expurgo controlado quando aplicável.
- [x] Solicitações do titular são registradas com SLA e auditoria.

## Retenção e minimização

- [x] Políticas parametrizadas por `.env`.
- [x] Exportações LGPD expiram e podem ser expurgadas.
- [x] Biometria tem retenção reduzida e expurgo separado.
- [x] Desativação preserva registros legais antes de anonimização.

## Direitos do titular

- [x] Portal exibe inventário e políticas de retenção.
- [x] Exportação inclui inventário, retenção e metadados tratados.
- [x] Fluxo para solicitar desativação/revisão biométrica.
- [x] DPO/Admin possui endpoints controlados para desativar, anonimizar e expurgar biometria.

## Auditoria

- [x] Eventos LGPD usam `PrivacyAuditLoggerService`.
- [x] Expurgos biométricos registram resultado e motivo.
- [x] Anonimização exige confirmação forte no endpoint administrativo.
