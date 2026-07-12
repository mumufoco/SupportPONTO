# Pacote 436 — Permissões, perfis e controle de acesso

## Objetivo
Resolver risco OWASP A01 — Broken Access Control por meio de matriz RBAC canônica, filtros explícitos por rota, resposta 403 padronizada e cobertura de regressão estática.

## Perfis suportados
- `admin`: acesso total.
- `rh`: gestão de colaboradores, relatórios operacionais, advertências, justificativas e biometria.
- `gestor`: gestão e relatórios do seu escopo/equipe/departamento.
- `funcionario`: autosserviço, ponto, justificativas e biometria própria.
- `auditor`: auditoria, exportação de auditoria, relatórios de conformidade e matriz de permissões.
- `dpo`: LGPD, auditoria, exportação de auditoria, relatórios de conformidade e matriz de permissões.

## Alterações principais
- `App\Enums\Role` passou a reconhecer `auditor` e aliases de auditoria.
- `EmployeeModel` passou a validar `auditor` no campo `role`.
- `AuthorizationService` recebeu matriz explícita para todos os perfis obrigatórios e métodos de introspecção (`permissionMatrix`, `allowedRolesFor`).
- `RoleFilter` cria guarda RBAC genérico para rotas web usando `role:admin,rh,...`.
- `ApiRoleFilter` cria guarda RBAC para rotas OAuth2 da API, com resposta JSON 403/401 padronizada.
- `AuthFilter` passou a renderizar 403 HTML padronizado em vez de redirecionamento silencioso para negações de permissão.
- Rotas sensíveis de DPO, LGPD, auditoria e compliance passaram a usar filtros de perfil explícitos.
- Endpoints DeepFace da API passaram a exigir papel privilegiado (`admin`, `rh` ou `gestor`) além de OAuth2.
- Migração `2026-05-17-0436_RbacRolesAndPermissions` sincroniza a tabela `roles` com perfis e permissões canônicas.

## Validação executada
- `php -l` nos arquivos PHP alterados/criados.
- Verificação estática de normalização de perfis via `AuthorizationService`.
- Teste estático criado em `tests/Feature/Package436RbacAccessControlStaticTest.php`.

## Observação operacional
O pacote preserva `AdminFilter` e `ManagerFilter` por compatibilidade, mas novas rotas sensíveis devem preferir `role:<perfil...>` para expressar a matriz de permissão diretamente no roteamento.
