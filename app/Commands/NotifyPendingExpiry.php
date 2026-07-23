<?php

namespace App\Commands;

use App\Models\NotificationModel;
use App\Services\NotificationService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Notifica gestores e admins sobre pendências próximas do vencimento.
 *
 * Regras de SLA (configuráveis via constantes):
 *   Justificativas: alerta após JUST_WARN_DAYS dias, urgente após JUST_URGENT_DAYS.
 *   Pontos pendentes: alerta quando expires_at < agora + PUNCH_WARN_HOURS horas.
 *
 * Dedup: nunca re-envia a mesma notificação (type + related_entity_id) dentro de 24h.
 *
 * Uso:
 *   php spark notify:pending-expiry
 *   php spark notify:pending-expiry --dry-run
 */
class NotifyPendingExpiry extends BaseCommand
{
    protected $group       = 'Operations';
    protected $name        = 'notify:pending-expiry';
    protected $description = 'Notifica gestores sobre pendências (justificativas/pontos) próximas do vencimento.';
    protected $usage       = 'notify:pending-expiry [--dry-run]';
    protected $options     = [
        '--dry-run' => 'Exibe o que seria enviado sem gravar notificações.',
    ];

    private const JUST_WARN_DAYS    = 5;
    private const JUST_URGENT_DAYS  = 7;
    private const PUNCH_WARN_HOURS  = 24;
    private const DEDUP_HOURS       = 23;

