# Fase 5 — UX profissional do instalador

Versão: **SupportPONTO v1.1.476**

## Objetivo

Elevar a experiência do instalador sem alterar páginas públicas, CSS/JS públicos ou layout da aplicação principal.

## Entregas

- CSS do instalador separado em `tools/installer/assets/installer.css`.
- JavaScript do wizard separado em `tools/installer/assets/installer.js`.
- Fallback inline mínimo mantido dentro do instalador para não quebrar caso os assets sejam removidos acidentalmente.
- Cabeçalho visual com marca SupportPONTO.
- Barra de progresso acessível por etapa.
- `aria-live` para informar troca de etapa a leitores de tela.
- `aria-current="step"` na navegação do wizard.
- Modo simples e modo técnico para mensagens de orientação.
- Melhorias de foco visível, contraste, responsividade e botões.
- Mensagens reforçadas para dry-run, bloqueios, avisos e dependências.

## Garantias

- A Fase 5 não altera assets públicos da aplicação.
- A Fase 5 não altera rotas públicas.
- A Fase 5 não habilita instalação pesada de DeepFace/Node no web installer.
- A Fase 5 preserva os modos seguros anteriores: `clean_install`, `update` e `destructive_reinstall`.

## Arquivos principais

- `tools/installer/SupportPontoZeroInstaller.php`
- `tools/installer/assets/installer.css`
- `tools/installer/assets/installer.js`
- `tests/Feature/InstallerPhase5ProfessionalUxStaticTest.php`

## Critérios de validação

- O instalador deve continuar autocontido.
- O wizard deve ter 7 etapas.
- A barra de progresso deve refletir a etapa atual.
- Deve haver modo simples/técnico.
- CSS e JS do instalador devem estar fora do corpo principal do PHP.
- `php -l` no instalador deve passar.
- `installer-wizard-audit.php` deve continuar aprovado.
