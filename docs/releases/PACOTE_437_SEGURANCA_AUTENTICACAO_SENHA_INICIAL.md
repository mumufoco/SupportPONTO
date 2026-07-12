# Pacote 437 — Segurança de autenticação e senha inicial

## Objetivo

Corrigir exposição da senha administrativa inicial e reforçar o ciclo de vida da senha temporária.

## Correções aplicadas

- Removida a gravação de `ADMIN_INITIAL_PASSWORD` no `.env` gerado pelo instalador.
- Removida a persistência da senha temporária em `writable/secrets/admin_bootstrap_credentials.json`.
- Removida a senha temporária do relatório persistido em `writable/installer/last-install-report.json`.
- Mantida a exibição da senha apenas no resultado ativo/final da instalação.
- Criado sanitizador recursivo para relatórios do instalador.
- Seeder do administrador inicial não persiste senha em texto puro e remove arquivos legados de credenciais.
- Troca de senha no primeiro acesso continua obrigatória por `must_change_password=true`.
- Troca de senha substitui o hash Argon2id, limpa tokens de reset/remember-me quando solicitado e remove arquivos legados de bootstrap.
- Política mínima elevada para 12 caracteres com maiúscula, minúscula, número e caractere especial.
- Migração idempotente criada para garantir colunas de ciclo de senha.
- Teste estático criado para cobrir vazamento de senha inicial, política de senha e ciclo de primeiro acesso.

## Arquivos alterados/criados

- `tools/installer/SupportPontoZeroInstaller.php`
- `app/Database/Seeds/AdminUserSeeder.php`
- `app/Services/Auth/PasswordLifecycleService.php`
- `app/Validation/CustomRules.php`
- `app/Config/Validation.php`
- `app/Services/Auth/RegisterPolicyService.php`
- `app/Validation/README.md`
- `app/Views/auth/first_access_password.php`
- `app/Views/auth/register.php`
- `app/Views/auth/reset_password.php`
- `app/Services/Auth/AuthService.php`
- `app/Services/Admin/SecuritySettingsService.php`
- `README.md`
- `app/Database/Migrations/2026-05-17-0437_HardenInitialAdminPasswordLifecycle.php`
- `tests/Feature/Package437InitialPasswordSecurityStaticTest.php`
- `docs/releases/PACOTE_437_SEGURANCA_AUTENTICACAO_SENHA_INICIAL.md`

## Validação esperada

- `.env` não contém `ADMIN_INITIAL_PASSWORD`.
- `writable/secrets/admin_bootstrap_credentials.json` não é criado com senha em texto puro.
- `last-install-report.json` não contém `temporary_password` nem valores sensíveis.
- Login inicial direciona para troca obrigatória de senha.
- Após troca, `must_change_password=false`, `password_changed_at` preenchido e senha temporária anterior inválida.
- Hashes continuam usando `PASSWORD_ARGON2ID`.
