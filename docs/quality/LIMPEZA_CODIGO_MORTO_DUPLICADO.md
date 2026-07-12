# Limpeza de código morto e duplicado — Pacote 454

## Objetivo

Reduzir sujeira acumulada sem quebrar referências internas. Esta limpeza foi limitada a artefatos comprovadamente não necessários em runtime e wrappers legados sem consumo interno ativo.

## Itens removidos

### Artefatos gerados

- `deepface-api/__pycache__/`
- arquivos `.pyc` do módulo DeepFace
- relatórios antigos em `build/go-live-gate/`
- relatórios antigos em `build/final-production-readiness/`
- HTML/logs antigos em `build/installer-runtime-check/`

Esses arquivos são produto de execução local/testes e devem ser recriados pelos scripts quando necessário, nunca versionados no pacote final.

### Wrappers legados sem uso interno

- `app/Services/CSVServiceLegacyCore.php`
- `app/Services/ReportServiceLegacyCore.php`
- `app/Services/SMSServiceLegacyCore.php`
- `app/Services/TXTServiceLegacyCore.php`
- `app/Support/LegacyServiceMap.php`

Os serviços canônicos permanecem em:

- `app/Services/CSVService.php`
- `app/Services/ReportService.php`
- `app/Services/SMSService.php`
- `app/Services/TXTService.php`

## Wrappers preservados propositalmente

Alguns wrappers continuam no pacote porque existem rotas ou entrypoints que dependem deles:

- `app/Controllers/ProfileController.php`
- `app/Controllers/Admin/SettingsController.php`
- `app/Controllers/Admin/DashboardController.php`
- `app/Controllers/Gestor/DashboardController.php`
- `tools/installer/install_cli.php`
- `tools/installer/install_web.php`
- `install/index.php`
- `public/install.php`
- `public/install/index.php`

Eles são compatibilidade ativa, não código morto.

## Auditoria bloqueante adicionada

Foi criado o script:

```bash
php tools/quality/dead-code-audit.php
```

Ele valida:

- ausência de artefatos gerados;
- ausência de wrappers `*LegacyCore` obsoletos na árvore ativa;
- ausência de arquivos de runtime versionados em `writable/`;
- integridade dos entrypoints do instalador;
- referências básicas de rotas para controllers existentes;
- referências simples de `view()` para arquivos existentes.

## Política daqui em diante

- `build/` deve ser usado apenas como saída temporária de scripts.
- `writable/` deve conter apenas marcadores seguros, como `.gitkeep`, `index.html` e `.htaccess`.
- wrappers só podem permanecer quando houver rota, serviço ou compatibilidade documentada.
- novas limpezas devem rodar `composer test:dead-code` antes de gerar release.
