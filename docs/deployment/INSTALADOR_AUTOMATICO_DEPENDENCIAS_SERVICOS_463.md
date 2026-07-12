# Instalador automático de dependências e serviços — v1.1.464

## Regra principal

O instalador web não executa `sudo`, Composer, npm, pip, DeepFace ou instalação de pacotes do sistema. Essas tarefas devem ser executadas por CLI/SSH, separando root de usuário da aplicação.

## Perfis

- `production_minimal`: aplicação PHP + PostgreSQL + Composer/vendor.
- `production_biometric_local`: produção mínima + DeepFace local em Python 3.10/3.11.
- `production_deepface_external`: produção mínima + API DeepFace externa.
- `production_docker`: stack Docker.
- `development_testing`: Node/Cypress/tooling.
- `aapanel`: modo aaPanel, onde extensões PHP devem ser habilitadas no painel do PHP do domínio.

## Fluxo seguro para aaPanel

```bash
sudo bash install/runtime/provision-server.sh --install-system-packages --fix-permissions --project-user=www --project-group=www --php-bin=/www/server/php/83/bin/php
sudo -u www bash install/runtime/install-dependencies.sh --install-vendor --profile=aapanel --php-bin=/www/server/php/83/bin/php
php tools/installer/install_cli.php --diagnose
```

## Fluxo seguro para Ubuntu puro

```bash
sudo bash install/runtime/install-dependencies.sh --all-root --webserver=nginx --profile=production_minimal
sudo -u www bash install/runtime/install-dependencies.sh --install-vendor --profile=production_minimal
sudo bash install/runtime/install-services.sh --worker --project-user=www --php-bin=/usr/bin/php
```

## DeepFace local

Antes de instalar, valide:

```bash
bash install/runtime/install-dependencies.sh --diagnose --profile=production_biometric_local --python-bin=/usr/bin/python3.11
```

Requisitos recomendados:

- Python 3.10 ou 3.11;
- 4 GB RAM ou mais;
- 10 GB livres ou mais;
- 2 vCPU ou mais.

## DeepFace externo

Configure no `.env`:

```ini
DEEPFACE_API_URL = "http://127.0.0.1:5000"
DEEPFACE_API_KEY = "trocar"
```

## Serviços systemd

Worker:

```bash
sudo bash install/runtime/install-services.sh --worker --project-user=www --php-bin=/www/server/php/83/bin/php
```

DeepFace local:

```bash
sudo bash install/runtime/install-services.sh --deepface-systemd --project-user=www --python-bin=/usr/bin/python3.11
```
