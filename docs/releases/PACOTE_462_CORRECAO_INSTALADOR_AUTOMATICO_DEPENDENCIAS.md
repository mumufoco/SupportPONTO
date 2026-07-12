# Pacote 462 — Correção profunda do instalador automático de dependências

## Objetivo

Corrigir a integração real do catálogo de dependências com o instalador automático, removendo riscos operacionais antes do uso em homologação/produção.

## Problemas corrigidos

- `diagnose()` referenciava `dependencies_catalog` sem inicializar `$dependencies`.
- O diagnóstico principal não consolidava bloqueios/avisos vindos do catálogo de dependências.
- `install-dependencies.sh --all` podia misturar tarefas de root com Composer/npm/pip/DeepFace.
- Em aaPanel, havia risco de instalar extensões PHP no PHP errado via `apt`.
- O script podia instalar Nginx e Apache juntos.
- DeepFace não validava faixa suportada de Python antes do `pip install`.
- `--json` podia vir precedido por logs em stdout.
- `composer.json` estava desalinhado do `composer.lock` para pacotes críticos.

## Alterações principais

- `SupportPontoZeroInstaller::diagnose()` agora chama `diagnoseDependencyCatalog()` e inclui a categoria no cálculo de bloqueios.
- `install-dependencies.sh` foi endurecido com:
  - `--all-root`;
  - `--all-userland`;
  - `--allow-root-userland`;
  - `--aapanel`;
  - `--webserver=nginx|apache|none`;
  - JSON puro em stdout para `--json`;
  - bloqueio de Composer/npm/pip como root por padrão;
  - validação de Python 3.10/3.11 para DeepFace.
- O instalador web passou a exibir seleção explícita de DeepFace e Node como dependências opcionais.
- `deepface-api/requirements.txt` passou a usar `opencv-python-headless`.
- O catálogo foi atualizado com política de root, aaPanel, JSON puro e serviços operacionais.
- O gate `dependency-catalog-audit.php` agora valida alinhamento com `composer.lock` e `requirements.txt`.

## Execução recomendada

### Diagnóstico

```bash
bash install/runtime/install-dependencies.sh --diagnose
bash install/runtime/install-dependencies.sh --diagnose --json
```

### Ubuntu/servidor puro

```bash
sudo bash install/runtime/install-dependencies.sh --all-root --webserver=nginx
sudo -u www bash install/runtime/install-dependencies.sh --all-userland --python-bin=/usr/bin/python3.11
```

### aaPanel

```bash
bash install/runtime/install-dependencies.sh --diagnose --aapanel --php-bin=/www/server/php/83/bin/php
```

No aaPanel, instale/ative extensões PHP pelo painel para o PHP real do domínio. Não assuma que `apt install php8.3-pgsql` altera o PHP de `/www/server/php/83/bin/php`.

## Validação

- `bash -n install/runtime/install-dependencies.sh`
- `php -l tools/installer/SupportPontoZeroInstaller.php`
- `php tools/quality/dependency-catalog-audit.php`
- `bash scripts/testing/dependency-catalog-gate.sh`
- `bash install/runtime/install-dependencies.sh --diagnose --json`

## Resultado esperado

O instalador automático passa a ter comportamento previsível, seguro e auditável, separando corretamente tarefas de root/sistema de tarefas userland da aplicação.
