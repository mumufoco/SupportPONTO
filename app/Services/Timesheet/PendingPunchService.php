<?php

namespace App\Services\Timesheet;

use App\DTO\Timesheet\PunchRegistrationCommand;
use App\Models\EmployeeModel;
use App\Models\PendingPunchModel;
use App\Models\TimePunchModel;
use App\Services\Audit\CanonicalAuditLogger;
use App\Services\AuthorizationService;
use App\Services\Monitoring\SystemObservabilityService;
use CodeIgniter\HTTP\RequestInterface;
use Config\Services;

/**
 * Gerencia o ciclo de vida de registros de ponto pendentes de aprovação
 * gerados quando todos os métodos automáticos falharam tecnicamente.
 */
class PendingPunchService
{
    private PendingPunchModel      $pendingModel;
    private TimePunchModel         $timePunchModel;
    private PunchFailureDetector   $failureDetector;
    private CanonicalAuditLogger   $auditLogger;
    private EmployeeModel          $employeeModel;
    private AuthorizationService   $authorizationService;

    public function __construct()
    {
        $this->pendingModel         = new PendingPunchModel();
        $this->timePunchModel       = new TimePunchModel();
        $this->failureDetector      = new PunchFailureDetector();
        $this->auditLogger          = new CanonicalAuditLogger();
        $this->employeeModel        = new EmployeeModel();
        $this->authorizationService = new AuthorizationService();
    }

    /**
     * Avalia se é possível abrir justificativa e retorna o estado.
     */
    public function evaluateEligibility(
        int    $employeeId,
        array  $attemptLog,
        array  $enabledMethods
    ): array {

        // Auto-expira registros vencidos antes de contabilizar a cota mensal
        // (mesma lógica de auto-cura aplicada em listPendingForManager — evita
        // que registros travados em 'pending' bloqueiem novas submissões).
        $this->pendingModel->expireStale(24);

        // Contar pendências abertas neste mês
        $monthCount = $this->pendingModel->monthlyCountByEmployee(
            $employeeId,
            date('Y-m')
        );

        $maxPerMonth = (int) (new \App\Models\SettingModel())->get('max_pending_punches_per_month', 3);
        $result      = $this->failureDetector->evaluate($attemptLog, $enabledMethods, $monthCount, $maxPerMonth);

        return [
            'eligible'        => $result->justified,
            'blocked'         => $result->blocked,
            'block_reason'    => $result->blockReason,
            'failure_summary' => $result->failureSummary,
            'methods_tried'   => array_keys($result->methodsAttempted),
            'technical_failures' => count($result->technicalFailures),
            'monthly_used'    => $monthCount,
            'monthly_limit'   => $maxPerMonth,
        ];
    }

