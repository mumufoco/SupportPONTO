# Pacote 392 — alinhamento current entre código, rotas e documentação

## Objetivo
Eliminar drift documental nas superfícies mais críticas do sistema, com foco em autenticação web, rotas de recuperação de senha e superfícies de registro de ponto.

## Ajustes current consolidados
- login web canônico em `GET/POST /auth/login`;
- logout web canônico em `POST /auth/logout`;
- recuperação de senha em `GET/POST /auth/forgot-password`;
- reset de senha em `GET /auth/reset-password/{token}` e `POST /auth/reset-password`;
- rota canônica de registro de ponto autenticado em `GET /timesheet/punch`, com alias protegido `GET /punch`;
- quick access público em `GET /timesheet/quick-access-public` e alias operacional `GET /registro-rapido`;
- terminal público em `GET /punch-terminal` e alias canônico `GET /timesheet/punch-terminal`;
- comprovante de ponto current em `GET /timesheet/receipt/{id}`; a view `app/Views/punch/success.php` permanece apenas como artefato utilitário/legado sem rota current direta.

## Regra operacional
Toda documentação nova deve preferir rotas canônicas ou nomes de rota, evitando paths antigos ou implícitos quando houver helper/alias consolidado no código.
