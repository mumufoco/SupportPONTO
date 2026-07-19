<?php

namespace App\Models;

use CodeIgniter\Model;

class PendingPunchModel extends Model
{
    protected $table      = 'pending_punches';
    protected $primaryKey = 'id';
    protected $returnType = 'object';

    protected $allowedFields = [
        'employee_id', 'intended_punch_type', 'intended_time', 'nsr_provisional',
        'justification_text', 'situation_type', 'evidence_package', 'methods_attempted',
        'technical_failures_count', 'terminal_id', 'ip_address', 'user_agent',
        'geolocation_lat', 'geolocation_lng', 'status', 'reviewed_by', 'reviewed_at',
        'review_notes', 'final_punch_id', 'expires_at', 'processed_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'employee_id'          => 'required|integer',
        'intended_punch_type'  => 'required|in_list[entrada,saida,intervalo_inicio,intervalo_fim]',
        'intended_time'        => 'required',
        'justification_text'   => 'required|min_length[20]|max_length[1000]',
        'situation_type'       => 'required|in_list[equipment_failure,system_slow,camera_inaccessible,biometric_failed,missing_checkout,other]',
        'status'               => 'required|in_list[pending,approved,rejected,expired,cancelled,awaiting_employee]',
    ];

    /** Retorna pendências aguardando aprovação, opcionalmente por departamento */
    public function getPending(?string $department = null): array
    {
        $builder = $this->select('pending_punches.*, employees.name AS employee_name, employees.department')
            ->join('employees', 'employees.id = pending_punches.employee_id', 'left')
            ->where('pending_punches.status', 'pending')
            ->groupStart()
                ->where('pending_punches.expires_at IS NULL', null, false)
                ->orWhere('pending_punches.expires_at >', date('Y-m-d H:i:s'))
            ->groupEnd()
            ->orderBy('pending_punches.intended_time', 'DESC');

        if ($department !== null && $department !== '') {
            $builder->where('employees.department', $department);
        }

        return $builder->findAll();
    }

    /** Expirar registros pendentes há mais de X horas sem aprovação */
    public function expireStale(int $hoursThreshold = 24): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$hoursThreshold} hours"));
        $this->whereIn('status', ['pending', 'awaiting_employee'])->where('created_at <', $cutoff)
            ->set(['status' => 'expired', 'updated_at' => date('Y-m-d H:i:s'), 'processed_at' => date('Y-m-d H:i:s')])->update();
        return $this->db->affectedRows();
    }

    /** Pendências geradas pelo sistema (virada de dia) aguardando o colaborador preencher a justificativa */
    public function getAwaitingEmployee(int $employeeId): array
    {
        return $this->where('employee_id', $employeeId)
            ->where('status', 'awaiting_employee')
            ->orderBy('intended_time', 'DESC')
            ->findAll();
    }

    /**
     * Verifica se já existe uma pendência (aguardando colaborador ou já em análise
     * do gestor) cobrindo este colaborador/tipo/dia — evita que o cron de virada de
     * dia crie duplicatas em execuções seguintes enquanto a pendência não é resolvida.
     */
    public function hasOpenPendingForDay(int $employeeId, string $punchType, string $date): bool
    {
        return $this->where('employee_id', $employeeId)
            ->where('intended_punch_type', $punchType)
            ->whereIn('status', ['awaiting_employee', 'pending'])
            ->where('intended_time >=', $date . ' 00:00:00')
            ->where('intended_time <', $date . ' 23:59:59')
            ->countAllResults() > 0;
    }

    /**
     * Cancela todas as pendências abertas de um colaborador — usado ao desligar/
     * desativar (MED-10 na auditoria): antes, pendências continuavam nas filas de
     * aprovação de gestores mesmo após o desligamento, e podiam ser aprovadas para
     * alguém que já não estava mais na folha.
     */
    public function cancelAllForEmployee(int $employeeId, string $reason): int
    {
        $this->where('employee_id', $employeeId)->where('status', 'pending')->set([
            'status'       => 'cancelled',
            'review_notes' => $reason,
            'processed_at' => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ])->update();

        return $this->db->affectedRows();
    }

    /** Histórico de pendências de um colaborador no mês */
    public function monthlyCountByEmployee(int $employeeId, string $yearMonth): int
    {
        $start = $yearMonth . '-01 00:00:00';
        $end   = date('Y-m-d H:i:s', strtotime($yearMonth . '-01 +1 month'));

        // Conta apenas pendências ABERTAS (aguardando revisão). Registros já
        // aprovados/rejeitados/expirados não devem consumir a cota mensal do
        // colaborador indefinidamente — do contrário ele fica bloqueado para
        // sempre após atingir o limite, mesmo que os pedidos antigos já tenham
        // sido resolvidos.
        return $this->where('employee_id', $employeeId)
            ->where('status', 'pending')
            ->where('intended_time >=', $start)
            ->where('intended_time <', $end)
            ->countAllResults();
    }

    /** Decodifica evidence_package de JSON para array */
    public function decodeEvidence(object $record): array
    {
        $raw = $record->evidence_package ?? null;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }
}

