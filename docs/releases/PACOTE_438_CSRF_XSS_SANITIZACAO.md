# Pacote 438 — Proteção CSRF, XSS e sanitização

## Objetivo

Reduzir a exposição a OWASP A03/A07 com reforço de CSRF, escape de saída e sanitização defensiva de entrada textual.

## Alterações principais

- Mantida proteção CSRF global em `app/Config/Filters.php` para superfícies web.
- Criado `app/Services/Security/InputSanitizerService.php`.
- Ampliado `app/Helpers/security_helper.php` com funções `security_sanitize*`.
- Ampliado `app/Helpers/output_escape_helper.php` com `sp_text()` e `sp_flash()`.
- Sanitizados payloads POST em massa em controllers administrativos, funcionários, escalas, justificativas e advertências.
- Corrigidos pontos de flash/textareas com saída não escapada.
- Sanitizados nomes de arquivos em uploads.
- Removido `innerHTML` desnecessário do JS principal.
- Criado checklist OWASP em `docs/security/OWASP_CSRF_XSS_CHECKLIST.md`.
- Criado teste estático `tests/Feature/Package438CsrfXssSanitizationStaticTest.php`.

## Validação

- Formulários POST das views foram auditados estaticamente para presença de CSRF.
- Scripts inline foram auditados para uso de nonce CSP.
- `php -l` foi executado nos arquivos alterados/criados.

## Observação operacional

A sanitização de entrada não substitui escape de saída. O padrão obrigatório permanece: persistir dados textuais saneados quando possível e sempre escapar no contexto correto ao renderizar HTML, atributos, JavaScript ou URL.
