# Pacote 439 — Mapa de scripts inline e CSP

## Política adotada

A CSP foi ativada de forma compatível:

- `script-src` permite apenas origem própria, CDN explicitamente mapeada e nonce dinâmico;
- `script-src-attr` permanece em `unsafe-inline` temporariamente para compatibilidade com atributos/eventos legados ainda existentes;
- scripts `<script>` nas views devem usar `csp_script_nonce_attr()`;
- novos scripts devem ser movidos para `public/js` sempre que possível;
- `style-src` mantém `unsafe-inline` controlado por `CSP_ALLOW_INLINE_STYLE`, pois o sistema ainda possui estilos inline necessários para telas administrativas, PWA e impressão.

## CDNs explicitamente permitidas

- `https://cdn.jsdelivr.net`
- `https://cdnjs.cloudflare.com`
- `https://unpkg.com`
- `https://browser.sentry-cdn.com`
- `https://fonts.googleapis.com`
- `https://fonts.gstatic.com`

## Inline removido neste pacote

- `app/Views/layouts/kiosk.php`: inicialização de ícones Lucide movida para `public/js/kiosk-icons.js`.

## Regra para próximos pacotes

1. Não criar `<script>` sem `csp_script_nonce_attr()`.
2. Não adicionar `unsafe-inline` em `script-src`.
3. Preferir arquivos versionáveis em `public/js`.
4. Quando um CDN novo for necessário, registrar o motivo nesta documentação antes de adicioná-lo à CSP.
5. Revisar atributos `onclick`, `onchange` e similares para futura remoção de `script-src-attr 'unsafe-inline'`.
