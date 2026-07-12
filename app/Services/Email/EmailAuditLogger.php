<?php

namespace App\Services\Email;

use App\Models\AuditModel;
use App\Services\Audit\CanonicalAuditLogger;
use CodeIgniter\Database\BaseConnection;

class EmailAuditLogger
{
    private readonly CanonicalAuditLogger $auditLogger;

    public function __construct(
        private readonly AuditModel $auditModel,
        private readonly BaseConnection $db
    ) {
        $this->auditLogger = new CanonicalAuditLogger($this->auditModel);
    }

    /**
     * @param string|array $to
     */
    public function logSend(string|array $to, string $subject, bool $success, ?string $details = null): void
    {
        $recipients = is_array($to) ? implode(', ', $to) : $to;

        $this->auditLogger->logEntityEvent(
            null,
            $success ? 'EMAIL_SENT' : 'EMAIL_FAILED',
            'emails',
            null,
            null,
            [
                'to' => $recipients,
                'subject' => $subject,
                'success' => $success,
                'details' => $details,
            ],
            sprintf(
                'Email %s - Para: %s - Assunto: %s',
                $success ? 'enviado' : 'falhou',
                $recipients,
                $subject
            ),
            $success ? 'info' : 'error'
        );
    }

    public function statistics(): array
    {
        $auditTable = $this->auditModel->getTable();

        $totalSent = $this->db->table($auditTable)
            ->where('action', 'EMAIL_SENT')
            ->countAllResults();

        $totalFailed = $this->db->table($auditTable)
            ->where('action', 'EMAIL_FAILED')
            ->countAllResults();

        [$todayStartAt, $tomorrowStartAt] = [date('Y-m-d 00:00:00'), date('Y-m-d H:i:s', strtotime('+1 day midnight'))];

        $sentToday = $this->db->table($auditTable)
            ->where('action', 'EMAIL_SENT')
            ->where('created_at >=', $todayStartAt)
            ->where('created_at <', $tomorrowStartAt)
            ->countAllResults();

        return [
            'total_sent' => $totalSent,
            'total_failed' => $totalFailed,
            'sent_today' => $sentToday,
            'success_rate' => $totalSent > 0 ? round(($totalSent / ($totalSent + $totalFailed)) * 100, 2) : 0,
        ];
    }
}
