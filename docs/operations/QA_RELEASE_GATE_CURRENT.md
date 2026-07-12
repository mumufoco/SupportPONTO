# QA Release Gate Current

Release atual: **v1.1.500**

Cobertura current:
- rodada 340A-340H
- rodada 355A-355F
- rodada 361A-361E
- rodada 366A-366F
- consistência do modelo de release/deploy único (ver `release_consistency_audit`)
- governança de dependências críticas (baseline + faixas declaradas em `composer.json`)

> A separação "source package vs release package" e a auditoria de
> `artifact-manifest.json` foram **abandonadas** na limpeza completa
> (v1.1.498 → v1.1.500): o repositório é o próprio artefato de produção,
> entregue via `scripts/release/deploy.sh`. Ver `docs/releases/ARTIFACT_STRATEGY.md`.

Cobertura adicional do pacote 382:
- relatório integrado de prontidão final
- matriz current de smoke/go-live