    /**
     * Submete a justificativa e cria o pending_punch.
     */
    public function submit(
        int             $employeeId,
        string          $punchType,
        string          $justificationText,
        string          $situationType,
        array           $attemptLog,
        array           $enabledMethods,
        RequestInterface $request
    ): array {

        // Re-verificar elegibilidade (defesa contra chamada direta sem verificação prévia)
        $eligibility = $this->evaluateEligibility($employeeId, $attemptLog, $enabledMethods);

        if (!$eligibility['eligible']) {
            return ['success' => false, 'message' => $eligibility['block_reason'], 'status' => 403];
        }

        // Defesa contra duplo-clique / duplo-submit: bloqueia se já existe uma
        // pendência ABERTA idêntica (mesmo colaborador, tipo de batida e horário
        // pretendido). Sem essa checagem, cliques duplos no botão de envio criam
        // dois registros idênticos no painel do gestor.
        $duplicate = $this->pendingModel
            ->where('employee_id', $employeeId)
            ->where('intended_punch_type', $punchType)
            ->where('intended_time', date('Y-m-d H:i:s'))
            ->where('status', 'pending')
            ->first();

        if ($duplicate) {
            return [
                'success' => false,
                'message' => 'Você já enviou uma justificativa para esta batida. Aguarde a análise do seu gestor.',
                'status'  => 409,
            ];
        }

        // Coletar evidências técnicas do sistema no momento da submissão
        try {
            $observability   = new SystemObservabilityService();
            $systemHealth    = $observability->publicHealth();
        } catch (\Throwable) {
            $systemHealth = ['status' => 'unknown'];
        }

        $evidencePackage = [
            'attempt_log'            => $attemptLog,
            'system_health_snapshot' => $systemHealth,
            'ip_address'             => $request->getIPAddress(),
            'user_agent'             => (string) $request->getUserAgent(),
            'terminal_id'            => $request->getHeaderLine('X-Terminal-Id') ?: null,
            'submitted_at'           => date('Y-m-d H:i:s'),
            'methods_available'      => $enabledMethods,
            'technical_failures'     => $eligibility['technical_failures'],
        ];

        $geolat = $request->getPost('geolocation_lat') ?: $request->getJsonVar('geolocation_lat');
        $geolng = $request->getPost('geolocation_lng') ?: $request->getJsonVar('geolocation_lng');

        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $data = [
            'employee_id'              => $employeeId,
            'intended_punch_type'      => $punchType,
            'intended_time'            => date('Y-m-d H:i:s'),
            'justification_text'       => $justificationText,
            'situation_type'           => $situationType,
            'evidence_package'         => json_encode($evidencePackage, JSON_UNESCAPED_UNICODE),
            'methods_attempted'        => json_encode(array_column($attemptLog, 'error_code', 'method'), JSON_UNESCAPED_UNICODE),
            'technical_failures_count' => $eligibility['technical_failures'],
            'ip_address'               => $request->getIPAddress(),
            'user_agent'               => (string) $request->getUserAgent(),
            'geolocation_lat'          => is_numeric($geolat) ? (float) $geolat : null,
            'geolocation_lng'          => is_numeric($geolng) ? (float) $geolng : null,
            'status'                   => 'pending',
            'expires_at'               => $expiresAt,
        ];

        if (!$this->pendingModel->insert($data)) {
            return ['success' => false, 'message' => 'Erro ao registrar justificativa. Tente novamente.', 'status' => 500];
        }

        $pendingId = $this->pendingModel->getInsertID();

        $this->auditLogger->logEntityEvent(
            $employeeId,
            'PENDING_PUNCH_SUBMITTED',
            'pending_punches',
            (int) $pendingId,
            null,
            ['punch_type' => $punchType, 'situation' => $situationType, 'failures' => $eligibility['technical_failures']],
            "Justificativa de ponto enviada por falha técnica ({$eligibility['technical_failures']} método(s) falharam)",
            'warning'
        );

        return [
            'success'    => true,
            'pending_id' => $pendingId,
            'message'    => 'Justificativa enviada. Aguarde aprovação do seu gestor (prazo: 24 horas).',
            'expires_at' => $expiresAt,
            'status'     => 202,
        ];
    }

