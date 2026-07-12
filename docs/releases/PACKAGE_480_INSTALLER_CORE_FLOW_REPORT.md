# Package 481 — Correção real do fluxo automático do instalador

Release: `v1.1.484`

## Problema corrigido

O instalador automático ainda travava ou bloqueava a instalação base porque o diagnóstico principal executava verificações pesadas e opcionais, como DeepFace, TensorFlow, Python e diagnóstico shell de dependências.

## Correções

- Separado diagnóstico core e diagnóstico full.
- Removido `biometric-doctor` do diagnóstico padrão.
- Removido diagnóstico shell de dependências do diagnóstico padrão.
- Criado `--diagnose-core`.
- Criado `--diagnose-full`.
- Mantido `--diagnose` como alias seguro de `--diagnose-core`.
- `diagnoseDependencyRuntime()` agora recebe `--php-bin` com o PHP real do instalador.
- `blocking` passou a representar apenas bloqueios core.
- `optional_blocking` armazena falhas opcionais.
- Adicionada política explícita `dependency_policy` ao relatório.

## Resultado esperado

A tela inicial e o diagnóstico base deixam de travar por dependências biométricas, scripts shell ou stack operacional avançada. O usuário consegue avançar na preparação do sistema base e executar diagnósticos avançados somente quando necessário.
