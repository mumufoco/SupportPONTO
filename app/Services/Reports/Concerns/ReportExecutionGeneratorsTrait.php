<?php

namespace App\Services\Reports\Concerns;

trait ReportExecutionGeneratorsTrait
{
    /**
     * Monta os registros de marcação de ponto para exportação do AFD (Portaria MTE 671/2021).
     *
     * IMPORTANTE — campos exigidos pelo leiaute oficial do registro tipo "7" (REP-P):
     *   - nsr: Número Sequencial de Registro canônico (gerado atomicamente em time_punches.nsr
     *     por NsrGeneratorService — nunca recalculado aqui, sob pena de o AFD divergir do
     *     valor auditável armazenado e quebrar a rastreabilidade exigida pela norma).
     *   - cpf: identificação do empregado é EXCLUSIVA por CPF desde 03/04/2024 — o PIS não é
     *     mais aceito no AFD (por isso buscamos employees.cpf, e não employees.pis).
     *   - punch_time / created_at: usados para compor os campos de data/hora da marcação e da
     *     gravação do registro (formato "DH" do leiaute), e para o encadeamento de hash SHA-256.
     *   - method / user_agent: usados para inferir o "identificador do coletor da marcação"
     *     (campo nº 6 do registro tipo "7").
     *
     * Os registros são ordenados por NSR (item 4 do leiaute: "Ordenar os registros pelo Número
     * Sequencial de Registro - NSR"), e não por punch_time — a ordem cronológica de marcação
     * pode, em casos legítimos (ex.: ajustes manuais retroativos), divergir da ordem de gravação.
     */
    public function buildAfdData(array $filters, ?string $departmentRestriction = null): array
    {
        [$startDate, $endDate] = $this->resolveDateRange($filters);

        $query = $this->timePunchModel
            ->select(
                'time_punches.nsr, time_punches.punch_time, time_punches.created_at, '
                . 'time_punches.method, time_punches.user_agent, '
                . 'employees.cpf, employees.name as employee_name'
            )
            ->join('employees', 'employees.id = time_punches.employee_id')
            // Administrador do sistema não é colaborador CLT -- não deve aparecer no
            // AFD oficial entregue à fiscalização do MTE.
            ->where('employees.role !=', 'admin')
            ->where('time_punches.punch_time >=', $startDate)
            ->where('time_punches.punch_time <=', $endDate)
            ->orderBy('time_punches.nsr', 'ASC');

        if ($departmentRestriction) {
            $query->where('employees.department', $departmentRestriction);
        } else {
            $this->applyDepartmentFilter($query, $filters);
        }

        $this->applyEmployeeIdFilter($query, $filters, 'time_punches.employee_id');
        $this->applyAfdResultLimit($query, $filters);

        $punches = $query->findAll();

        if (isset($filters['limit']) && count($punches) === max(1, min(50000, (int) $filters['limit']))) {
            log_message(
                'warning',
                'AFD pode estar truncado: limite explícito de {limit} registros foi atingido para o período {start} a {end}.',
                ['limit' => $filters['limit'], 'start' => $startDate, 'end' => $endDate]
            );
        }

        // MED-11 (auditoria): employees.cpf agora fica criptografado em repouso. Esta
        // consulta faz JOIN via TimePunchModel (não EmployeeModel), então o callback
        // afterFind() que decripta automaticamente não se aplica aqui — sem decriptar
        // explicitamente, o AFD sairia com o CPF cifrado no lugar do CPF real,
        // invalidando o arquivo perante a fiscalização do MTE.
        return array_map(static function ($punch) {
            return [
                'nsr' => $punch->nsr ?? null,
                'cpf' => \App\Models\EmployeeModel::decryptCpfValue($punch->cpf ?? null) ?? '',
                'punch_time' => $punch->punch_time,
                'created_at' => $punch->created_at,
                'method' => $punch->method ?? '',
                'user_agent' => $punch->user_agent ?? '',
                'employee_name' => $punch->employee_name ?? '',
            ];
        }, $punches);
    }
    protected function generateTimesheetReport(array $filters): array
    {
        [$startDate, $endDate] = $this->resolveDateRange($filters);

        $query = $this->consolidatedModel
            ->select('timesheet_consolidated.*, employees.name as employee_name, employees.department')
            ->join('employees', 'employees.id = timesheet_consolidated.employee_id')
            ->where('employees.role !=', 'admin')
            ->where('date >=', $startDate)
            ->where('date <=', $endDate);

        $this->applyEmployeeIdFilter($query, $filters, 'employee_id');
        $this->applyDepartmentFilter($query, $filters);
        $this->applyResultLimit($query, $filters);

        return ['success' => true, 'data' => $query->asArray()->findAll()];
    }
    protected function generateOvertimeReport(array $filters): array
    {
        [$startDate, $endDate] = $this->resolveDateRange($filters);

        $query = $this->consolidatedModel
            ->select('timesheet_consolidated.*, employees.name as employee_name, employees.department')
            ->join('employees', 'employees.id = timesheet_consolidated.employee_id')
            ->where('employees.role !=', 'admin')
            ->where('date >=', $startDate)
            ->where('date <=', $endDate)
            ->where('extra >', 0);

        $this->applyEmployeeIdFilter($query, $filters, 'employee_id');
        $this->applyDepartmentFilter($query, $filters);
        $this->applyResultLimit($query, $filters);

        $records = $query->asArray()->findAll();

        foreach ($records as &$record) {
            $ts = !empty($record['date']) ? strtotime((string) $record['date']) : false;
            $dayOfWeek = $ts !== false ? date('w', $ts) : null;
            $record['is_weekend'] = $dayOfWeek !== null && ($dayOfWeek === '0' || $dayOfWeek === '6');
        }
        unset($record);

        return ['success' => true, 'data' => $records];
    }
    protected function generateAbsenceReport(array $filters): array
    {
        [$startDate, $endDate] = $this->resolveDateRange($filters);

        $query = $this->consolidatedModel
            ->select('timesheet_consolidated.*, employees.name as employee_name, employees.department')
            ->join('employees', 'employees.id = timesheet_consolidated.employee_id')
            ->where('employees.role !=', 'admin')
            ->where('date >=', $startDate)
            ->where('date <=', $endDate)
            ->groupStart()
            ->where('incomplete', true)
            ->orWhere('owed >', 0)
            ->groupEnd();

        $this->applyEmployeeIdFilter($query, $filters, 'employee_id');
        $this->applyDepartmentFilter($query, $filters);
        $this->applyResultLimit($query, $filters);

        $records = $query->findAll();
        $data = [];

        foreach ($records as $record) {
            $data[] = [
                'date' => $record->date,
                'employee_name' => $record->employee_name,
                'department' => $record->department,
                'type' => $record->incomplete ? 'falta' : 'atraso',
                'punch_time' => $record->first_punch,
                'expected_time' => '08:00',
                'delay_minutes' => $record->owed * 60,
                'justified' => (bool) ($record->justified ?? false),
            ];
        }

        return ['success' => true, 'data' => $data];
    }
    protected function generateBankHoursReport(array $filters): array
    {
        // Administradores do sistema não são colaboradores e não têm banco de horas.
        $query = $this->employeeModel
            ->select('id, name, department, extra_hours_balance, owed_hours_balance')
            ->where('active', true)
            ->where('role !=', 'admin');

        $this->applyEmployeeIdFilter($query, $filters, 'id');
        $this->applyDepartmentFilter($query, $filters, 'department');
        $this->applyResultLimit($query, $filters);

        $data = [];
        foreach ($query->findAll() as $record) {
            $data[] = [
                'employee_name' => $record->name,
                'department' => $record->department,
                'extra_hours_balance' => (float) $record->extra_hours_balance,
                'owed_hours_balance' => (float) $record->owed_hours_balance,
            ];
        }

        return ['success' => true, 'data' => $data];
    }
    protected function generateMonthlyConsolidatedReport(array $filters): array
    {
        [$startDate, $endDate] = $this->resolveDateRange($filters);

        // Pacote 448: agregação em lote. Evita N+1 de employeeModel->find(),
        // countLateArrivals() e countAbsences() para cada funcionário do relatório.
        $query = $this->consolidatedModel
            ->select("timesheet_consolidated.employee_id")
            ->select("employees.name AS employee_name, employees.department")
            ->select("COUNT(*) AS days_worked")
            ->select("COALESCE(SUM(timesheet_consolidated.total_worked), 0) AS total_worked", false)
            ->select("COALESCE(SUM(timesheet_consolidated.expected), 0) AS total_expected", false)
            ->select("COALESCE(SUM(timesheet_consolidated.extra), 0) AS extra", false)
            ->select("COALESCE(SUM(timesheet_consolidated.owed), 0) AS owed", false)
            ->select("COALESCE(SUM(CASE WHEN timesheet_consolidated.first_punch IS NOT NULL AND employees.work_schedule_start IS NOT NULL AND (timesheet_consolidated.first_punch::time > (employees.work_schedule_start::time + (COALESCE((SELECT CASE WHEN value ~ '^[0-9]+$' THEN value::int ELSE NULL END FROM settings WHERE key = 'tolerance_minutes_late' LIMIT 1), 10) * INTERVAL '1 minute'))) THEN 1 ELSE 0 END), 0) AS late_count", false)
            ->select("COALESCE(SUM(CASE WHEN timesheet_consolidated.incomplete = TRUE OR timesheet_consolidated.owed > 0 THEN 1 ELSE 0 END), 0) AS absence_count", false)
            ->join('employees', 'employees.id = timesheet_consolidated.employee_id')
            ->where('employees.role !=', 'admin')
            ->where('timesheet_consolidated.date >=', $startDate)
            ->where('timesheet_consolidated.date <=', $endDate)
            ->groupBy('timesheet_consolidated.employee_id, employees.name, employees.department')
            ->orderBy('employees.name', 'ASC');

        $this->applyEmployeeIdFilter($query, $filters, 'timesheet_consolidated.employee_id');
        $this->applyDepartmentFilter($query, $filters);
        $this->applyResultLimit($query, $filters);

        $data = [];
        foreach ($query->findAll() as $record) {
            $data[] = [
                'employee_name' => $record->employee_name,
                'department' => $record->department,
                'days_worked' => (int) $record->days_worked,
                'total_worked' => (float) $record->total_worked,
                'total_expected' => (float) $record->total_expected,
                'extra' => (float) $record->extra,
                'owed' => (float) $record->owed,
                'late_count' => (int) $record->late_count,
                'absence_count' => (int) $record->absence_count,
            ];
        }

        return ['success' => true, 'data' => $data];
    }
    protected function generateJustificationsReport(array $filters): array
    {
        [$startDate, $endDate] = $this->resolveDateRange($filters);

        // Pacote 448: join direto com employees para eliminar busca por funcionário dentro do loop.
        $query = $this->justificationModel
            ->select('justifications.*, employees.name AS employee_name')
            ->join('employees', 'employees.id = justifications.employee_id', 'left')
            // LEFT JOIN: uma justificativa nunca deve sumir da listagem só porque o
            // colaborador foi excluído (fica "Desconhecido" abaixo) -- por isso o
            // filtro de admin precisa aceitar employees.role NULL, não só != 'admin'
            // (que descartaria silenciosamente as linhas sem colaborador vinculado).
            ->groupStart()
                ->where('employees.role !=', 'admin')
                ->orWhere('employees.role IS NULL', null, false)
            ->groupEnd()
            ->where('justifications.justification_date >=', $startDate)
            ->where('justifications.justification_date <=', $endDate)
            ->orderBy('justifications.justification_date', 'DESC');

        $this->applyEmployeeIdFilter($query, $filters, 'justifications.employee_id');

        if (! empty($filters['status'])) {
            $query->where('justifications.status', $filters['status']);
        }

        $this->applyResultLimit($query, $filters);

        $data = [];
        foreach ($query->findAll() as $record) {
            $data[] = [
                'justification_date' => $record->justification_date,
                'employee_name' => $record->employee_name ?: 'Desconhecido',
                'justification_type' => $record->justification_type,
                'category' => $record->category,
                'reason' => $record->reason,
                'status' => $record->status,
                'has_attachments' => ! empty($record->attachments),
                'created_at' => $record->created_at,
            ];
        }

        return ['success' => true, 'data' => $data];
    }
    protected function generateWarningsReport(array $filters): array
    {
        [$startDate, $endDate] = $this->resolveDateRange($filters);

        $query = $this->warningModel
            ->select('warnings.*, employees.name AS employee_name, employees.department')
            ->join('employees', 'employees.id = warnings.employee_id')
            ->where('employees.role !=', 'admin')
            ->where('warnings.occurrence_date >=', $startDate)
            ->where('warnings.occurrence_date <=', $endDate)
            ->orderBy('warnings.occurrence_date', 'DESC');

        $this->applyEmployeeIdFilter($query, $filters, 'warnings.employee_id');
        $this->applyDepartmentFilter($query, $filters);
        $this->applyResultLimit($query, $filters);

        $data = [];
        foreach ($query->findAll() as $record) {
            $data[] = [
                'occurrence_date' => $record->occurrence_date,
                'employee_name' => $record->employee_name ?: 'Desconhecido',
                'department' => $record->department,
                'warning_type' => $record->warning_type,
                'reason' => $record->reason,
                'status' => $record->status,
            ];
        }

        return ['success' => true, 'data' => $data];
    }

}
