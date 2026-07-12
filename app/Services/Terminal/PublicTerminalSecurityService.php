<?php

namespace App\Services\Terminal;

use App\Models\PunchTerminalDeviceModel;
use App\Models\PunchTerminalNonceModel;
use App\Models\AuditModel;
use CodeIgniter\HTTP\RequestInterface;

/**
 * Hardening dos endpoints públicos de terminal/kiosk.
 *
 * Fase 11:
 * - substitui confiança apenas em IP por terminal_id + secret + fingerprint;
 * - bloqueia replay por nonce persistido;
 * - aplica rate limit por terminal/IP;
 * - mantém fallback legado apenas quando explicitamente liberado.
 */
class PublicTerminalSecurityService
{
    private const TOKEN_TTL_SECONDS = 300;
    private const CLOCK_SKEW_SECONDS = 30;
    private const MAX_REQUESTS_PER_MINUTE = 20;

    public function __construct(
        private readonly PunchTerminalDeviceModel $terminalModel = new PunchTerminalDeviceModel(),
        private readonly PunchTerminalNonceModel $nonceModel = new PunchTerminalNonceModel(),
        private readonly AuditModel $auditModel = new AuditModel(),
    ) {
    }

    public function issueToken(RequestInterface $request): array
    {
        $terminal = $this->resolveTerminalFromRequest($request, requireSecret: true);
        if (! ($terminal['success'] ?? false)) {
            return $terminal;
        }

        $terminalRecord = $terminal['terminal'];
        $terminalId = (string) $terminalRecord->terminal_id;
        $timestamp = time();
        $nonce = bin2hex(random_bytes(16));
        $deviceFingerprint = $this->resolveDeviceFingerprint($request);
        $payload = implode('|', ['v2', $timestamp, $terminalId, $deviceFingerprint, $nonce]);
        $signature = hash_hmac('sha256', $payload, $this->terminalSecretMaterial($terminalRecord));

        $this->rememberNonce($terminalId, $nonce, 'kiosk_token_issue', $request->getIPAddress(), $timestamp + self::TOKEN_TTL_SECONDS);
        $this->markSeen($terminalRecord, $request);

        return [
            'success' => true,
            'status' => 200,
            'message' => 'Token de terminal gerado com segurança.',
            'data' => [
                'kiosk_token' => implode('.', ['v2', $timestamp, rawurlencode($terminalId), $deviceFingerprint, $nonce, $signature]),
                'terminal_id' => $terminalId,
                'expires_at' => date('Y-m-d H:i:s', $timestamp + self::TOKEN_TTL_SECONDS),
                'ttl_seconds' => self::TOKEN_TTL_SECONDS,
            ],
        ];
    }

    public function validateKioskToken(?string $token, RequestInterface $request): bool
    {
        $result = $this->validateKioskTokenDetailed($token, $request);
        return (bool) ($result['success'] ?? false);
    }

    public function validateKioskTokenDetailed(?string $token, RequestInterface $request): array
    {
        if (! $this->rateLimit($request)) {
            return $this->deny('terminal_rate_limited', 'Muitas requisições neste terminal.', $request, 429);
        }

        if ($token === null || trim($token) === '') {
            return $this->deny('terminal_token_missing', 'Token de terminal ausente.', $request, 403);
        }

        $parts = explode('.', trim($token));
        if (count($parts) === 6 && $parts[0] === 'v2') {
            return $this->validateV2Token($parts, $request);
        }

        if ($this->legacyTokensAllowed()) {
            return ['success' => true, 'legacy' => true];
        }

        return $this->deny('terminal_legacy_token_blocked', 'Token legado de terminal bloqueado.', $request, 403);
    }

    private function validateV2Token(array $parts, RequestInterface $request): array
    {
        [, $timestampRaw, $terminalIdRaw, $deviceFingerprint, $nonce, $signature] = $parts;
        $timestamp = (int) $timestampRaw;
        $terminalId = rawurldecode((string) $terminalIdRaw);

        if ($timestamp <= 0 || abs(time() - $timestamp) > (self::TOKEN_TTL_SECONDS + self::CLOCK_SKEW_SECONDS)) {
            return $this->deny('terminal_token_expired', 'Token de terminal expirado.', $request, 403, $terminalId);
        }

        $terminal = $this->terminalModel->findActiveByTerminalId($terminalId);
        if (! $terminal) {
            return $this->deny('terminal_unknown', 'Terminal não registrado ou revogado.', $request, 403, $terminalId);
        }

        if (! $this->ipAllowed((string) ($terminal->allowed_ip ?? ''), $request->getIPAddress())) {
            return $this->deny('terminal_ip_denied', 'IP não autorizado para este terminal.', $request, 403, $terminalId);
        }

        $expectedFingerprint = $this->resolveDeviceFingerprint($request);
        if (! hash_equals($expectedFingerprint, (string) $deviceFingerprint)) {
            return $this->deny('terminal_fingerprint_mismatch', 'Fingerprint do terminal inválido.', $request, 403, $terminalId);
        }

        $payload = implode('|', ['v2', $timestamp, $terminalId, $deviceFingerprint, $nonce]);
        $expectedSignature = hash_hmac('sha256', $payload, $this->terminalSecretMaterial($terminal));
        if (! hash_equals($expectedSignature, (string) $signature)) {
            return $this->deny('terminal_signature_invalid', 'Assinatura do token inválida.', $request, 403, $terminalId);
        }

        $nonceHash = hash('sha256', $terminalId . '|' . $nonce . '|punch');
        if ($this->nonceModel->nonceExists($terminalId, $nonceHash, 'punch')) {
            return $this->deny('terminal_replay_detected', 'Reuso de token detectado.', $request, 409, $terminalId);
        }

        $this->rememberNonce($terminalId, $nonce, 'punch', $request->getIPAddress(), time() + self::TOKEN_TTL_SECONDS);
        $this->markSeen($terminal, $request);

        return ['success' => true, 'terminal_id' => $terminalId];
    }

