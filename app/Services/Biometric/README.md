# Serviços biométricos

## DeepFaceService

Métodos canônicos para novos chamadores:

- `enrollFace(int $employeeId, string $photoBase64): array`
- `recognizeFace(string $photoBase64, ?float $customThreshold = null): array`
- `verifyFace(int $employeeId, string $photoBase64): array`
- `analyzeFace(string $photoBase64): array`

Aliases legados ainda aceitos por compatibilidade transitória:

- `enroll()` → delega para `enrollFace()`
- `recognize()` → delega para `recognizeFace()`
- `verify()` → aceita dois modos:
  - `verify(int $employeeId, string $photoBase64)`
  - `verify(string $photo1, string $photo2)`
- `analyze()` → delega para `analyzeFace()`

### Regra de manutenção

Novos chamadores devem usar sempre os métodos `*Face()` canônicos.
Os aliases legados existem apenas para testes antigos, POCs e compatibilidade gradual.
