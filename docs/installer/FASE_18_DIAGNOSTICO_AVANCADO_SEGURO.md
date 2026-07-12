# Fase 18 — Diagnóstico avançado leve e seguro

Release: `v1.1.484`

## Objetivo

Impedir que o diagnóstico avançado do instalador trave por causa de verificações pesadas de shell, Python, DeepFace, TensorFlow, OpenCV ou API biométrica.

## Correções implementadas

- `--diagnose-full` agora executa diagnóstico avançado em modo leve por padrão.
- Criado comando explícito `--diagnose-full-light`.
- Criados comandos dedicados:
  - `--diagnose-biometric`
  - `--diagnose-biometric-strict`
- `biometric-doctor.sh` recebeu novos modos:
  - `--light`
  - `--api-only`
  - `--skip-local-imports`
  - `--local-imports`
  - `--timeout=N`
- Imports locais de `deepface`, `tensorflow` e `cv2` não rodam no modo leve.
- O modo strict continua disponível, mas com timeout por comando.
- `disk_free_mb` agora retorna `unknown` quando o filesystem não permite medição confiável, evitando falso `0 MB`.
- O diagnóstico biométrico leve passa a ser tratado como pós-instalação/opcional.

## Comandos

```bash
php tools/installer/install_cli.php --diagnose-full --json
php tools/installer/install_cli.php --diagnose-full-light --json
php tools/installer/install_cli.php --diagnose-biometric --json
php tools/installer/install_cli.php --diagnose-biometric-strict --json
bash install/runtime/biometric-doctor.sh --json --light --timeout=8
bash install/runtime/biometric-doctor.sh --json --strict --local-imports --timeout=10
```

## Resultado esperado

O diagnóstico avançado deve retornar JSON mesmo em ambiente sem DeepFace, sem TensorFlow, sem OpenCV ou com API indisponível. Falhas desses itens são relatadas como avisos, exceto quando o modo strict é solicitado explicitamente.
