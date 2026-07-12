# Checklist — Uploads, arquivos e diretórios públicos

## Regras obrigatórias

- [x] Upload operacional fica em `writable/uploads` sempre que possível.
- [x] `public/uploads` fica restrito a assets públicos não sensíveis, como branding.
- [x] Extensões executáveis são bloqueadas antes da movimentação.
- [x] HTML, SVG, XML e scripts são bloqueados para evitar XSS armazenado.
- [x] MIME real é verificado com `finfo`/magic bytes, não apenas pelo navegador.
- [x] Imagens passam por validação estrutural com `getimagesize` ou reencode quando aplicável.
- [x] Nomes armazenados são aleatórios e não usam o nome original como chave.
- [x] Diretórios privados recebem `.htaccess`, `index.html` e permissão restritiva.
- [x] Downloads privados passam por `realpath` e raízes permitidas.
- [x] Eventos de bloqueio e armazenamento são registrados em log técnico.

## Diretórios

| Diretório | Uso | Exposição direta |
|---|---|---|
| `writable/uploads/chat` | anexos de chat | não |
| `writable/uploads/justifications` | anexos de justificativa | não |
| `writable/uploads/warnings` | evidências e PDFs de advertência | não |
| `writable/uploads/faces` | biometria facial | não |
| `writable/certificates` | certificados digitais | não |
| `public/uploads/system` | imagens públicas de marca | sim, somente imagens normalizadas |
| `public/assets/uploads` | legado/fallback público | sim, com bloqueio de execução |

## Bloqueios mínimos

- PHP: `php`, `phtml`, `phar` e variações.
- Scripts: `js`, `mjs`, `vbs`, `cgi`, `pl`, `py`, `rb`, `sh`, `bat`, `cmd`, `ps1`.
- Executáveis: `exe`, `dll`, `com`, `scr`, `msi`, `jar`.
- XSS/HTML: `html`, `htm`, `xhtml`, `svg`, `xml`, `shtml`, `swf`.

## Validação manual recomendada

1. Tentar enviar `shell.php` renomeado para `.jpg`.
2. Tentar enviar SVG com `<script>`.
3. Tentar acessar diretamente `writable/uploads/...` via HTTP.
4. Tentar `../` em download de chat.
5. Confirmar que o arquivo válido continua baixando pela rota autenticada.
