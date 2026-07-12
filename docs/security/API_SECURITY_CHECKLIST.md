# Checklist de segurança da API — Pacote 444

## Autenticação e autorização

- [x] APIs de dados exigem OAuth2 Bearer token.
- [x] APIs gerenciais exigem RBAC por perfil.
- [x] Endpoints públicos são mínimos e documentados.
- [x] Rotas `dashboard`, `deepface`, `employee/team` e `employee/by-code` não são acessíveis por funcionário comum.

## Entrada e validação

- [x] JSON malformado é bloqueado antes do controller.
- [x] `application/x-www-form-urlencoded` permanece aceito nos endpoints OAuth legados.
- [x] Payload textual passa por sanitização defensiva comum.
- [x] Senhas, tokens, imagens/base64 e templates biométricos não são alterados pelo sanitizador textual.

## Resposta e exposição de dados

- [x] Respostas de erro incluem código e `request_id`.
- [x] Exceções internas não são retornadas por `ApiController`.
- [x] `validate-code` não retorna nome, departamento ou cargo.
- [x] Health check não expõe versão PHP, ambiente, host ou banco.

## Rate limit

- [x] Rate limit declarado nas rotas API.
- [x] `reports/status` e `jobs/status` não são mais excluídos por conterem a palavra `status`.
- [x] Rotas biométricas mantêm limitador específico.

## Debug

- [x] Nenhum endpoint `debug`, `phpinfo`, `var_dump`, `print_r` ou `dd()` foi exposto em `app/Controllers/API` ou `app/Config/Routes/90_api.php`.
