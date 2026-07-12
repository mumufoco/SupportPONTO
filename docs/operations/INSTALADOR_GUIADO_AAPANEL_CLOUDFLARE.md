# Instalador guiado SupportPONTO — aaPanel, open_basedir e Cloudflare

## Objetivo
Orientar a execução segura do instalador web/CLI em servidores aaPanel, especialmente quando há restrições de `open_basedir`, PHP-FPM separado por domínio e Cloudflare Tunnel/Proxy.

## Fluxo recomendado

1. Acesse `/install` para abrir a interface guiada.
2. Execute primeiro o diagnóstico.
3. Corrija bloqueios de PHP/extensões/permissões antes de instalar.
4. Execute `dry-run` com as credenciais reais do banco.
5. Execute a instalação somente após o dry-run não apontar bloqueios críticos.
6. Copie a senha temporária do admin imediatamente ao final da instalação.
7. Remova ou bloqueie acesso público ao instalador após validar o sistema.

## Extensões PHP esperadas

- `curl`
- `fileinfo`
- `intl`
- `json`
- `mbstring`
- `openssl`
- `pdo`
- `pdo_pgsql`
- `pgsql`
- `xml`
- `zip`

No aaPanel, confirme que as extensões foram habilitadas no PHP vinculado ao site/domínio, não apenas no PHP CLI global.

## open_basedir

Quando `open_basedir` estiver ativo, o PHP pode gerar warnings ao verificar Composer em `/usr/bin/composer` ou `/usr/local/bin/composer`.

Opções seguras:

- instalar `vendor/` por SSH antes de abrir o instalador;
- permitir temporariamente o caminho do Composer no `open_basedir` do domínio;
- copiar `composer.phar` para `writable/composer/composer.phar`;
- liberar também a raiz do projeto, `writable/` e diretório temporário do PHP.

## Cloudflare / proxy reverso

- Confirme que a URL informada em `app_url` usa o esquema público correto (`https://`).
- Evite loop de redirect: alinhe SSL do Cloudflare, servidor e `.env`.
- O instalador não força HSTS durante instalação; habilite HSTS somente após confirmar HTTPS estável.

## Logs e relatórios

- Logs: `writable/logs/installer-YYYY-MM-DD.log`
- Relatório sanitizado: `writable/installer/last-install-report.json`
- Fatal capturado: `writable/installer/last-fatal-error.json`
- Bloqueios de provisionamento: `writable/installer/provision-required.json`

## Reset controlado

O reset controlado remove apenas o estado do instalador, como `installed.lock`. Ele não apaga banco, `.env` ou `vendor`.

CLI:

```bash
php tools/installer/install_cli.php --reset --token=TOKEN_DO_ENV
```

Web:

- informe o token de reinstalação/reset na tela do instalador.
