# Pacote 444 — API e integrações internas

## Objetivo
Auditar, restaurar e estabilizar os endpoints de API do SupportPONTO, reduzindo risco de exposição de dados sensíveis e falhas por entrada inválida ou dependências ausentes.

## Principais correções

- Criado filtro `api-json` para bloquear JSON malformado antes dos controllers.
- Criado serviço `App\Services\API\ApiPayloadSanitizer` para sanitização defensiva de payloads textuais.
- Registrado `apiPayloadSanitizer()` em `Config\Services`.
- Rotas de API reorganizadas em `app/Config/Routes/90_api.php` com política explícita:
  - públicas mínimas: `GET /api/health` e `POST /api/validate-code`;
  - autenticação OAuth2 para APIs operacionais;
  - RBAC para dashboard, DeepFace e consultas gerenciais;
  - rate limit por rota de API;
  - validação JSON em todas as rotas de API com corpo.
- Controllers existentes foram recolocados no mapa de rotas:
  - `DashboardController`;
  - `EmployeeController`;
  - `TimePunchController`;
  - `NotificationController`;
  - `PushNotificationController`.
- `ApiController::validateCode()` deixou de retornar nome, departamento e cargo em endpoint público legado.
- `ApiController` removeu retorno de erro interno de exceção em produção/desenvolvimento na resposta JSON pública.
- `OAuth2Controller` passou a aceitar payload via JSON ou form-data usando o normalizador/sanitizador comum.
- `RateLimitFilter` deixou de ignorar qualquer rota contendo `status`, evitando bypass em `reports/status` e `jobs/status`.
- Removido rate limit duplicado global em `api/*`; a proteção agora fica declarada nas rotas de API para evitar dupla contagem.

## Endpoints públicos mantidos

- `GET /api/health`: readiness mínimo, sem versão PHP, host, DB name ou stack.
- `POST /api/validate-code`: compatibilidade com terminal, com throttle e resposta mínima.

## Endpoints protegidos

- `auth/me`, `auth/logout`, `auth/change-password`.
- `oauth/revoke`, `oauth/tokens`, `oauth/revoke-all`.
- `deepface/*`: `admin`, `rh` ou `gestor`.
- `dashboard/*`: `admin`, `rh` ou `gestor`.
- `employee/team` e `employee/by-code/*`: `admin`, `rh` ou `gestor`.
- `biometric/*`, `time-punch/*`, `notifications/*`, `push/*`, `chat/*`, `reports/*`, `jobs/*`: OAuth2 obrigatório.

## Arquivos alterados/criados

- `app/Config/Routes/90_api.php`
- `app/Config/Filters.php`
- `app/Config/Services.php`
- `app/Filters/ApiJsonRequestFilter.php`
- `app/Filters/RateLimitFilter.php`
- `app/Controllers/API/ApiController.php`
- `app/Controllers/API/AuthController.php`
- `app/Controllers/API/BaseApiController.php`
- `app/Controllers/API/OAuth2Controller.php`
- `app/Services/API/ApiPayloadSanitizer.php`
- `docs/security/API_SECURITY_CHECKLIST.md`
- `tests/Feature/Package444ApiSecurityStaticTest.php`
- `public/version.json`

## Validação recomendada em produção/homologação

1. Executar `php spark routes | grep api` e confirmar rotas protegidas.
2. Testar `GET /api/health` sem token.
3. Testar `GET /api/v1/auth/me` sem token e confirmar 401.
4. Testar `GET /api/v1/dashboard` com funcionário comum e confirmar 403.
5. Testar `POST /api/v1/auth/login` com JSON malformado e confirmar 400 `invalid_json`.
6. Testar `POST /api/validate-code` com código válido e confirmar que não retorna nome/departamento/cargo.