    private function resolveTerminalFromRequest(RequestInterface $request, bool $requireSecret = false): array
    {
        $terminalId = trim($request->getHeaderLine('X-Terminal-Id') ?: (string) ($request->getGetPost('terminal_id') ?? ''));
        if ($terminalId === '') {
            return $this->deny('terminal_id_missing', 'Identificador do terminal ausente.', $request, 403);
        }

        $terminal = $this->terminalModel->findActiveByTerminalId($terminalId);
        if (! $terminal) {
            return $this->deny('terminal_unknown', 'Terminal não registrado ou revogado.', $request, 403, $terminalId);
        }

        if (! $this->ipAllowed((string) ($terminal->allowed_ip ?? ''), $request->getIPAddress())) {
            return $this->deny('terminal_ip_denied', 'IP não autorizado para este terminal.', $request, 403, $terminalId);
        }

        if ($requireSecret) {
            $secret = $request->getHeaderLine('X-Terminal-Secret') ?: (string) ($request->getGetPost('terminal_secret') ?? '');
            if ($secret === '' || ! password_verify($secret, (string) $terminal->secret_hash)) {
                return $this->deny('terminal_secret_invalid', 'Credencial do terminal inválida.', $request, 403, $terminalId);
            }
        }

        return ['success' => true, 'terminal' => $terminal];
    }

    private function rateLimit(RequestInterface $request): bool
    {
        $terminalId = trim($request->getHeaderLine('X-Terminal-Id') ?: (string) ($request->getGetPost('terminal_id') ?? 'unknown'));
        $key = 'terminal_' . hash('sha256', $terminalId . '|' . $request->getIPAddress());
        return \Config\Services::throttler()->check($key, self::MAX_REQUESTS_PER_MINUTE, MINUTE);
    }

    private function resolveDeviceFingerprint(RequestInterface $request): string
    {
        $provided = trim($request->getHeaderLine('X-Device-Fingerprint') ?: (string) ($request->getGetPost('device_fingerprint') ?? ''));
        if ($provided !== '') {
            return substr(hash('sha256', $provided), 0, 64);
        }

        return substr(hash('sha256', $request->getIPAddress() . '|' . (string) $request->getUserAgent()->getAgentString()), 0, 64);
    }

    private function ipAllowed(string $allowedIpList, string $clientIp): bool
    {
        $allowedIpList = trim($allowedIpList);
        if ($allowedIpList === '') {
            return true;
        }

        $allowed = array_filter(array_map('trim', explode(',', $allowedIpList)));
        if (in_array('*', $allowed, true) && (ENVIRONMENT ?? 'production') !== 'production') {
            return true;
        }

        return in_array($clientIp, $allowed, true);
    }

    private function terminalSecretMaterial(object $terminal): string
    {
        return hash('sha256', (string) $terminal->secret_hash . '|' . (env('APP_KEY') ?: env('app.baseURL') ?: 'supportponto'));
    }

    private function rememberNonce(string $terminalId, string $nonce, string $purpose, ?string $ip, int $expiresAt): void
    {
        try {
            $this->nonceModel->insert([
                'terminal_id' => $terminalId,
                'nonce_hash' => hash('sha256', $terminalId . '|' . $nonce . '|' . $purpose),
                'purpose' => $purpose,
                'ip_address' => $ip,
                'expires_at' => date('Y-m-d H:i:s', $expiresAt),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Falha ao registrar nonce de terminal: ' . $e->getMessage());
        }
    }

    private function markSeen(object $terminal, RequestInterface $request): void
    {
        try {
            $this->terminalModel->update((int) $terminal->id, [
                'last_seen_at' => date('Y-m-d H:i:s'),
                'last_ip' => $request->getIPAddress(),
                'last_user_agent' => substr((string) $request->getUserAgent()->getAgentString(), 0, 1000),
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'Falha ao atualizar terminal last_seen: ' . $e->getMessage());
        }
    }

    private function legacyTokensAllowed(): bool
    {
        return filter_var(env('ALLOW_LEGACY_KIOSK_TOKEN', false), FILTER_VALIDATE_BOOL)
            && (ENVIRONMENT ?? 'production') !== 'production';
    }

    private function deny(string $reason, string $message, RequestInterface $request, int $status = 403, ?string $terminalId = null): array
    {
        try {
            $this->auditModel->log(null, 'TERMINAL_SECURITY_BLOCKED', 'punch_terminal_devices', null, null, [
                'reason' => $reason,
                'terminal_id' => $terminalId,
                'ip' => $request->getIPAddress(),
                'path' => $request->getUri()->getPath(),
            ], $message, 'warning');
        } catch (\Throwable $e) {
            log_message('warning', 'Falha ao auditar bloqueio de terminal: ' . $e->getMessage());
        }

        return ['success' => false, 'status' => $status, 'message' => $message, 'error' => $reason];
    }
}
