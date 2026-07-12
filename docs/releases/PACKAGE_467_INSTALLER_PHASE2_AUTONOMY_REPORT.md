# Pacote 467 — Fase 2 do instalador: autonomia controlada

## Resumo

Este pacote evolui o instalador do SupportPONTO para resolver a ausência de `vendor/autoload.php` em ambientes novos, adicionando instalação automática controlada de Composer/vendor, verificação criptográfica do instalador do Composer e relatórios técnicos persistentes.

## Problemas resolvidos

1. Instalação travava quando `vendor/autoload.php` estava ausente.
2. O instalador não tinha opção segura para preparar vendor pela própria execução principal.
3. Download anterior de Composer não validava assinatura criptográfica.
4. Ausência de vendor não gerava plano estruturado de provisionamento.
5. Logs de Composer não eram isolados em relatório próprio.

## Correções aplicadas

- Adicionado suporte a `install_vendor` no Web e CLI.
- Adicionada flag CLI `--install-vendor`.
- Adicionado bloqueio duplo para Web com `INSTALLER_ALLOW_WEB_VENDOR_INSTALL=true`.
- Composer passa a ser instalado pelo installer oficial validado por SHA384.
- Criado relatório `composer-install-last.json`.
- Criado plano `composer-provision-required.json`.
- Comando Composer padronizado para produção.
- Versão sincronizada para `1.1.467`.

## Riscos reduzidos

- Falha de instalação em servidor sem vendor.
- Execução indevida de Composer por requisição Web sem autorização.
- Supply chain básico por download não verificado.
- Falta de diagnóstico em falhas de Composer.

## Validações executadas

```bash
php -l tools/installer/SupportPontoZeroInstaller.php
php tests/Feature/InstallerPhase2AutonomyStaticTest.php
php tools/quality/installer-wizard-audit.php
php tools/quality/dependency-catalog-audit.php
php tools/release/audit-version-consistency.php
```

## Resultado

Fase 2 concluída com pacote completo v1.1.467.
