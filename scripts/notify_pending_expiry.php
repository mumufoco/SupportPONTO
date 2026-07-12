<?php
/**
 * notify_pending_expiry.php
 *
 * Notifica gestores/admins sobre pendências próximas do vencimento.
 * Usa PDO direto para evitar conflito com Events.php (ob_start).
 *
 * Cron (8h diário):
 *   0 8 * * * /usr/bin/php /www/wwwroot/ponto.supportsondagens.com.br/scripts/notify_pending_expiry.php >> /www/wwwroot/ponto.supportsondagens.com.br/writable/logs/notify_expiry.log 2>&1
 *
 * Teste: php notify_pending_expiry.php --dry-run
 */

declare(strict_types=1);

const JUST_WARN_DAYS   = 5;
const JUST_URGENT_DAYS = 7;
const PUNCH_WARN_HOURS = 24;
const DEDUP_HOURS      = 23;

$dryRun = in_array('--dry-run', $argv ?? [], true);

// ── Conexão ───────────────────────────────────────────────────────────────────
try {
    $pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=supportponto', 'postgres', '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    out('ERRO: Conexão com banco falhou: ' . $e->getMessage());
    exit(1);
}

out('=== notify:pending-expiry ' . date('Y-m-d H:i:s') . ($dryRun ? ' [DRY-RUN]' : '') . ' ===');

// ── Gestores/admins ativos ────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT id FROM employees WHERE role IN ('admin','gestor','rh') AND deleted_at IS NULL");
$stmt->execute();
$managerIds = array_column($stmt->fetchAll(), 'id');

if (empty($managerIds)) {
    out('Nenhum gestor/admin ativo. Encerrando.');
    exit(0);
}

out('Gestores para notificar: ' . count($managerIds) . ' (ids: ' . implode(', ', $managerIds) . ')');

$sent = 0;

