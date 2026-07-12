# Fase 21 — Separação de bloqueios, avisos e opcionais do instalador

Versão: **SupportPONTO v1.1.489**

## Objetivo

Evitar que o usuário confunda requisitos essenciais da instalação base com itens recomendados, bloqueios específicos de atualização/reinstalação e verificações opcionais pós-instalação.

## Alterações

- O diagnóstico agora expõe os grupos:
  - `core_blocking`: bloqueia instalação agora.
  - `mode_blocking`: bloqueia apenas atualização/reinstalação destrutiva no modo aplicável.
  - `recommended`: recomendações que não impedem instalação limpa.
  - `optional`: itens pós-instalação, como DeepFace, Python, TensorFlow, Node, Docker, WebSocket e systemd.
- `blocking` continua compatível e contém somente `core_blocking`.
- `psql` e `pg_dump` receberam mensagens contextuais.
- A tela de requisitos mostra quatro blocos visuais separados.
- O modo técnico continua exibindo avisos brutos para suporte.

## Resultado esperado

A instalação limpa deixa de parecer bloqueada por ferramentas auxiliares como `psql`, `pg_dump`, DeepFace, Node ou Docker. Apenas requisitos essenciais da aplicação base impedem a instalação.
