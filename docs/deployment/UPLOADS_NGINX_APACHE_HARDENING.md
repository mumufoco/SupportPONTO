# Hardening de uploads — Nginx, Apache e aaPanel

## Objetivo

Garantir que arquivos enviados nunca sejam executados pelo servidor web e que arquivos operacionais não sejam servidos diretamente.

## Apache / aaPanel

O pacote já entrega `.htaccess` em:

- `public/uploads/.htaccess`
- `public/assets/uploads/.htaccess`
- `writable/uploads/.htaccess`

Confirme que o vhost permite `AllowOverride All` ou pelo menos `AllowOverride FileInfo Options AuthConfig` para o diretório do projeto.

Exemplo recomendado:

```apache
<Directory "/www/wwwroot/ponto.supportsondagens.com.br/public/uploads">
    Options -Indexes -ExecCGI
    AllowOverride All
    Require all granted
</Directory>

<Directory "/www/wwwroot/ponto.supportsondagens.com.br/writable/uploads">
    Options -Indexes -ExecCGI
    AllowOverride All
    Require all denied
</Directory>
```

## Nginx / aaPanel

Inclua regras equivalentes no vhost quando Nginx estiver servindo diretamente os arquivos:

```nginx
location ^~ /uploads/ {
    autoindex off;
    add_header X-Content-Type-Options "nosniff" always;
    location ~* \.(php|php3|php4|php5|php7|php8|phtml|phar|pl|py|cgi|sh|asp|aspx|jsp|exe|dll|com|bat|cmd|ps1|html|htm|svg|xml|shtml|swf)$ {
        deny all;
    }
}

location ^~ /writable/uploads/ {
    deny all;
    return 403;
}
```

## Conferências após deploy

```bash
# deve negar ou baixar como texto, nunca executar
curl -I https://ponto.supportsondagens.com.br/uploads/teste.php

# não deve listar diretório
curl -I https://ponto.supportsondagens.com.br/uploads/

# writable nunca deve ser público
curl -I https://ponto.supportsondagens.com.br/writable/uploads/
```

## Regra operacional

Não crie symlink de `writable/uploads` para dentro de `public/`. Para arquivos privados, use sempre controller de download autenticado.
