# Pacote 443 — Configuração `.env` e ambiente

## Objetivo
Garantir que o ambiente do SupportPONTO tenha `.env` completo, seguro e coerente antes do boot da aplicação.

## Problemas corrigidos
- `.env.example` não cobria todas as variáveis críticas usadas pela aplicação.
- Validação do instalador aceitava `.env` incompleto ou com placeholders críticos.
- Ambiente podia iniciar sem padronização explícita de produção e timezone.
- `ADMIN_INITIAL_PASSWORD` não deve existir no `.env`, mas a senha temporária precisa ser passada de forma transitória ao seeder.
- Falhas de `baseURL`, driver PostgreSQL ou segredos placeholders podiam gerar erros silenciosos.

## Alterações realizadas
- `.env.example` reescrito como referência completa de produção segura.
- `.env.production.example` sincronizado com defaults de produção e HSTS/CSP compatíveis.
- Instalador atualizado para `1.1.443-env-hardening`.
- Criada validação categorizada do `.env` no instalador:
  - variáveis obrigatórias;
  - segredos proibidos persistidos;
  - placeholders em segredos obrigatórios;
  - `CI_ENVIRONMENT=production`;
  - `app.appTimezone=America/Sao_Paulo`;
  - `app.baseURL` válida;
  - `database.default.DBDriver=Postgre`.
- `.env` gerado pelo instalador agora inclui:
  - `APP_ENV` e `CI_ENVIRONMENT` em produção;
  - timezone e locale canônicos;
  - aliases `DB_*` e `PG*`;
  - sessão, cookie, cache, CSP e HSTS mínimos;
  - sem senha temporária administrativa persistida.
- Corrigida passagem transitória de `ADMIN_INITIAL_PASSWORD` apenas para o processo do seeder, sem gravar em `.env` ou relatório persistido.
- `app/Config/App.php` agora aceita override controlado de timezone/locales por ambiente.

## Arquivos principais alterados
- `.env.example`
- `.env.production.example`
- `tools/installer/SupportPontoZeroInstaller.php`
- `app/Config/App.php`
- `public/version.json`

## Validação
- `php -l` nos arquivos PHP alterados.
- Diagnóstico CLI do instalador sem fatal error.
- Validação estática do pacote criada em `tests/Feature/Package443EnvConfigurationStaticTest.php`.
- `.zip` validado com `unzip -t`.

## Resultado esperado
O `.env` gerado permite boot previsível da aplicação, sem senha administrativa inicial em texto puro persistida e com ambiente padronizado para produção em `America/Sao_Paulo`.
