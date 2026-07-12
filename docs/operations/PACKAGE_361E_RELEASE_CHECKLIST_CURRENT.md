# Package 361E Release Checklist Current

Pacote 361E

Release atual: **v1.1.500**

Checklist preservado para trilha current:
- dependências críticas governadas (ver `docs/operations/DEPENDENCY_BASELINE_CURRENT.md`)
- constraints abertas removidas das libs críticas
- pipeline de release/deploy validado por `php spark release:gate` (modelo single-script-ssh-deploy, sem dependência de `tools/release/*.php` ou Docker)
