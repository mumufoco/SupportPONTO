# Wizard visual do instalador — Pacote 465

O instalador web foi organizado em etapas para reduzir erro operacional e facilitar o trabalho do técnico.

## Fluxo recomendado

1. Abrir `/install` com token válido, quando o instalador web estiver liberado por ambiente.
2. Ler a tela de boas-vindas.
3. Corrigir bloqueios da etapa de requisitos.
4. Preencher banco PostgreSQL.
5. Preencher URL da aplicação e administrador inicial.
6. Preparar dependências por CLI/SSH quando necessário.
7. Executar `dry-run`.
8. Executar instalação apenas se o dry-run passar.
9. Copiar a senha temporária exibida uma única vez.
10. Desativar o instalador web e confirmar `installed.lock`.

## Dependências pesadas

Por segurança, o wizard web não instala:

- pacotes apt/yum;
- Composer/vendor;
- DeepFace/TensorFlow;
- Node/Cypress;
- serviços systemd.

Use CLI/SSH:

```bash
sudo bash install/runtime/provision-server.sh --install-system-packages --fix-permissions --project-user=www --project-group=www
sudo -u www bash install/runtime/install-dependencies.sh --profile=production_minimal --apply-userland
sudo bash install/runtime/install-services.sh --worker --project-user=www --project-group=www
```

## Observação para aaPanel

Confirme sempre o PHP do domínio no aaPanel. Extensões instaladas por `apt` podem não afetar `/www/server/php/83/bin/php`.
