# Checklist OWASP — CSRF, XSS e sanitização

Pacote: 438  
Versão: 1.1.438

## CSRF

- CSRF permanece global para superfícies web autenticadas.
- APIs tokenizadas ficam fora do CSRF de sessão e devem usar Bearer/OAuth2.
- Terminais públicos de ponto/QR/kiosk mantêm exclusão controlada e são protegidos por throttle/rate limit.
- Formulários POST das views foram verificados quanto à presença de `csrf_field()`/token.
- Requisições AJAX devem usar `window.spFetch`, que injeta header/token CSRF para métodos mutáveis.

## XSS refletido e armazenado

- Saída HTML continua obrigatoriamente escapada por `esc()`, `sp_text()`, `sp_attr()`, `sp_js()` ou `sp_safe_url()`.
- Mensagens flash são renderizadas com escape e contam com sanitização defensiva via `sp_flash()` quando usadas fora do componente central.
- Campos `old()` em textareas revisados foram escapados.
- Scripts inline devem usar `csp_script_nonce_attr()`.
- `innerHTML` desnecessário foi removido do JS principal.

## Sanitização de entrada

- Criado `InputSanitizerService` para remover tags HTML, bytes de controle e esquemas `javascript:`, `data:` e `vbscript:` de campos textuais.
- Payloads POST em massa usados por cadastros/configurações/escala/advertências/justificativas passam por `security_sanitize()` antes de chegar aos serviços.
- Campos sensíveis como senha, token, base64, imagem, biometria e certificado são preservados e continuam sendo validados por políticas próprias.

## Uploads

- Uploads continuam validando extensão, MIME real, tamanho e imagem reprocessável.
- Nomes de arquivo passaram a usar `security_sanitize_filename()`.
- Extensões executáveis continuam bloqueadas.

## Critério de regressão

Payloads como `<script>alert(1)</script>`, `<img src=x onerror=alert(1)>` e `javascript:alert(1)` não devem ser persistidos/exibidos como HTML executável em campos textuais comuns.
