# Pacote 455 — Clean Code, SOLID e organização

## Objetivo

Reduzir acoplamento, duplicação e decisões espalhadas em serviços críticos, mantendo o comportamento existente.

## Alterações estruturais

### DTO de status de job

Criado `App\DTO\Queue\AsyncJobStatusData` para concentrar casts, valores padrão e cálculo de download disponível para jobs assíncronos. Antes, essa apresentação ficava diretamente no `AsyncJobService`, misturando orquestração de fila com formatação de resposta.

### Catálogo de tipos de job

Criado `App\Services\Queue\Support\AsyncJobTypeCatalog` para concentrar fila e prioridade por tipo de job. Isso evita duplicação de `match` e deixa o worker com responsabilidade menor.

### Exceção de domínio padronizada

Criado `App\Exceptions\DomainOperationException` como exceção base para falhas previsíveis de domínio. A classe não altera fluxos existentes, mas cria um ponto padronizado para novos serviços.

### Gate de qualidade

Criado `tools/quality/clean-code-audit.php`, integrado ao Composer, para bloquear regressões simples:

- arquivos PHP críticos grandes demais;
- chamadas de debug bruto em `app/`;
- ausência de DTO/catálogo do worker;
- ausência desta documentação arquitetural.

## Regras adotadas

1. Services devem orquestrar casos de uso, não formatar payloads complexos de resposta.
2. Decisões estáveis de catálogo devem ficar em classes pequenas e testáveis.
3. DTOs simples são aceitos quando reduzem casts repetidos e contratos ambíguos.
4. Comentários devem explicar intenção técnica atual, não histórico de pacotes antigos.
5. Refatoração não deve alterar contrato público sem teste ou documentação.

## Próximas classes grandes a acompanhar

O pacote evita refatorações agressivas sem runtime completo, mas marca como candidatos futuros:

- `ReleaseGateService`;
- `SystemHealthCheckService`;
- `AuditModel`;
- `EmployeeModel`;
- `TimePunchModel`;
- controllers com mais de 400 linhas.

Esses arquivos devem ser quebrados em passos menores, com testes de comportamento antes de mudanças profundas.
