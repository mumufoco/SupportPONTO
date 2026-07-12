# Pacote 447 — Uploads, arquivos e diretórios públicos

## Objetivo

Evitar upload inseguro, execução acidental de arquivos enviados, vazamento de anexos e XSS armazenado por arquivos públicos.

## Problemas tratados

- Uploads estavam distribuídos em helpers e services sem um serviço central de política.
- Alguns diretórios privados eram criados com permissão ampla.
- `public/uploads` tinha bloqueio básico, mas incompleto para extensões modernas e XSS por SVG/HTML.
- Arquivos de chat permitiam pacotes compactados, aumentando superfície de malware.
- Nomes armazenados ainda podiam carregar parte do nome original.
- Certificados digitais precisavam de validação e permissão mais rígida após upload.

## Arquivos principais alterados/criados

- `app/Services/Upload/SafeUploadService.php`
- `app/Controllers/Upload/SecureDownloadController.php`
- `app/Config/Routes/82_uploads.php`
- `app/Helpers/file_upload_helper.php`
- `app/Services/ChatWebWorkflowService.php`
- `app/Services/Warning/Workflow/WarningEvidenceService.php`
- `app/Services/Warning/WarningPdfStorageService.php`
- `app/Services/Settings/System/SystemBrandingAssetService.php`
- `app/Controllers/Admin/CertificateController.php`
- `public/uploads/.htaccess`
- `public/uploads/.user.ini`
- `public/assets/uploads/.htaccess`
- `writable/uploads/.htaccess`
- `writable/uploads/.user.ini`
- `docs/security/UPLOADS_ARQUIVOS_PUBLICOS_CHECKLIST.md`
- `tests/Feature/Package447UploadSecurityStaticTest.php`

## Decisões de segurança

1. Upload operacional fica em `writable/uploads`.
2. Download operacional deve passar por controller autenticado.
3. `public/uploads` fica reservado a assets públicos de marca e imagens normalizadas.
4. Arquivos HTML, SVG, XML e scripts são sempre recusados.
5. Arquivos compactados foram removidos do chat por padrão.
6. Diretórios privados recebem bloqueio de execução e listagem.
7. Certificados digitais são gravados com permissão `0600`.
8. Evidências de advertência passam a usar serviço centralizado de upload privado.

## Validações realizadas

- `php -l` nos PHP alterados/criados.
- Varredura estática por extensões bloqueadas.
- Varredura de `.htaccess` em `public/uploads` e `writable/uploads`.
- Integridade do pacote com `unzip -t`.

## Observação

Não foi executado `php spark routes`/PHPUnit no sandbox porque o pacote base não contém `vendor/codeigniter4/framework/system/Boot.php`.
