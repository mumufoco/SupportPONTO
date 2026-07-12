# Pacote 464 — Correção de integridade do catálogo, perfis e automação do instalador

## Objetivo

Corrigir a inconsistência operacional do instalador automático após o Pacote 463, garantindo que catálogo, perfis, diagnóstico, JSON, scripts CLI, serviços e gates reflitam o mesmo comportamento real.

## Correções principais

- Perfis do `dependencies.catalog.json` passaram a usar IDs reais de dependências.
- Gate de dependências passou a reprovar perfis com grupos inexistentes.
- O diagnóstico principal separa metadados do catálogo de diagnóstico real do ambiente.
- A ponte `php tools/installer/install_cli.php --install-dependencies --diagnose --json` passa a retornar JSON puro em stdout.
- `cmd()` do instalador separa stdout/stderr para automação segura.
- `--all` foi removido definitivamente do script de dependências.
- Foram adicionados `--plan`, `--apply-root` e `--apply-userland`.
- `install-services.sh` foi reforçado com `.env`, logs, validação de usuário/grupo, vendor, porta 5000 e hardening systemd.
- `--install-services` agora instala serviços quando solicitado por perfil, em vez de apenas diagnosticar.
- `provision-server.sh` deixou de tentar instalar dependências da aplicação.
- Diagnóstico de disco foi ajustado para evitar valores irreais em overlay/container.

## Validação esperada

```bash
bash scripts/testing/dependency-catalog-gate.sh
php tools/installer/install_cli.php --install-dependencies --diagnose --json | jq .
bash install/runtime/install-dependencies.sh --profile=production_minimal --plan
```
