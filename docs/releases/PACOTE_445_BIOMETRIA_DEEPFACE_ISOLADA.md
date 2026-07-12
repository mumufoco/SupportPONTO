# Pacote 445 — Biometria facial e dependências DeepFace

## Objetivo

Isolar e estabilizar o módulo de biometria facial para que lentidão, indisponibilidade ou alto consumo do DeepFace não derrube o sistema principal.

## Problemas tratados

- Chamadas diretas ao DeepFace sem circuit breaker central.
- Upload facial validado tarde demais em alguns fluxos.
- Rotas biométricas misturadas com rotas genéricas de acesso.
- Diagnóstico biométrico sem estado do circuit breaker.
- Fallback legado de verificação facial usando variável inexistente `$force`.
- API DeepFace Python sem `MAX_CONTENT_LENGTH` do Flask e com base64 sem validação estrita.

## Alterações principais

- Criado `App\Services\Biometric\DeepFaceCircuitBreakerService`.
- `FaceDeepFaceClient` e `DeepFaceApiClient` agora respeitam circuit breaker.
- Erros de comunicação retornam fallback controlado em vez de vazar exceções técnicas.
- `FaceImageService` agora valida base64, tamanho, MIME real e dimensões.
- Controllers web/API validam imagem antes de enfileirar ou chamar reconhecimento.
- Rotas biométricas foram isoladas em `app/Config/Routes/55_biometrics.php`.
- `app/Config/Routes/60_access_points.php` ficou apenas com QR/kiosk/punch terminal.
- DeepFace Python recebeu limite de tamanho de request e validação base64 estrita.
- `.env.example` e `.env.production.example` receberam variáveis de limite biométrico e circuit breaker.
- `docker-compose.yml` recebeu variáveis operacionais adicionais para DeepFace.

## Variáveis novas

```env
DEEPFACE_CIRCUIT_BREAKER_ENABLED = true
DEEPFACE_CIRCUIT_FAILURE_THRESHOLD = 3
DEEPFACE_CIRCUIT_OPEN_SECONDS = 120
DEEPFACE_CIRCUIT_HALF_OPEN_AFTER = 120
BIOMETRIC_FACE_MAX_BYTES = 3145728
BIOMETRIC_FACE_MIN_BYTES = 1000
BIOMETRIC_FACE_MAX_PIXELS = 6000000
```

## Validação esperada

- DeepFace offline deve retornar erro controlado, não erro fatal.
- Após falhas consecutivas, novas chamadas devem ser bloqueadas temporariamente pelo circuit breaker.
- Imagens inválidas, grandes demais ou corrompidas devem ser rejeitadas antes do processamento.
- Rotas biométricas de gestão devem exigir perfil adequado.
- Consentimento e revogação devem permanecer preservados.
