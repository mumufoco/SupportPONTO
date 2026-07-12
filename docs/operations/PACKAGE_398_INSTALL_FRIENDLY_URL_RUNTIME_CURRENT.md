# Package 398 — `/install` runtime entrypoint correction

## Objetivo

Garantir que a URL amigável abaixo abra o instalador automático em ambiente novo:

```text
https://ponto.supportsondagens.com.br/install
```

## Cenários cobertos

### 1. Vhost apontando para `public/`

Entrada usada:

```text
public/install/index.php
```

Nginx/aaPanel: usar o snippet:

```text
docs/infrastructure/nginx/supportponto-install-public-root.conf
```

### 2. Vhost apontando para a raiz do projeto

Entrada usada:

```text
install/index.php
```

Nginx/aaPanel: usar o snippet:

```text
docs/infrastructure/nginx/supportponto-install-project-root.conf
```

## Regras de segurança preservadas

O instalador automático em `/install` só abre quando:

- não existe `.env`;
- não existe `writable/installer/installed.lock`;
- o host é autorizado, por padrão `ponto.supportsondagens.com.br`.

Depois da instalação, `/install` deve ser bloqueado pelo lock.

O arquivo `public/install.php` continua sendo stub inerte e não é usado para abertura automática por URL amigável.

## Validação local obrigatória

Executar:

```bash
bash scripts/testing/installer-friendly-url-runtime-check.sh
```

Esse script abre `/install` com servidor PHP embutido em dois cenários:

- docroot `public/`;
- docroot raiz do projeto.

A validação só passa se o wizard bootstrapless aparecer nos dois casos.
