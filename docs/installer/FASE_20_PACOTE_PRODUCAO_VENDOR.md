# Fase 20 — Pacote de produção com vendor incluído

Versão: **SupportPONTO v1.1.489**
Pacote: **485**

## Objetivo

Criar o pipeline oficial para gerar um artefato de produção realmente instalável pelo navegador, contendo `vendor/autoload.php`, manifesto de release e checksums SHA256.

## Problema resolvido

O instalador web depende do `vendor/` para executar a aplicação, migrations e seeders. Em servidores sem Composer, sem internet ou com PHP CLI divergente, a instalação pode parar antes da conclusão.

A partir desta fase, o projeto passa a ter um build oficial:

```bash
php tools/release/build-production-with-vendor.php
```

Quando `vendor/autoload.php` existe ou quando o Composer pode ser executado, o script gera:

```text
build/release/SupportPONTO-v1.1.489-production-with-vendor.zip
build/release/release-manifest.json
build/release/release-checksums.sha256
```

## Regras do artefato production-with-vendor

O artefato `production-with-vendor` só é gerado quando `vendor/autoload.php` está presente. Se o ambiente de build não tiver Composer nem vendor, o script falha por padrão para evitar um falso pacote de produção.

Existe uma opção de contingência:

```bash
php tools/release/build-production-with-vendor.php --allow-source-fallback
```

Essa opção gera um pacote claramente marcado como `source-fallback-without-vendor`. Esse pacote não deve ser anunciado como production-with-vendor.

## Flags Composer aplicadas

O build usa o padrão de produção:

```bash
composer install \
  --no-dev \
  --prefer-dist \
  --no-interaction \
  --no-progress \
  --optimize-autoloader \
  --classmap-authoritative
```

## Validações

Antes de liberar o artefato production-with-vendor, validar:

```bash
php -r "require 'vendor/autoload.php'; echo 'OK';"
php tools/release/audit-version-consistency.php
unzip -t build/release/SupportPONTO-v1.1.489-production-with-vendor.zip
```

## Observação operacional

Em ambientes corporativos, o pacote recomendado é sempre o `production-with-vendor.zip`. O pacote fonte sem vendor deve ficar reservado para desenvolvimento, CI ou builds internos.