    /**
     * Colaborador preenche a justificativa de uma pendência que o próprio SISTEMA
     * gerou (via cron punches:close-incomplete-days) ao detectar, na virada do dia,
     * um par de marcações incompleto (ex.: início do intervalo sem o fim). A
     * pendência já existe com status 'awaiting_employee' e um texto-placeholder;
     * esta ação apenas substitui o texto pelo relato do colaborador e move o
     * registro para 'pending', entrando na mesma fila que o gestor já usa para
     * aprovar/rejeitar justificativas de falha técnica.
     */
    public function submitEmployeeResponseForSystemPending(
        int              $pendingId,
        int              $employeeId,
        string           $justificationText,
        string           $situationType,
        RequestInterface $request
    ): array {
        $pending = $this->pendingModel->find($pendingId);

        if (!$pending) {
            return ['success' => false, 'message' => 'Pendência não encontrada.', 'status' => 404];
        }

        if ((int) $pending->employee_id !== $employeeId) {
            return ['success' => false, 'message' => 'Acesso negado.', 'status' => 403];
        }

        if ($pending->status !== 'awaiting_employee') {
            return ['success' => false, 'message' => "Esta pendência já está com status '{$pending->status}'.", 'status' => 409];
        }

        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $updated = $this->pendingModel->update($pendingId, [
            'justification_text' => $justificationText,
            'situation_type'      => $situationType,
            'ip_address'          => $request->getIPAddress(),
            'user_agent'          => (string) $request->getUserAgent(),
            'status'              => 'pending',
            'expires_at'          => $expiresAt,
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);

        if (!$updated) {
            return ['success' => false, 'message' => 'Erro ao registrar justificativa. Tente novamente.', 'status' => 500];
        }

        $this->auditLogger->logEntityEvent(
            $employeeId,
            'PENDING_PUNCH_SUBMITTED',
            'pending_punches',
            $pendingId,
            ['status' => 'awaiting_employee'],
            ['status' => 'pending', 'situation' => $situationType],
            'Justificativa enviada pelo colaborador para pendência gerada pelo sistema (virada de dia)',
            'info'
        );

        return [
            'success'    => true,
            'pending_id' => $pendingId,
            'message'    => 'Justificativa enviada. Aguarde aprovação do seu gestor (prazo: 24 horas).',
            'expires_at' => $expiresAt,
            'status'     => 202,
        ];
    }

    /**
     * Cria uma pendência a partir de uma marcação capturada offline (PWA) cuja
     * sincronização esbarrou em conflito de sequência (ex.: o colaborador já
     * registrou o ponto por outro canal enquanto o dispositivo estava sem
     * conexão). Diferente de submit() — feito para falhas técnicas detectadas
     * enquanto o colaborador está ONLINE, com attemptLog da sessão — este
     * caminho não depende de PendingPunchAttemptStore: a "evidência" é o
     * próprio horário original capturado no dispositivo e a foto/localização
     * enviadas junto. Preserva intended_time = horário real da captura, não o
     * horário da sincronização.
     */
    public function submitFromOfflineSync(
        int $employeeId,
        string $punchType,
        string $intendedTime,
        string $method,
        ?string $photo,
        ?float $lat,
        ?float $lng,
        string $clientUuid,
        RequestInterface $request
    ): array {
        $duplicate = $this->pendingModel->findByClientUuid($clientUuid);
        if ($duplicate) {
            return [
                'success' => true,
                'message' => 'Esta marcação já está pendente de aprovação do gestor.',
                'status'  => 202,
                'data'    => ['pending_id' => $duplicate->id, 'pending_review' => true, 'already_processed' => true],
            ];
        }

        $evidencePackage = [
            'offline_sync'  => true,
            'method'        => $method,
            'has_photo'     => $photo !== null && $photo !== '',
            'ip_address'    => $request->getIPAddress(),
            'user_agent'    => (string) $request->getUserAgent(),
            'synced_at'     => date('Y-m-d H:i:s'),
        ];

        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $data = [
            'employee_id'         => $employeeId,
            'intended_punch_type' => $punchType,
            'intended_time'       => $intendedTime,
            'justification_text'  => 'Marcação capturada offline (sem conexão com o servidor) e sincronizada automaticamente; o horário original foi preservado, mas a sequência de marcações não pôde ser confirmada em tempo real.',
            'situation_type'      => 'offline_sync_conflict',
            'evidence_package'    => json_encode($evidencePackage, JSON_UNESCAPED_UNICODE),
            'ip_address'          => $request->getIPAddress(),
            'user_agent'          => (string) $request->getUserAgent(),
            'geolocation_lat'     => $lat,
            'geolocation_lng'     => $lng,
            'status'              => 'pending',
            'expires_at'          => $expiresAt,
            'client_uuid'         => $clientUuid,
        ];

        if (!$this->pendingModel->insert($data)) {
            return ['success' => false, 'message' => 'Erro ao registrar marcação offline pendente. Tente novamente.', 'status' => 500];
        }

        $pendingId = $this->pendingModel->getInsertID();

        $this->auditLogger->logEntityEvent(
            $employeeId,
            'PENDING_PUNCH_SUBMITTED',
            'pending_punches',
            (int) $pendingId,
            null,
            ['punch_type' => $punchType, 'situation' => 'offline_sync_conflict', 'method' => $method],
            'Marcação offline sincronizada com conflito de sequência — pendente de aprovação do gestor',
            'warning'
        );

        return [
            'success' => true,
            'message' => 'Marcação sincronizada, mas ficou pendente de aprovação do gestor devido a um conflito de sequência.',
            'status'  => 202,
            'data'    => ['pending_id' => $pendingId, 'pending_review' => true, 'expires_at' => $expiresAt],
        ];
    }

    /**
     * Gestor/RH aprova o registro pendente e efetiva o ponto.
     */
    public function approve(int $pendingId, object|array $reviewer, string $reviewNotes = ''): array
    {
        $reviewerId = (int) ($this->extractActorValue($reviewer, 'id') ?? 0);

        if ($reviewerId <= 0) {
            return ['success' => false, 'message' => 'Revisor inválido.', 'status' => 400];
        }

        // ALTO-08 (auditoria): trava este pendingId específico antes de reler seu
        // status — serializa aprovações/rejeições concorrentes da mesma solicitação
        // (dois gestores, duplo clique) para que a segunda chamada só prossiga depois
        // que a primeira já tenha persistido 'approved'/'rejected', e então seja
        // corretamente barrada pela checagem de status abaixo em vez de duplicar o
        // ponto efetivo.
        $db = \Config\Database::connect();
        $db->transStart();
        (new PendingPunchConcurrencyService($db))->lockPendingPunch($pendingId);

        $pending = $this->pendingModel->find($pendingId);

        if (!$pending) {
            $db->transComplete();
            return ['success' => false, 'message' => 'Registro pendente não encontrado.', 'status' => 404];
        }

        if ($pending->status !== 'pending') {
            $db->transComplete();
            return ['success' => false, 'message' => "Registro já está com status '{$pending->status}'.", 'status' => 409];
        }

        $scopeCheck = $this->assertReviewerScope($reviewer, $pending);
        if (!$scopeCheck['success']) {
            $db->transComplete();
            return $scopeCheck;
        }

        if ($pending->expires_at && strtotime($pending->expires_at) < time()) {
            $this->pendingModel->update($pendingId, ['status' => 'expired', 'updated_at' => date('Y-m-d H:i:s')]);
            $db->transComplete();
            return ['success' => false, 'message' => 'Prazo de aprovação expirado.', 'status' => 410];
        }

        // Criar o punch efetivo com method = 'manual_gestor'
        $punchData = [
            'employee_id'  => $pending->employee_id,
            'punch_time'   => $pending->intended_time,
            'punch_type'   => $pending->intended_punch_type,
            'method'       => 'manual_gestor',
            'notes'        => "Aprovado por gestor ID#{$reviewerId}. " . ($reviewNotes ? "Nota: {$reviewNotes}" : ''),
            'ip_address'   => $pending->ip_address,
        ];

        if (isset($pending->geolocation_lat) && $pending->geolocation_lat !== null) {
            $punchData['location_lat'] = $pending->geolocation_lat;
            $punchData['location_lng'] = $pending->geolocation_lng;
        }

        try {
            $registration = (new TimesheetPunchRegistrationService())->register(new PunchRegistrationCommand(
                employeeId: (int) $pending->employee_id,
                punchType: (string) $pending->intended_punch_type,
                method: 'manual_gestor',
                punchTime: (string) $pending->intended_time,
                latitude: $pending->geolocation_lat ?? null,
                longitude: $pending->geolocation_lng ?? null,
                ipAddress: $pending->ip_address ?? null,
                additionalData: [
                    'notes' => "Aprovado por gestor ID#{$reviewerId}. " . ($reviewNotes ? "Nota: {$reviewNotes}" : ''),
                ],
                context: ['pending_punch_id' => $pendingId, 'reviewer_id' => $reviewerId],
                skipSequenceValidation: true,
                skipCooldownValidation: true,
                requireGeofenceConfirmation: false,
                source: 'pending_approval',
            ));

            if (! ($registration['success'] ?? false)) {
                log_message('error', 'PendingPunchService::approve canonical registration failed: ' . ($registration['message'] ?? 'unknown'));
                $db->transRollback();
                return ['success' => false, 'message' => $registration['message'] ?? 'Falha ao criar registro de ponto.', 'status' => (int) ($registration['status'] ?? 500)];
            }

            $finalPunchId = (int) ($registration['data']['punch_id'] ?? $registration['data']['id'] ?? 0);
        } catch (\Throwable $e) {
            log_message('error', 'PendingPunchService::approve failed to create final punch: ' . $e->getMessage());
            $db->transRollback();
            return ['success' => false, 'message' => 'Falha ao criar registro de ponto.', 'status' => 500];
        }

        $this->pendingModel->update($pendingId, [
            'status'         => 'approved',
            'reviewed_by'    => $reviewerId,
            'reviewed_at'    => date('Y-m-d H:i:s'),
            'review_notes'   => $reviewNotes,
            'final_punch_id' => $finalPunchId,
            'processed_at'   => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        // Só libera o lock consultivo (commit) depois que TANTO o ponto efetivo quanto
        // a atualização de status da pendência estão persistidos — fecha a janela onde
        // uma segunda aprovação concorrente poderia ver o ponto já criado mas o status
        // ainda 'pending'.
        $db->transComplete();

        $this->auditLogger->logEntityEvent(
            $reviewerId,
            'PENDING_PUNCH_APPROVED',
            'pending_punches',
            $pendingId,
            ['status' => 'pending'],
            ['status' => 'approved', 'final_punch_id' => $finalPunchId],
            "Justificativa de ponto aprovada. Ponto efetivado: ID#{$finalPunchId}",
            'info'
        );

        return ['success' => true, 'final_punch_id' => $finalPunchId, 'message' => 'Ponto aprovado e efetivado.', 'status' => 200];
    }

    /**
     * Gestor/RH rejeita o registro pendente.
     */
    public function reject(int $pendingId, object|array $reviewer, string $reviewNotes): array
    {
        $reviewerId = (int) ($this->extractActorValue($reviewer, 'id') ?? 0);

        if ($reviewerId <= 0) {
            return ['success' => false, 'message' => 'Revisor inválido.', 'status' => 400];
        }

        if (trim($reviewNotes) === '') {
            return ['success' => false, 'message' => 'É obrigatório informar o motivo da rejeição.', 'status' => 400];
        }

        // ALTO-08 (auditoria): mesmo lock consultivo usado em approve() — evita que
        // uma aprovação e uma rejeição concorrentes da mesma pendência corram juntas.
        $db = \Config\Database::connect();
        $db->transStart();
        (new PendingPunchConcurrencyService($db))->lockPendingPunch($pendingId);

        $pending = $this->pendingModel->find($pendingId);

        if (!$pending) {
            $db->transComplete();
            return ['success' => false, 'message' => 'Registro pendente não encontrado.', 'status' => 404];
        }

        if ($pending->status !== 'pending') {
            $db->transComplete();
            return ['success' => false, 'message' => "Registro já está com status '{$pending->status}'.", 'status' => 409];
        }

        $scopeCheck = $this->assertReviewerScope($reviewer, $pending);
        if (!$scopeCheck['success']) {
            $db->transComplete();
            return $scopeCheck;
        }

        $this->pendingModel->update($pendingId, [
            'status'       => 'rejected',
            'reviewed_by'  => $reviewerId,
            'reviewed_at'  => date('Y-m-d H:i:s'),
            'review_notes' => $reviewNotes,
            'processed_at' => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);
        $db->transComplete();

        $this->auditLogger->logEntityEvent(
            $reviewerId,
            'PENDING_PUNCH_REJECTED',
            'pending_punches',
            $pendingId,
            ['status' => 'pending'],
            ['status' => 'rejected', 'reason' => $reviewNotes],
            "Justificativa de ponto rejeitada. Motivo: {$reviewNotes}",
            'warning'
        );

        return ['success' => true, 'message' => 'Registro rejeitado.', 'status' => 200];
    }

    /** Lista pendências para gestor por departamento e para admin/RH em escopo global. */
    public function listPendingForManager(?string $department = null): array
    {
        // Auto-expira registros vencidos antes de listar — não há cron/command
        // configurado para isso, então sem essa chamada lazy os registros
        // expirados ficam presos com status='pending' (somem da lista do gestor
        // mas continuam contando contra o limite mensal do colaborador).
        $this->pendingModel->expireStale(24);

        return $this->pendingModel->getPending($department);
    }

    private function assertReviewerScope(object|array $reviewer, object $pending): array
    {
        $reviewerRole = $this->authorizationService->getRole($reviewer);

        if (in_array($reviewerRole, ['admin', 'rh'], true)) {
            return ['success' => true];
        }

        if ($reviewerRole !== 'gestor') {
            return ['success' => false, 'message' => 'Acesso negado.', 'status' => 403];
        }

        $employee = $this->employeeModel->find((int) ($pending->employee_id ?? 0));
        if (!$employee) {
            return ['success' => false, 'message' => 'Colaborador da pendência não encontrado.', 'status' => 404];
        }

        if (!$this->authorizationService->belongsToSameDepartment($reviewer, $employee)) {
            return ['success' => false, 'message' => 'Você só pode revisar pendências do seu departamento.', 'status' => 403];
        }

        return ['success' => true];
    }

    private function extractActorValue(object|array $actor, string $field): mixed
    {
        if (is_object($actor)) {
            return $actor->{$field} ?? null;
        }

        return $actor[$field] ?? null;
    }

    /** Expira registros pendentes antigos (chamar via cron) */
    public function expireStale(): int
    {
        return $this->pendingModel->expireStale(24);
    }
}

