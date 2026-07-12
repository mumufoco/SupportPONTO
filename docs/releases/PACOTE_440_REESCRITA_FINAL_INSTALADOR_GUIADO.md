# Pacote 440 — Reescrita final do instalador guiado completo

## Objetivo
Transformar o instalador do SupportPONTO em uma ferramenta confiável, guiada, autocontida e capaz de explicar qualquer bloqueio sem fatal error.

## Escopo entregue

- Reescrita do instalador zero-dependency em `tools/installer/SupportPontoZeroInstaller.php`.
- Entrada web preservada por:
  - `install/index.php`
  - `public/install.php`
  - `public/install/index.php`
  - `tools/installer/install_web.php`
- Entrada CLI preservada por:
  - `tools/installer/install_cli.php`
- Diagnóstico categorizado por:
  - PHP
  - extensões
  - funções PHP
  - permissões/writable
  - Composer/vendor
  - aplicação/arquivos essenciais
  - classes essenciais
  - rotas modulares
  - migrations
  - `.env`
  - banco PostgreSQL quando credenciais forem informadas
- Modo diagnóstico JSON:
  - Web: `/install?action=diagnose-json`
  - CLI: `php tools/installer/install_cli.php --diagnose`
- Modo dry-run:
  - Web: botão **Executar dry-run**
  - CLI: `php tools/installer/install_cli.php --dry-run ...`
- Modo instalação web e CLI.
- Reset controlado do estado do instalador:
  - remove apenas `installed.lock` e arquivos de estado do instalador;
  - não remove `.env`, `vendor` ou banco;
  - exige token no web e token/`--force` no CLI.
- Logs JSONL em `writable/logs/installer-YYYY-MM-DD.log`.
- Relatórios sanitizados em `writable/installer/last-install-report.json`.
- Interceptação de fatal error com gravação de `writable/installer/last-fatal-error.json`.
- Mensagens específicas para aaPanel/open_basedir.
- Senha inicial continua sendo exibida apenas uma vez e não é persistida em relatório/.env.

## Comandos principais

```bash
php tools/installer/install_cli.php --diagnose
php tools/installer/install_cli.php --dry-run --app-url=https://ponto.supportsondagens.com.br/ --db-host=127.0.0.1 --db-name=supportponto --db-user=usuario --db-pass='senha' --admin-email=admin@dominio.com --admin-cpf=00000000000
php tools/installer/install_cli.php --install --app-url=https://ponto.supportsondagens.com.br/ --db-host=127.0.0.1 --db-name=supportponto --db-user=usuario --db-pass='senha' --admin-name='Administrador' --admin-email=admin@dominio.com --admin-cpf=00000000000
php tools/installer/install_cli.php --reset --token=TOKEN_DO_ENV
```

## Arquivos alterados/criados

- `tools/installer/SupportPontoZeroInstaller.php`
- `docs/releases/PACOTE_440_REESCRITA_FINAL_INSTALADOR_GUIADO.md`
- `docs/operations/INSTALADOR_GUIADO_AAPANEL_CLOUDFLARE.md`
- `tests/Feature/Package440GuidedInstallerStaticTest.php`
- `public/version.json`

## Critérios de validação

- O instalador deve abrir sem Composer/vendor.
- O diagnóstico deve retornar JSON mesmo com extensões ausentes.
- Erros de PHP devem ser capturados e reportados sem tela branca.
- `.env` não deve receber senha temporária do admin.
- `last-install-report.json` não deve persistir senhas, tokens ou secrets.
- `dry-run` não deve alterar banco, `.env`, vendor ou lock.
- Reset controlado não deve apagar banco, `.env` ou vendor.

## Observação sobre ambiente

No sandbox de validação, algumas extensões PHP necessárias ao projeto não estão carregadas (`pdo_pgsql`, `pgsql`, `mbstring`, `intl`, `curl`, `xml`, `zip`). O novo instalador reporta isso corretamente como bloqueio de ambiente, sem fatal error.
