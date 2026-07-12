# Pacote 463 — Consolidação final do instalador automático

## Objetivo

Consolidar o instalador automático de dependências antes de homologação real, corrigindo divergências entre catálogo, script, provisionamento, instalador web/CLI e operação em produção.

## Problemas corrigidos

- O catálogo ainda permitia instalar Nginx e Apache juntos.
- A opção `--all` era ambígua e podia misturar tarefas de root com tarefas userland.
- O instalador principal ainda podia tentar resolver Composer/vendor durante a instalação.
- O instalador web mostrava opções de DeepFace e Node/Cypress que podem travar por timeout, memória ou permissão.
- Não havia perfis claros por cenário de instalação.
- Faltavam checks mínimos de CPU, RAM e disco para DeepFace local.
- Faltava script operacional para serviços systemd do worker de filas e DeepFace local.
- O JSON de diagnóstico não trazia versões/caminhos detalhados.

## Implementado

- Catálogo `install/runtime/dependencies.catalog.json` atualizado para versão `v1.1.464`.
- Perfis de instalação adicionados:
  - `production_minimal`;
  - `production_biometric_local`;
  - `production_deepface_external`;
  - `production_docker`;
  - `development_testing`;
  - `aapanel`.
- Webserver passou a exigir seleção explícita:
  - `--webserver=nginx`;
  - `--webserver=apache`;
  - `--webserver=none`.
- Removida instalação conjunta automática de Nginx e Apache.
- `--all` descontinuado no instalador de dependências e no provisionamento.
- `install-dependencies.sh` agora lê extensões obrigatórias do catálogo quando Python está disponível.
- `install-dependencies.sh` agora valida:
  - perfil selecionado;
  - Python 3.10/3.11 para DeepFace local;
  - RAM, CPU e disco para biometria local;
  - disponibilidade de pacotes `php8.3-*` e `python3.11` antes de instalar;
  - Composer/npm/pip/DeepFace como root bloqueados por padrão.
- Diagnóstico JSON ampliado com:
  - caminhos detectados;
  - versões detectadas;
  - recursos de CPU/RAM/disco;
  - bloqueios e avisos.
- Criado script de serviços:
  - `install/runtime/install-services.sh`.
- Serviços suportados:
  - `supportponto-worker.service`;
  - `supportponto-deepface.service`.
- Instalador principal deixou de executar Composer automaticamente em produção.
- `COMPOSER_ALLOW_SUPERUSER` removido do ambiente do instalador principal.
- Instalador web deixou de exibir checkboxes para instalar DeepFace e Node/Cypress.
- Documentação e gate de catálogo reforçados.

## Comandos recomendados

### Ubuntu/aaPanel — preparo root

```bash
sudo bash install/runtime/provision-server.sh --install-system-packages --fix-permissions --project-user=www --project-group=www --php-bin=/www/server/php/83/bin/php
```

### Dependências da aplicação — usuário do projeto

```bash
sudo -u www bash install/runtime/install-dependencies.sh --install-vendor --php-bin=/www/server/php/83/bin/php
```

### DeepFace local — apenas se houver Python 3.10/3.11 e recursos suficientes

```bash
sudo -u www bash install/runtime/install-dependencies.sh --install-deepface --python-bin=/usr/bin/python3.11
sudo bash install/runtime/install-services.sh --deepface-systemd --project-user=www --python-bin=/usr/bin/python3.11
```

### Worker de filas

```bash
sudo bash install/runtime/install-services.sh --worker --project-user=www --php-bin=/www/server/php/83/bin/php
```

## Validação

Execute:

```bash
bash scripts/testing/dependency-catalog-gate.sh
bash install/runtime/install-dependencies.sh --diagnose --json
bash install/runtime/install-services.sh --diagnose
php tools/installer/install_cli.php --diagnose
```

## Resultado esperado

Instalador automático mais previsível, com separação clara entre root/userland/web, perfis por cenário e menor risco de instalação incompleta ou permissões quebradas.
