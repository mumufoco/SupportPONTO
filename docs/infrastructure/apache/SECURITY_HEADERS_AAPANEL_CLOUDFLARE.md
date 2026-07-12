# Apache/aaPanel/Cloudflare — headers de segurança

## Fonte principal

O SupportPONTO aplica os headers de segurança no filtro `App\Filters\SecurityHeaders`, carregado como `security-headers` em `app/Config/Filters.php`.

O bloco em `public/.htaccess` existe apenas como fallback para arquivos estáticos servidos diretamente pelo Apache.

## Headers esperados

- `X-Frame-Options: DENY`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: geolocation=(), camera=(self), microphone=(), payment=(), usb=(), fullscreen=(self)`
- `X-Permitted-Cross-Domain-Policies: none`
- `Content-Security-Policy: ...`
- `Strict-Transport-Security: ...` somente quando HTTPS estiver confirmado

## HSTS com Cloudflare/aaPanel

Não ative HSTS antes de validar:

1. o domínio final abre em HTTPS sem erro de certificado;
2. Cloudflare está em modo SSL/TLS Full ou Full Strict;
3. não existe redirect loop entre aaPanel, Apache/Nginx e Cloudflare;
4. assets, PWA, câmera e QR carregam sem mixed content;
5. subdomínios também aceitam HTTPS caso use `includeSubDomains`.

Depois da validação, ative no `.env`:

```env
SECURITY_HSTS_ENABLED=true
SECURITY_HSTS_MAX_AGE=31536000
SECURITY_HSTS_INCLUDE_SUBDOMAINS=true
SECURITY_HSTS_PRELOAD=false
```

Use `SECURITY_HSTS_PRELOAD=true` somente se o domínio atender aos requisitos de preload e se a decisão for permanente.

## Exemplo Apache vhost para estáticos

```apache
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "DENY"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "geolocation=(), camera=(self), microphone=(), payment=(), usb=(), fullscreen=(self)"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains" env=HTTPS
</IfModule>
```

Em Cloudflare, se o Apache receber HTTP interno, prefira deixar HSTS no filtro PHP com `SECURITY_HSTS_ENABLED=true`, pois ele reconhece `X-Forwarded-Proto` e `CF-Visitor`.
