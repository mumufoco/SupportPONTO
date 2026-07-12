# Fase 4 — Dependências Biométricas e DeepFace

Versão: **SupportPONTO v1.1.476**

## Objetivo

Padronizar a preparação biométrica do instalador sem transformar o instalador web em executor de pacotes pesados. A Fase 4 recomenda produção com **DeepFace API externa ou container isolado**, mantém DeepFace local como opção CLI controlada e adiciona um diagnóstico operacional dedicado.

## Decisões técnicas

1. O instalador web continua proibido de instalar DeepFace, TensorFlow, OpenCV, Node ou serviços systemd.
2. O perfil recomendado para produção passa a ser `production_deepface_external_recommended`.
3. O novo `install/runtime/biometric-doctor.sh` valida a API DeepFace, Python local, venv, imports Python, recursos mínimos, systemd e chave de API sem imprimir segredos.
4. O instalador principal agora inclui `biometric_runtime_diagnosis` no diagnóstico completo.
5. O CLI do instalador expõe a ponte `--biometric-doctor`.

## Comandos principais

```bash
php tools/installer/install_cli.php --biometric-doctor --json
php tools/installer/install_cli.php --biometric-doctor --url=http://127.0.0.1:5000 --strict
bash install/runtime/biometric-doctor.sh --json --skip-api
bash install/runtime/biometric-doctor.sh --json --url=http://127.0.0.1:5000
```

## Política de produção recomendada

Para produção corporativa, use um serviço externo ou container DeepFace separado do processo PHP. Isso reduz risco de conflito de Python/TensorFlow no servidor web, melhora isolamento, facilita rollback e diminui impacto de memória/CPU sobre o SupportPONTO.

## Artefatos gerados

- `writable/installer/biometric-doctor-last.json`
- `writable/installer/biometric-doctor-YYYY-MM-DD.log`

## Segurança

O doctor mascara URL sensível, não imprime `DEEPFACE_API_KEY`, não grava segredo no relatório e usa `X-API-Key` apenas na chamada HTTP. O diagnóstico JSON é pensado para suporte técnico sem expor tokens.

## Validação esperada

- O script retorna JSON puro com `--json`.
- Ausência de API é aviso por padrão e bloqueio apenas com `--strict`.
- Python fora de 3.10/3.11 gera aviso para DeepFace local.
- Produção deve preferir `production_deepface_external_recommended`.
