# Gate do catálogo de dependências

O gate `dependency-catalog-gate.sh` valida o catálogo e a automação de dependências.

## Validações bloqueantes

- versão `1.1.464` no catálogo;
- perfis de instalação presentes;
- Nginx e Apache não podem ser instalados juntos por padrão;
- `--all` deve estar descontinuado;
- Composer/npm/pip bloqueados como root por padrão;
- instalador web não pode oferecer instalação de DeepFace/Node;
- `COMPOSER_ALLOW_SUPERUSER` não pode estar no instalador principal;
- `install-services.sh` deve existir;
- DeepFace deve usar `opencv-python-headless`;
- catálogo deve refletir `composer.json`, `composer.lock` e `requirements.txt`.

## Execução

```bash
bash scripts/testing/dependency-catalog-gate.sh
```

## Validações adicionais v1.1.464

O gate agora reprova:

- perfis que referenciam dependências inexistentes;
- `extends` apontando para perfil inexistente;
- ponte CLI que contamine JSON com logs;
- uso de `--allow-deprecated-all`;
- ausência de hardening mínimo nos serviços systemd.
