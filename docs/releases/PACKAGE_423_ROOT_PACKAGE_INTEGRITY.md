# Pacote 423 — Reconstrução do `.zip` completo verdadeiro

## Objetivo

Restaurar a aplicação completa na raiz executável do SupportPONTO e remover a dependência de `build/artifacts/source-package` como aplicação paralela.

## Correções aplicadas

- A árvore completa presente em `build/artifacts/source-package` foi promovida para a raiz executável.
- As correções atuais do pacote 422 foram preservadas por sobreposição, incluindo o instalador guiado.
- O diretório `build/artifacts/source-package` foi removido do pacote final.
- O arquivo `build/artifacts/supportponto-source-package.zip` foi removido do pacote final.
- Metadados principais foram atualizados para `v1.1.423`.
- Foi adicionado teste estático para impedir regressão da raiz incompleta.

## Contagens validadas

- `app/`: 801 arquivos.
- `app/Config/Routes`: 11 arquivos de rota.
- `app/Controllers`: 74 controllers PHP.
- `app/Services`: 270 services PHP.
- `app/Views`: 198 arquivos de view.

## Limite deste pacote

Este pacote corrige a integridade da árvore entregue. Ele não corrige ainda migrations, divergências model/schema, segurança avançada ou instalação limpa real em PostgreSQL; esses pontos seguem nos pacotes seguintes já planejados.
