# Diretriz de componentização de views

## Views grandes que devem evoluir por partials
- `employees/create.php`
- `employees/edit.php`
- `settings/index.php`
- `biometric/enrollment.php`
- `chat/room.php`
- `audit/index.php`

## Regra
Sempre separar:
- cabeçalho
- filtros
- resumo/KPIs
- conteúdo principal
- rodapé/ações
- scripts isolados quando necessário

## Objetivo
Reduzir peso de manutenção e evitar regressões em telas grandes.