// ── 1. Justificativas pendentes ───────────────────────────────────────────────
$stmt = $pdo->query("
    SELECT j.id, j.employee_id, j.justification_date, j.created_at,
           e.name AS emp_name,
           EXTRACT(DAY FROM NOW() - j.created_at)::int AS days_pending
    FROM justifications j
    JOIN employees e ON e.id = j.employee_id
    WHERE j.status = 'pendente'
      AND j.deleted_at IS NULL
    ORDER BY j.created_at ASC
");
$justRows = $stmt->fetchAll();
out('Justificativas pendentes: ' . count($justRows));

foreach ($justRows as $row) {
    $days = (int) $row['days_pending'];
    if ($days < JUST_WARN_DAYS) {
        continue;
    }

    $isUrgent  = $days >= JUST_URGENT_DAYS;
    $notifType = $isUrgent ? 'pending_expiry_urgent' : 'pending_expiry_warning';
    $priority  = $isUrgent ? 'high' : 'normal';

    if (alreadySent($pdo, $notifType, 'justification', (int) $row['id'])) {
        out("  [skip] justification #{$row['id']} ({$row['emp_name']}) já notificado.");
        continue;
    }

    $title   = $isUrgent
        ? "Justificativa urgente: {$days} dias sem revisão"
        : "Justificativa pendente há {$days} dias";
    $message = "A justificativa de {$row['emp_name']} (data: "
        . date('d/m/Y', strtotime($row['justification_date']))
        . ") aguarda revisão há {$days} dia(s). Acesse o sistema para aprovar ou rejeitar.";

    out("  [{$notifType}] #{$row['id']} {$row['emp_name']} — {$days}d" . ($isUrgent ? ' *** URGENTE ***' : ''));

    if (!$dryRun) {
        foreach ($managerIds as $mid) {
            insertNotification($pdo, (int)$mid, $notifType, $title, $message, '/justifications/' . $row['id'],
                'bi bi-clipboard-check', $priority, 'justification', (int)$row['id']);
            $sent++;
        }
        stampDedup($pdo, $notifType, 'justification', (int)$row['id'], $priority, (int)$managerIds[0]);
    }
}

// ── 2. Pontos pendentes expirando ─────────────────────────────────────────────
$punchWarnHours = (int) PUNCH_WARN_HOURS;
$stmt = $pdo->query("
    SELECT pp.id, pp.employee_id, pp.intended_time, pp.expires_at,
           e.name AS emp_name,
           ROUND(EXTRACT(EPOCH FROM (pp.expires_at - NOW())) / 3600, 1) AS hours_left
    FROM pending_punches pp
    JOIN employees e ON e.id = pp.employee_id
    WHERE pp.status = 'pending'
      AND pp.expires_at IS NOT NULL
      AND pp.expires_at > NOW()
      AND pp.expires_at <= NOW() + INTERVAL '{$punchWarnHours} hours'
    ORDER BY pp.expires_at ASC
");
$punchRows = $stmt->fetchAll();
out('Pontos expirando em <' . PUNCH_WARN_HOURS . 'h: ' . count($punchRows));

foreach ($punchRows as $row) {
    $hoursLeft = round((float)$row['hours_left'], 1);
    $notifType = 'pending_punch_expiry';

    if (alreadySent($pdo, $notifType, 'pending_punch', (int)$row['id'])) {
        out("  [skip] punch #{$row['id']} já notificado.");
        continue;
    }

    $title   = "Ponto pendente expira em {$hoursLeft}h";
    $message = "O ponto pendente de {$row['emp_name']} ("
        . date('d/m/Y H:i', strtotime($row['intended_time']))
        . ") vence em {$hoursLeft} hora(s) e será descartado automaticamente.";

    out("  [punch_expiry] #{$row['id']} {$row['emp_name']} — {$hoursLeft}h restantes *** URGENTE ***");

    if (!$dryRun) {
        foreach ($managerIds as $mid) {
            insertNotification($pdo, (int)$mid, $notifType, $title, $message, '/manager/pending-punches',
                'bi bi-clock-history', 'high', 'pending_punch', (int)$row['id']);
            $sent++;
        }
        stampDedup($pdo, $notifType, 'pending_punch', (int)$row['id'], 'high', (int)$managerIds[0]);
    }
}

// ── Resumo ────────────────────────────────────────────────────────────────────
$warnJust   = count(array_filter($justRows, fn($r) => (int)$r['days_pending'] >= JUST_WARN_DAYS && (int)$r['days_pending'] < JUST_URGENT_DAYS));
$urgentJust = count(array_filter($justRows, fn($r) => (int)$r['days_pending'] >= JUST_URGENT_DAYS));

out('');
out("Justificativas em aviso (>=" . JUST_WARN_DAYS . "d)  : {$warnJust}");
out("Justificativas urgentes (>=" . JUST_URGENT_DAYS . "d) : {$urgentJust}");
out("Pontos expirando em <" . PUNCH_WARN_HOURS . "h       : " . count($punchRows));
out("Notificações inseridas             : " . ($dryRun ? '0 (dry-run)' : $sent));
out('=== Concluído ===');
exit(0);

// ── Funções ───────────────────────────────────────────────────────────────────

function insertNotification(PDO $pdo, int $userId, string $type, string $title, string $message,
    string $link, string $icon, string $priority, string $entityType, int $entityId): void
{
    $stmt = $pdo->prepare("
        INSERT INTO notifications
            (user_id, type, title, message, link, icon, priority, read, related_entity_type, related_entity_id, created_at)
        VALUES
            (:uid, :type, :title, :msg, :link, :icon, :priority, false, :etype, :eid, NOW())
    ");
    $stmt->execute([
        ':uid'      => $userId,
        ':type'     => $type,
        ':title'    => $title,
        ':msg'      => $message,
        ':link'     => $link,
        ':icon'     => $icon,
        ':priority' => $priority,
        ':etype'    => $entityType,
        ':eid'      => $entityId,
    ]);
}

function stampDedup(PDO $pdo, string $type, string $entityType, int $entityId, string $priority, int $userId): void
{
    $stmt = $pdo->prepare("
        INSERT INTO notifications
            (user_id, type, title, message, related_entity_type, related_entity_id, priority, read, created_at)
        VALUES
            (:uid, :type, :title, '', :etype, :eid, :priority, true, NOW())
    ");
    $stmt->execute([
        ':uid'      => $userId,
        ':type'     => $type . '_dedup_stamp',
        ':title'    => '_dedup_' . $entityType . '_' . $entityId,
        ':etype'    => $entityType,
        ':eid'      => $entityId,
        ':priority' => $priority,
    ]);
}

function alreadySent(PDO $pdo, string $type, string $entityType, int $entityId): bool
{
    $hours = (int) DEDUP_HOURS;
    $stmt  = $pdo->prepare("
        SELECT COUNT(*) FROM notifications
        WHERE type = :type
          AND related_entity_type = :etype
          AND related_entity_id = :eid
          AND created_at >= NOW() - INTERVAL '{$hours} hours'
    ");
    $stmt->execute([
        ':type'  => $type . '_dedup_stamp',
        ':etype' => $entityType,
        ':eid'   => $entityId,
    ]);
    return (int)$stmt->fetchColumn() > 0;
}

function out(string $msg): void
{
    echo '[' . date('H:i:s') . '] ' . $msg . PHP_EOL;
}
