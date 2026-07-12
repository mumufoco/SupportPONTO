# Fase 19 — Etapa explícita de Vendor/Composer

Versão: **SupportPONTO v1.1.484**

## Objetivo

Transformar a ausência de `vendor/autoload.php` em uma etapa clara, guiada e segura do instalador, evitando que a instalação falhe tardiamente durante migrations ou seeders.

## Alterações

- Nova etapa visual: **Dependências PHP / Vendor**.
- O checkbox antigo foi removido da tela de banco.
- A instalação Web de vendor agora exige confirmação textual: `INSTALAR VENDOR`.
- O wizard apresenta três caminhos:
  - pacote de produção com `vendor/` incluído;
  - instalação via SSH;
  - instalação Web avançada, com confirmação explícita.
- A instalação continua usando PHP CLI validado, Composer com SHA384 e relatório em `writable/installer/composer-install-last.json`.

## Critério operacional

Para ambiente de produção e usuário leigo, o pacote recomendado continua sendo o pacote com `vendor/` incluído. A instalação Web de Composer é uma opção avançada e auditada.
