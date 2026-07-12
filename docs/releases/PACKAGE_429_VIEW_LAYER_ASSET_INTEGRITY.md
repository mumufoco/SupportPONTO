# Pacote 429 — Integridade de views, layouts, componentes e assets

## Objetivo

Estabilizar a camada de interface do SupportPONTO e impedir que o pacote completo seja entregue com views, partials, componentes ou assets locais ausentes.

## Problemas corrigidos

- O dashboard administrativo referenciava componentes que não existiam no pacote.
- Views de ponto referenciavam `partials/flash_messages`, mas o alias não existia.
- A página de impressão de QR Code referenciava `public/assets/js/qrcode.min.js`, ausente no pacote.
- O layout moderno referenciava arquivos em `public/assets/modern`, ausentes no pacote.
- Não existia gate bloqueante específico para integridade de views/assets.

## Arquivos principais adicionados

```text
app/Views/components/observability/dashboard_refresh_runtime.php
app/Views/components/dashboard/summary_stat_card.php
app/Views/components/admin/section_action_footer.php
app/Views/components/admin/alert_stack.php
app/Views/dashboard/partials/_admin_observability_widget.php
app/Views/dashboard/partials/admin_system_incident_widget.php
app/Views/dashboard/partials/admin_database_observability_widget.php
app/Views/dashboard/partials/admin_deepface_observability_widget.php
app/Views/dashboard/partials/admin_settings_cache_widget.php
app/Views/dashboard/partials/admin_performance_index_widget.php
app/Views/dashboard/partials/admin_report_performance_widget.php
app/Views/dashboard/partials/admin_rate_limit_widget.php
app/Views/dashboard/partials/admin_feature_flags_widget.php
app/Views/dashboard/partials/admin_database_observability_widget_refresh.php
app/Views/dashboard/partials/admin_deepface_observability_widget_refresh.php
app/Views/dashboard/partials/admin_feature_flags_widget_refresh.php
app/Views/dashboard/partials/admin_system_incident_widget_refresh.php
app/Views/partials/flash_messages.php
public/assets/js/qrcode.min.js
public/assets/modern/css/dashboard.css
public/assets/modern/css/sidebar.css
public/assets/modern/css/components.css
public/assets/modern/js/dashboard.js
public/assets/modern/js/sidebar.js
public/assets/modern/js/theme-switcher.js
public/assets/modern/images/favicon.ico
```

## Gate criado

```text
tools/release/audit-view-integrity.php
scripts/testing/view-integrity-gate.sh
tests/Feature/Package429ViewLayerIntegrityStaticTest.php
```

O gate valida:

- views obrigatórias;
- layouts principais;
- includes e extends literais;
- chamadas `view(...)` literais;
- `$routes->view(...)`;
- assets locais referenciados por `asset_url(...)`, `base_url(...)`, `site_url(...)`, `src` e `href`;
- ícones do `manifest.webmanifest`;
- contrato mínimo de seções dos layouts.

## Integrações

Adicionados comandos Composer:

```bash
composer run audit:views
composer run test:view-integrity
```

O gate foi integrado aos scripts:

```text
scripts/release/build-release-package.sh
scripts/release/build-source-package.sh
tools/release/audit-package-integrity.php
```

## Observação controlada

O gate registra um aviso não bloqueante para `reports/` em `ReportController`, porque a view de relatório é montada dinamicamente a partir de tipos já controlados pelo controller. Essa referência dinâmica será tratada com mais profundidade nos pacotes de relatórios.
