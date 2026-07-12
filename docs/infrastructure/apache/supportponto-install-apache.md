# SupportPONTO - Apache installer URL

Apache usa os arquivos `.htaccess` já incluídos em:

- `public/install/.htaccess`, quando o vhost aponta para `public/`;
- `install/.htaccess`, quando o vhost aponta para a raiz do projeto.

A URL esperada é:

```text
https://ponto.supportsondagens.com.br/install
```

Se o servidor usar Apache com `AllowOverride None`, ative `AllowOverride All` para o vhost ou copie as regras equivalentes do `.htaccess` para o VirtualHost.
