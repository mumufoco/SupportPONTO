# Arquivo técnico legado

Este diretório guarda artefatos removidos da árvore ativa por limpeza controlada.
Eles permanecem apenas para referência histórica, comparação em auditorias e
apoio a testes estáticos que verificam a segregação entre código ativo e legado.

## Itens arquivados nesta rodada
- `app/Controllers/TestController.php` — controller de teste removido da árvore ativa.
- `app/Views/placeholders/missing_view.php` — placeholder antigo substituído por `feature_unavailable.php`.
- `app/Views/biometric/manage_employee.php` — placeholder antigo substituído pelos fluxos biométricos canônicos.

## Regras
- Não restaurar esses arquivos para a árvore ativa sem pacote corretivo explícito.
- Arquivos ativos de compatibilidade controlada devem permanecer na árvore principal e ser documentados separadamente.

- `archive/app/Controllers/JustificationsController.php` — implementação legada completa do domínio de justificativas, arquivada após consolidação da trilha canônica em `App\Controllers\Timesheet\JustificationController`.

- `app/Controllers/FeatureAccessController.php` — controller de superfícies despublicadas de analytics e fluxos demonstrativos, arquivado após remoção da superfície órfã sem rotas ativas.
- `app/Views/dashboard/analytics.php` — dashboard analítico demonstrativo sem rota ativa, arquivado para evitar manutenção em superfície órfã.
- `app/Views/analytics/management.php` — tela demonstrativa de analytics gerenciais arquivada fora da árvore ativa.
- `app/Views/analytics/punch_intelligence.php` — tela demonstrativa de inteligência do ponto arquivada fora da árvore ativa.
- `app/Views/analytics/reports_advanced.php` — tela demonstrativa de relatórios avançados arquivada fora da árvore ativa.
- `app/Views/analytics/team_indicators.php` — tela demonstrativa de indicadores por equipe arquivada fora da árvore ativa.
- `app/Views/analytics/method_metrics.php` — tela demonstrativa de métricas por método arquivada fora da árvore ativa.
