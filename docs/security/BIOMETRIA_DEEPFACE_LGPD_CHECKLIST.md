# Checklist — Biometria facial, DeepFace e LGPD

## Isolamento operacional

- A API DeepFace deve rodar como serviço separado, preferencialmente containerizado.
- O PHP não deve carregar modelos faciais em memória.
- `DEEPFACE_TIMEOUT` deve permanecer baixo o suficiente para não prender workers PHP.
- O circuit breaker deve permanecer habilitado em produção.
- O rate limit biométrico deve estar ativo em rotas web e API que recebem imagem.

## Upload e payload de imagem

- Aceitar apenas JPEG/PNG em base64.
- Limitar tamanho por `BIOMETRIC_FACE_MAX_BYTES`.
- Validar MIME real com `finfo`.
- Validar dimensões com `getimagesizefromstring`.
- Rejeitar payload corrompido, muito pequeno, grande demais ou com formato desconhecido.
- Não confiar em extensão, nome de arquivo ou prefixo `data:image` isoladamente.

## Dados biométricos e LGPD

- Exigir consentimento antes de cadastro facial.
- Permitir revogação de consentimento com expurgo dos dados biométricos.
- Evitar expor hash, caminho físico, descriptor facial ou metadados sensíveis nas APIs.
- Auditar cadastro, teste, exclusão e revogação.
- Restringir diagnósticos e gestão biométrica a perfis autorizados.

## Fallback e indisponibilidade

- Quando DeepFace estiver offline, o sistema principal não deve cair.
- O usuário deve receber mensagem genérica de indisponibilidade temporária.
- Erros técnicos detalhados devem ficar apenas nos logs.
- O circuit breaker deve abrir após falhas consecutivas e reabrir em half-open após janela controlada.

## Produção

- `DEEPFACE_API_KEY` deve ser forte e diferente de placeholders.
- DeepFace não deve ser exposto publicamente sem proxy/autenticação.
- `docker-compose.yml` deve limitar CPU/memória do serviço DeepFace.
- Logs não devem conter imagem base64, descriptor facial ou segredo de integração.
