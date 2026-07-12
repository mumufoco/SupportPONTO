# Pacote 465 — Wizard visual real do instalador

## Objetivo

Transformar o instalador web do SupportPONTO em um wizard visual real, mais próximo do fluxo profissional estilo WordPress, sem reabrir riscos de instalação pesada via navegador.

## Implementado

- Wizard web em 7 etapas:
  1. Boas-vindas;
  2. Verificação de requisitos;
  3. Configuração do banco PostgreSQL;
  4. Configuração da aplicação e administrador inicial;
  5. Dependências e serviços;
  6. Resumo e confirmação;
  7. Conclusão e acompanhamento.
- Navegação visual por etapas com botões Voltar/Avançar.
- Resumo dinâmico antes do dry-run/instalação.
- Link direto para diagnóstico JSON.
- Link direto para último relatório sanitizado.
- Orientações CLI para dependências e serviços.
- Preservação de CSRF, confirmação destrutiva e bloqueio por installed.lock.
- DeepFace, Node/Cypress, Composer/vendor e systemd continuam fora do fluxo web.

## Segurança preservada

- Nenhuma senha inicial é gravada no `.env`.
- A instalação web não executa pacotes do sistema nem dependências pesadas.
- Reset destrutivo continua exigindo confirmação forte.
- O instalador continua bloqueado após `installed.lock`.

## Validação

- `php -l tools/installer/SupportPontoZeroInstaller.php`
- `php tools/quality/installer-wizard-audit.php`
- `bash scripts/testing/installer-wizard-gate.sh`
