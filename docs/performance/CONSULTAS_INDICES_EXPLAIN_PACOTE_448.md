# Pacote 448 — Consultas críticas, índices e EXPLAIN

## Consultas mapeadas

As consultas críticas do SupportPONTO para uso real são:

1. marcações de ponto por funcionário e período;
2. marcações por período para AFD/relatórios;
3. consolidação diária/mensal por funcionário;
4. relatórios por departamento/setor;
5. justificativas por funcionário, período e status;
6. escalas por funcionário, data e status;
7. auditoria por período, usuário e ação;
8. fila de relatórios por status/prioridade.

## Índices criados

A migration `2026-05-17-0448_PerformanceIndexesForReportsAndTimesheet.php` cria índices idempotentes, somente em PostgreSQL, para:

- `time_punches(employee_id, punch_time, punch_type, method, status)`;
- `time_punches(punch_time, employee_id)`;
- `timesheet_consolidated(date, employee_id)`;
- `timesheet_consolidated(employee_id, date, incomplete, justified)`;
- `employees(active, role, name)`;
- `employees(work_unit_id, department_id, department, active, name)`;
- `justifications(employee_id, justification_date, status)`;
- `schedules(employee_id, date, status)`;
- `audit_logs(created_at, user_id, action)`;
- `report_queue(status, priority, created_at)`.

A migration valida se a tabela e as colunas existem antes de criar cada índice. Isso evita erro fatal em instalações com histórico de migrations divergente.

## N+1 removido

O relatório `consolidado-mensal` deixou de buscar funcionário e contadores de atraso/falta dentro do loop. A consulta agora agrega em lote com `JOIN employees` e `SUM(CASE...)`.

O relatório `justificativas` agora usa `JOIN employees` para obter o nome do funcionário no mesmo SELECT.

## EXPLAIN opcional

O serviço `App\Services\Database\QueryPlanAnalyzerService` permite análise segura de plano:

```php
$analyzer = service('queryPlanAnalyzerService');
$result = $analyzer->explainCriticalQueries('2026-05-01', '2026-05-31', 10);
```

Ele aceita somente `SELECT`, usa `EXPLAIN (FORMAT JSON)` no PostgreSQL e informa se o plano usa índice ou se há `Seq Scan`.

## Critério de validação em produção

Após rodar migrations, validar pelo menos:

```sql
EXPLAIN (ANALYZE, BUFFERS)
SELECT id, employee_id, punch_time
FROM time_punches
WHERE employee_id = 1
  AND punch_time >= '2026-05-01 00:00:00'
  AND punch_time < '2026-06-01 00:00:00'
ORDER BY punch_time ASC;

EXPLAIN (ANALYZE, BUFFERS)
SELECT employee_id, date, total_worked
FROM timesheet_consolidated
WHERE date >= '2026-05-01'
  AND date <= '2026-05-31'
ORDER BY date ASC;
```

O esperado é que bases com volume relevante usem `Index Scan`, `Index Only Scan` ou `Bitmap Index Scan` nos filtros por período/funcionário.
