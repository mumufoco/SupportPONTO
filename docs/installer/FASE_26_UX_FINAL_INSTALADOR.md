# Fase 26 — UX final do fluxo automático do instalador

Versão: **SupportPONTO v1.1.492**

## Objetivo

Transformar o wizard do instalador em um fluxo operacional claro para usuário leigo e técnico, separando preparo do servidor, vendor, requisitos, banco, administrador, instalação, pós-instalação e conclusão.

## Entregas

- Reorganização visual do wizard em etapas operacionais.
- Painel fixo **O que fazer agora** com instrução contextual por etapa.
- Botões de copiar comando para blocos técnicos.
- Log resumido antes da execução.
- Log técnico expansível na conclusão.
- Melhor sinalização de modo simples e modo técnico.
- Melhor suporte a acessibilidade com `aria-live`, `aria-current` e foco visível já existentes no wizard.

## Fluxo final

1. Preparação do servidor.
2. Vendor/Composer.
3. Requisitos essenciais.
4. Banco PostgreSQL.
5. Administrador inicial.
6. Instalação da aplicação.
7. Pós-instalação opcional.
8. Resumo e dry-run.
9. Conclusão e suporte.

## Regras preservadas

- O Web installer não executa comandos root.
- DeepFace, workers e systemd continuam pós-instalação.
- Dry-run continua recomendado antes da instalação real.
- Instalação destrutiva continua exigindo confirmação forte e backup real quando aplicável.
