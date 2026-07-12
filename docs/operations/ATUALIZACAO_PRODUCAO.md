# Atualização de produção

## Fluxo seguro

1. Ler a documentação do pacote em `docs/releases/`.
2. Fazer backup do banco e arquivos sensíveis.
3. Testar o pacote em ambiente limpo quando possível:

```bash
composer test:essential
composer test:release-critical
```

4. Enviar o novo pacote para uma pasta de staging.
5. Reaproveitar `.env` validado.
6. Instalar dependências:

```bash
composer install --no-dev --optimize-autoloader
```

7. Executar diagnóstico:

```bash
php tools/installer/install_cli.php --diagnose
```

8. Executar migrations:

```bash
php spark migrate --all
php spark migrate:status
```

9. Limpar caches, quando aplicável:

```bash
php spark cache:clear
php spark routes
```

10. Validar endpoints principais:

```bash
curl -I https://ponto.supportsondagens.com.br/healthz
curl -I https://ponto.supportsondagens.com.br/auth/login
```

## Cuidados

- Não remover `writable/installer/installed.lock` em produção.
- Não publicar diretórios internos no webroot.
- Não executar `--force-reset` em banco real.
- Não ativar `APP_DEBUG` em produção.
- Não deixar `ALLOW_WEB_INSTALLER=true` após atualização.

## Pós-atualização

- Verificar tela de saúde admin.
- Verificar fila `async_jobs`.
- Verificar DeepFace/circuit breaker.
- Verificar logs de erro das últimas horas.
- Registrar versão implantada e responsável.
