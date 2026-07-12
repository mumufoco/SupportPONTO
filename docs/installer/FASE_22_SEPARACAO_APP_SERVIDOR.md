# Fase 22 — Separação real entre instalação da aplicação e provisionamento do servidor

## Objetivo

Separar definitivamente o fluxo do instalador Web em duas responsabilidades:

1. **Instalação da aplicação**: `.env`, `vendor`, banco, migrations, seeders, administrador inicial e `installed.lock`.
2. **Provisionamento do servidor**: pacotes do sistema, extensões PHP, PostgreSQL client, permissões, DeepFace, Node, workers, WebSocket e serviços systemd.

## Decisão técnica

O instalador Web não executa `apt-get`, `systemctl`, `systemd`, instalação de pacotes do SO ou ações root. Essas operações continuam em scripts CLI/SSH dedicados.

## Artefato criado

O instalador agora gera automaticamente:

```text
writable/installer/server-provisioning-plan.json
```

Esse arquivo contém:

- ambiente detectado;
- PHP CLI usado;
- comandos de preparo do servidor;
- escopo da instalação da aplicação;
- escopo do provisionamento do servidor;
- orientações para aaPanel, Docker e hospedagem compartilhada.

## Comando CLI

```bash
php tools/installer/install_cli.php --server-provision-plan --json
```

## Wizard

O wizard passou a ter 9 etapas:

1. Boas-vindas
2. Preparação do servidor
3. Requisitos da aplicação
4. Vendor/Composer
5. Banco PostgreSQL
6. Aplicação e administrador
7. Dependências e serviços pós-instalação
8. Resumo
9. Conclusão

## Resultado esperado

O usuário deixa de confundir bloqueios de servidor com bloqueios da aplicação. O instalador informa com clareza quais comandos precisam de SSH/root e quais ações são executadas pelo wizard.
