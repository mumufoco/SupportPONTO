# Package 488 — App/server provisioning separation

## Versão

SupportPONTO v1.1.489

## Correções

- Adicionado plano explícito de provisionamento do servidor.
- Criado comando `--server-provision-plan --json`.
- Adicionada rota Web `?action=server-provisioning-plan-json`.
- Wizard reorganizado de 8 para 9 etapas.
- Nova etapa 2: Preparação do servidor.
- Diagnóstico mantém instalação da aplicação separada de pacotes do sistema, DeepFace, serviços e permissões root.
- Plano salvo em `writable/installer/server-provisioning-plan.json`.

## Validação

- Sintaxe PHP do instalador.
- Diagnóstico core.
- Diagnóstico full leve.
- Plano de provisionamento em JSON.
- Auditoria de versão.
- Auditoria do wizard.
- Integridade do zip final.
