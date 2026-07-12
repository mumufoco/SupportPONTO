# Go Live Release Authorization Current

Release atual: **v1.1.500**

Autorização condicionada a:
- `php spark release:gate` sem reprovações críticas
- deploy executado via `scripts/release/deploy.sh` (script único: empacota → transfere → instala → reinicia)
- checklist de produção executado
- smoke/gate sem bloqueadores críticos (`scripts/verify-deployment.sh`, `/health/readiness`)

> Os antigos requisitos "release package validado" e "manifesto do artefato
> coerente" foram **substituídos**: não existe mais empacotamento intermediário
> nem `artifact-manifest.json` (conceito abandonado na limpeza completa
> v1.1.498 → v1.1.500). O deploy é direto do repositório para o servidor.

Rastreabilidade current preserva as rodadas 340A-340H, 355A-355F, 361A-361E e 366A-366F.


## Pacote 382

A autorização final de go-live passa a exigir a anexação dos relatórios de `go-live-gate.sh` e `final-production-readiness.sh`.
