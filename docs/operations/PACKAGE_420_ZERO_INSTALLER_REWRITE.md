# Pacote 420 — Reescrita total do instalador SupportPONTO

O instalador antigo foi substituído por `tools/installer/SupportPontoZeroInstaller.php`, um runtime autocontido que não chama Composer, CodeIgniter nem classes `App\*` antes da hora.

## Fluxo
1. Valida PHP/extensões.
2. Prepara `writable/*`.
3. Instala dependências Composer se `vendor/autoload.php` não existir.
4. Gera `.env` novo.
5. Recria o schema público do PostgreSQL com `DROP SCHEMA IF EXISTS public CASCADE`.
6. Executa `php spark migrate --all`.
7. Executa `php spark db:seed DatabaseSeeder` para criar permissões e administrador.
8. Valida tabelas e admin.
9. Cria `writable/installer/installed.lock`.

## Uso
```bash
php tools/installer/install_cli.php --diagnose
php tools/installer/install_cli.php --force --app-url=https://ponto.supportsondagens.com.br --db-host=127.0.0.1 --db-name=supportponto --db-user=postgres --db-pass='SENHA' --admin-email=contato@supportsondagens.com.br --admin-cpf=00000000000 --admin-password='SenhaForte!12345'
```
