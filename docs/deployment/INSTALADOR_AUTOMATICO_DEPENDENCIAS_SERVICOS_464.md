# Instalador automático de dependências e serviços — v1.1.464

## Fluxo recomendado

### Ubuntu/aaPanel — root

```bash
sudo bash install/runtime/provision-server.sh --install-system-packages --fix-permissions --project-user=www --project-group=www
sudo bash install/runtime/install-dependencies.sh --profile=production_minimal --apply-root --webserver=nginx
```

### Aplicação — usuário do projeto

```bash
sudo -u www bash install/runtime/install-dependencies.sh --profile=production_minimal --apply-userland
```

### Biometria local

```bash
sudo -u www bash install/runtime/install-dependencies.sh --profile=production_biometric_local --apply-userland --python-bin=/usr/bin/python3.11
sudo bash install/runtime/install-services.sh --worker --deepface-systemd --project-user=www --project-group=www --php-bin=/www/server/php/83/bin/php
```

## Planejamento antes de executar

```bash
bash install/runtime/install-dependencies.sh --profile=production_minimal --plan
bash install/runtime/install-dependencies.sh --diagnose --json
php tools/installer/install_cli.php --install-dependencies --diagnose --json
```

## Regras

- `--all` foi removido.
- Composer/npm/pip não rodam como root por padrão.
- DeepFace e Node não são instalados pelo instalador web.
- aaPanel deve validar extensões no PHP real do domínio.
