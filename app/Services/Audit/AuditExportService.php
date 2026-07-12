<?php

namespace App\Services\Audit;

use App\Models\AuditModel;
use App\Models\SettingModel;
use CodeIgniter\Database\BaseConnection;

class AuditExportService
{

    public function __construct(
        private readonly BaseConnection $db,
        private readonly AuditModel $auditModel = new AuditModel(),
        private readonly SettingModel $settingsModel = new SettingModel(),
    ) {
    }

    public static function createDefault(): self
    {
        return new self(db_connect(), new AuditModel(), new SettingModel());
    }

    public function csvExport(string $dateFrom, string $dateTo): array
    {
        $auditTable = $this->auditModel->getTable();

        $logs = $this->db->table($auditTable)
            ->select($auditTable . '.*, employees.name AS user_name')
            ->join('employees', 'employees.id = ' . $auditTable . '.user_id', 'left')
            ->where($auditTable . '.created_at >=', $dateFrom . ' 00:00:00')
            ->where($auditTable . '.created_at <=', $dateTo . ' 23:59:59')
            ->orderBy($auditTable . '.created_at', 'DESC')
            ->get()
            ->getResult();

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['ID', 'Data/Hora', 'Usuário', 'Ação', 'Entidade', 'Registro ID', 'Descrição', 'Nível', 'IP']);

        foreach ($logs as $log) {
            $entityType = $log->entity_type ?? $log->table_name ?? '';
            $entityId = $log->entity_id ?? $log->record_id ?? '';

            fputcsv($stream, [
                $log->id,
                format_datetime_br($log->created_at),
                $log->user_name ?? 'Sistema',
                $log->action,
                $entityType,
                $entityId,
                $log->description,
                $log->level,
                $log->ip_address ?? '',
            ]);
        }

        rewind($stream);
        $content = stream_get_contents($stream) ?: '';
        fclose($stream);

        return [
            'filename' => "audit_log_{$dateFrom}_to_{$dateTo}.csv",
            'content' => $content,
            'count' => count($logs),
        ];
    }


    public function logCsvExport(int $userId, string $dateFrom, string $dateTo, int $records): void
    {
        $this->auditModel->log(
            $userId,
            'AUDIT_EXPORTED',
            'audit_logs',
            null,
            null,
            ['date_from' => $dateFrom, 'date_to' => $dateTo, 'records' => $records, 'format' => 'csv'],
            "Auditoria exportada em CSV: {$dateFrom} a {$dateTo} ({$records} registros)",
            'info'
        );
    }

    public function afdExport(string $dateFrom, string $dateTo): array
    {
        $punches = $this->db->table('time_punches tp')
            ->select('tp.*, e.pis, e.name AS employee_name, e.cpf')
            ->join('employees e', 'e.id = tp.employee_id', 'left')
            ->where('tp.punch_time >=', $dateFrom . ' 00:00:00')
            ->where('tp.punch_time <=', $dateTo . ' 23:59:59')
            ->orderBy('tp.nsr', 'ASC')
            ->get()
            ->getResult();

        $companyName = $this->settingsModel->get('company_name', 'Empresa');
        $companyCNPJ = $this->settingsModel->get('company_cnpj', '00.000.000/0001-00');
        $content = $this->buildAfdContent($punches, $companyName, $companyCNPJ, $dateFrom, $dateTo);

        $filename = 'AFD_' . str_replace(['/', '-', '.'], '', $companyCNPJ)
            . '_' . date('Ymd', strtotime($dateFrom))
            . '_' . date('Ymd', strtotime($dateTo))
            . '.txt';

        return [
            'filename' => $filename,
            'content' => $content,
            'records' => count($punches),
        ];
    }

    public function logAfdExport(int $userId, string $dateFrom, string $dateTo, int $records): void
    {
        $this->auditModel->log(
            $userId,
            'AFD_EXPORTED',
            'time_punches',
            null,
            null,
            ['date_from' => $dateFrom, 'date_to' => $dateTo, 'records' => $records],
            "AFD exportado: {$dateFrom} a {$dateTo} ({$records} registros)",
            'info'
        );
    }

    private function buildAfdContent(array $punches, string $companyName, string $companyCNPJ, string $dateFrom, string $dateTo): string
    {
        $lines = [];
        $cnpjNumeric = preg_replace('/\D/', '', $companyCNPJ);

        $header = '1';
        $header .= '2';
        $header .= str_pad((string) $cnpjNumeric, 14, '0', STR_PAD_LEFT);
        $header .= str_pad('', 12, '0');
        $header .= str_pad(substr($companyName, 0, 150), 150);
        $header .= '000000000000000';
        $header .= date('dmY', strtotime($dateFrom));
        $header .= date('dmY', strtotime($dateTo));
        $header .= date('dmYHis');
        $lines[] = $header;

        $company = '2';
        $company .= '1';
        $company .= str_pad((string) $cnpjNumeric, 14, '0', STR_PAD_LEFT);
        $company .= str_pad('', 12, '0');
        $company .= str_pad(substr($companyName, 0, 150), 150);
        $lines[] = $company;

        foreach ($punches as $punch) {
            $pisNumeric = preg_replace('/\D/', '', $punch->pis ?? '');
            $punchTime = new \DateTime($punch->punch_time);

            $record = '3';
            $record .= str_pad((string) ($punch->nsr ?? 0), 9, '0', STR_PAD_LEFT);
            $record .= str_pad((string) $pisNumeric, 12, '0', STR_PAD_LEFT);
            $record .= $punchTime->format('dmY');
            $record .= $punchTime->format('Hi');
            $record .= $this->punchTypeCode((string) $punch->punch_type);
            $lines[] = $record;
        }

        $trailer = '9' . str_pad((string) count($lines), 9, '0', STR_PAD_LEFT);
        $lines[] = $trailer;

        return implode("\r\n", $lines);
    }

    private function punchTypeCode(string $punchType): string
    {
        $codes = [
            'entrada' => 'E',
            'saida' => 'S',
            'intervalo_inicio' => 'I',
            'intervalo_fim' => 'F',
        ];

        return $codes[$punchType] ?? 'O';
    }
}
