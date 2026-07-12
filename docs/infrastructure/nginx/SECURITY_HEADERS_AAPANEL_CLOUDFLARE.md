# Nginx/aaPanel/Cloudflare — headers de segurança

## Fonte principal

O filtro `App\Filters\SecurityHeaders` é a fonte principal para respostas geradas pelo CodeIgniter.

Use headers no Nginx apenas como fallback para arquivos estáticos ou quando o proxy encerra TLS antes do PHP.

## Exemplo compatível

```nginx
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "DENY" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "geolocation=(), camera=(self), microphone=(), payment=(), usb=(), fullscreen=(self)" always;
add_header X-Permitted-Cross-Domain-Policies "none" always;

# Ativar somente depois de validar HTTPS, Cloudflare e subdomínios.
# add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
```

## HSTS

Ative HSTS no `.env` da aplicação após validação do domínio final:

```env
SECURITY_HSTS_ENABLED=true
SECURITY_HSTS_MAX_AGE=31536000
SECURITY_HSTS_INCLUDE_SUBDOMAINS=true
SECURITY_HSTS_PRELOAD=false
```

O filtro só envia `Strict-Transport-Security` quando `SECURITY_HSTS_ENABLED=true` e a requisição for reconhecida como HTTPS por `isSecure()`, `X-Forwarded-Proto`, `Forwarded` ou `CF-Visitor`.

## CSP

A CSP está ativa em modo compatível. Scripts inline existentes devem usar nonce via `csp_script_nonce_attr()`; novos scripts devem ir para arquivos em `public/js` sempre que possível.