    public function run(array $params): void
    {
        $dryRun  = CLI::getOption('dry-run') !== null;
        $db      = Database::connect();
        $svc     = new NotificationService();
        $notifMd = new NotificationModel();
        $sent    = 0;

        CLI::write('[notify:pending-expiry] ' . ($dryRun ? '[DRY-RUN] ' : '') . date('Y-m-d H:i:s'), 'cyan');

        // ── 1. Justificativas pendentes ────────────────────────────────────────
        $justRows = $db->query("
            SELECT j.id, j.employee_id, j.justification_date, j.created_at,
                   e.name AS emp_name,
                   EXTRACT(DAY FROM NOW() - j.created_at)::int AS days_pending
            FROM justifications j
            JOIN employees e ON e.id = j.employee_id
            WHERE j.status = 'pendente'
              AND j.deleted_at IS NULL
              AND e.role != 'admin'
            ORDER BY j.created_at ASC
        ")->getResultArray();

        foreach ($justRows as $row) {
            $days = (int) $row['days_pending'];

            if ($days < self::JUST_WARN_DAYS) {
                continue;
            }

            $isUrgent  = $days >= self::JUST_URGENT_DAYS;
            $notifType = $isUrgent ? 'pending_expiry_urgent' : 'pending_expiry_warning';
            $priority  = $isUrgent ? 'high' : 'normal';

            if ($this->alreadySent($notifMd, $notifType, 'justification', (int) $row['id'])) {
                CLI::write("  [skip] justification #{$row['id']} ({$row['emp_name']}) já notificado recentemente.", 'dark_gray');
                continue;
            }

            $title   = $isUrgent
                ? "⚠ Justificativa urgente: {$days} dias sem revisão"
                : "Justificativa pendente há {$days} dias";
            $message = "A justificativa de {$row['emp_name']} (data: "
                . date('d/m/Y', strtotime($row['justification_date']))
                . ") aguarda revisão há {$days} dia(s).";
            $link    = '/justifications/' . $row['id'];

            CLI::write("  [justification #{$row['id']}] {$row['emp_name']} — {$days}d — " . ($isUrgent ? 'URGENTE' : 'AVISO'), $isUrgent ? 'red' : 'yellow');

            if (!$dryRun) {
                $count = $svc->notifyManagers($title, $message, $notifType, $link);
                $this->stampDedup($notifMd, $notifType, 'justification', (int) $row['id'], $priority);
                $sent += $count;
            }
        }

        // ── 2. Pontos pendentes com expires_at próximo ─────────────────────────
        $punchRows = $db->query("
            SELECT pp.id, pp.employee_id, pp.intended_time, pp.expires_at,
                   e.name AS emp_name,
                   EXTRACT(EPOCH FROM (pp.expires_at - NOW())) / 3600 AS hours_left
            FROM pending_punches pp
            JOIN employees e ON e.id = pp.employee_id
            WHERE pp.status = 'pending'
              AND pp.expires_at IS NOT NULL
              AND pp.expires_at > NOW()
              AND pp.expires_at <= NOW() + INTERVAL '" . self::PUNCH_WARN_HOURS . " hours'
              AND e.role != 'admin'
            ORDER BY pp.expires_at ASC
        ")->getResultArray();

        foreach ($punchRows as $row) {
            $hoursLeft = round((float) $row['hours_left'], 1);
            $notifType = 'pending_punch_expiry';

            if ($this->alreadySent($notifMd, $notifType, 'pending_punch', (int) $row['id'])) {
                CLI::write("  [skip] pending_punch #{$row['id']} já notificado.", 'dark_gray');
                continue;
            }

            $title   = "Ponto pendente expira em {$hoursLeft}h";
            $message = "O ponto pendente de {$row['emp_name']} ("
                . date('d/m/Y H:i', strtotime($row['intended_time']))
                . ") vence em {$hoursLeft} hora(s) e será automaticamente descartado.";
            $link    = '/manager/pending-punches';

            CLI::write("  [punch #{$row['id']}] {$row['emp_name']} — {$hoursLeft}h restantes", 'yellow');

            if (!$dryRun) {
                $count = $svc->notifyManagers($title, $message, $notifType, $link);
                $this->stampDedup($notifMd, $notifType, 'pending_punch', (int) $row['id'], 'high');
                $sent += $count;
            }
        }

        // ── 3. Resumo ──────────────────────────────────────────────────────────
        $totalJust  = count($justRows);
        $warnJust   = count(array_filter($justRows, fn($r) => (int)$r['days_pending'] >= self::JUST_WARN_DAYS && (int)$r['days_pending'] < self::JUST_URGENT_DAYS));
        $urgentJust = count(array_filter($justRows, fn($r) => (int)$r['days_pending'] >= self::JUST_URGENT_DAYS));
        $expiringPunches = count($punchRows);

        CLI::write('');
        CLI::write("Justificativas pendentes totais : {$totalJust}", 'white');
        CLI::write("  → em aviso (≥" . self::JUST_WARN_DAYS . "d)  : {$warnJust}", 'yellow');
        CLI::write("  → urgentes (≥" . self::JUST_URGENT_DAYS . "d) : {$urgentJust}", 'red');
        CLI::write("Pontos expirando em <" . self::PUNCH_WARN_HOURS . "h : {$expiringPunches}", 'white');
        CLI::write("Notificações enviadas           : " . ($dryRun ? '0 (dry-run)' : $sent), 'green');
    }

    /**
     * Verifica se já foi enviada notificação igual nas últimas DEDUP_HOURS horas.
     * Busca pelo registro sentinela de dedup (type = $type . '_dedup_stamp').
     */
    private function alreadySent(NotificationModel $model, string $type, string $entityType, int $entityId): bool
    {
        $db = Database::connect();
        $count = $db->table('notifications')
            ->where('type', $type . '_dedup_stamp')
            ->where('related_entity_type', $entityType)
            ->where('related_entity_id', $entityId)
            ->where('created_at >=', date('Y-m-d H:i:s', strtotime('-' . self::DEDUP_HOURS . ' hours')))
            ->countAllResults();

        return $count > 0;
    }

    /**
     * Grava uma notificação sentinela de dedup (para o employee_id 0 = sistema).
     * Usamos a própria tabela notifications com user_id = 0 como marcador.
     * Caso user_id 0 não seja permitido pela FK, usamos o menor admin_id disponível.
     */
    private function stampDedup(NotificationModel $model, string $type, string $entityType, int $entityId, string $priority): void
    {
        $db = Database::connect();

        // Pega o primeiro admin para usar como receptor do marcador de dedup
        $adminRow = $db->table('employees')->where('role', 'admin')->select('id')->limit(1)->get()->getRowArray();
        $adminId  = $adminRow ? (int) $adminRow['id'] : 1;

        $db->table('notifications')->insert([
            'user_id'             => $adminId,
            'type'                => $type . '_dedup_stamp',
            'title'               => '_dedup_' . $entityType . '_' . $entityId,
            'message'             => '',
            'related_entity_type' => $entityType,
            'related_entity_id'   => $entityId,
            'priority'            => $priority,
            'read'                => true,
            'created_at'          => date('Y-m-d H:i:s'),
        ]);
    }
}
