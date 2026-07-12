# Pacote 439 — Hardening HTTP, CSP, HSTS e headers

## Objetivo

Melhorar a proteção do navegador contra ataques de clickjacking, MIME sniffing, vazamento por referrer, permissões indevidas de APIs do browser, execução indevida de scripts e uso inseguro de HTTP.

## Arquivos principais alterados/criados

- `app/Config/App.php`
- `app/Config/ContentSecurityPolicy.php`
- `app/Config/Filters.php`
- `app/Filters/SecurityHeadersFilter.php`
- `app/Filters/SecurityHeaders.php`
- `app/Views/layouts/kiosk.php`
- `public/js/kiosk-icons.js`
- `public/.htaccess`
- `.env.example`
- `.env.production.example`
- `docs/infrastructure/apache/SECURITY_HEADERS_AAPANEL_CLOUDFLARE.md`
- `docs/infrastructure/nginx/SECURITY_HEADERS_AAPANEL_CLOUDFLARE.md`
- `docs/security/PACKAGE_439_CSP_INLINE_SCRIPT_MAP.md`
- `tests/Feature/Package439HttpSecurityHeadersStaticTest.php`

## Implementação

### Headers HTTP

O filtro `SecurityHeadersFilter` continua como fonte central e agora possui alias compatível `security-headers`, aplicado globalmente no pós-processamento das respostas.

Headers cobertos:

- `X-Frame-Options: DENY`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: geolocation=(), camera=(self), microphone=(), payment=(), usb=(), fullscreen=(self)`
- `Cross-Origin-Opener-Policy: same-origin`
- `Cross-Origin-Resource-Policy: same-origin`
- `Cross-Origin-Embedder-Policy: unsafe-none`
- `X-Permitted-Cross-Domain-Policies: none`
- `Content-Security-Policy`
- `Strict-Transport-Security`, condicionado a HTTPS confirmado

### CSP

`App::$CSPEnabled` foi ativado e a configuração de `ContentSecurityPolicy` ganhou flags por ambiente:

- `CSP_REPORT_ONLY`
- `CSP_UPGRADE_INSECURE_REQUESTS`
- `CSP_ALLOW_INLINE_STYLE`
- `CSP_ALLOW_INLINE_SCRIPT_ATTR`
- `CSP_CONNECT_SRC`

A política permanece compatível para não quebrar telas legadas que ainda dependem de estilos inline e atributos de script, mas `script-src` não permite curingas globais como `https:`.

### Scripts inline

Todos os `<script>` em views continuam exigindo nonce via `csp_script_nonce_attr()`.

Foi removido o inline simples de inicialização do Lucide no layout kiosk, movendo a lógica para `public/js/kiosk-icons.js`.

### HSTS

O HSTS deixou de depender automaticamente de `ENVIRONMENT=production`.

Agora ele só é enviado quando:

1. `SECURITY_HSTS_ENABLED=true` ou `APP_HSTS_ENABLED=true`; e
2. a requisição é reconhecida como HTTPS por `isSecure()`, `X-Forwarded-Proto`, `Forwarded` ou `CF-Visitor`.

Isso evita ativação prematura em aaPanel/Cloudflare quando ainda existe risco de redirect loop ou proxy mal configurado.

### Apache/Nginx/aaPanel/Cloudflare

Foram criados guias específicos para ativar headers em vhost/proxy e validar HSTS antes de usar `includeSubDomains` ou `preload`.

## Validação

- `php -l` em todos os PHP alterados/criados.
- Varredura de `<script>` em `app/Views` para confirmar uso de nonce.
- Varredura estática do filtro para confirmar headers obrigatórios.
- Varredura estática para confirmar que HSTS depende de flag explícita e contexto HTTPS.
- `php spark` e PHPUnit não foram executados no sandbox por ausência de `vendor/codeigniter4/framework/system/Boot.php` no pacote base.

## Resultado esperado

As respostas web passam a expor headers de segurança consistentes, CSP fica efetivamente ativa em modo compatível e HSTS pode ser habilitado com segurança apenas após validação real de HTTPS no ambiente final.
