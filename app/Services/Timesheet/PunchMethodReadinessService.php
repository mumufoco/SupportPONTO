<?php

namespace App\Services\Timesheet;

class PunchMethodReadinessService
{
    private readonly PunchService $punchService;

    public function __construct(?PunchService $punchService = null)
    {
        $this->punchService = $punchService ?? new PunchService();
    }

    public function summary(bool $isKioskMode = false, bool $isPublicQuickAccess = false): array
    {
        $enabled = $this->punchService->getEnabledPunchMethods();

        return [
            'codigo' => $this->buildMethod(
                key: 'codigo',
                label: 'Código único',
                description: 'Identificação rápida por código interno do colaborador.',
                icon: 'bi bi-123',
                enabled: (bool) ($enabled['codigo'] ?? false),
                requiresCamera: false,
                requiresDevice: false,
                recommendedContexts: ['contingência', 'terminal', 'registro rápido'],
                publicAllowed: true,
                kioskAllowed: true,
                endpoint: site_url($isPublicQuickAccess || $isKioskMode ? 'punch-terminal/code' : 'timesheet/punch/code')
            ),
            'cpf' => $this->buildMethod(
                key: 'cpf',
                label: 'CPF',
                description: 'Registro pelo documento do colaborador com validação do CPF informado.',
                icon: 'bi bi-card-text',
                enabled: (bool) ($enabled['cpf'] ?? false),
                requiresCamera: false,
                requiresDevice: false,
                recommendedContexts: ['contingência', 'terminal', 'registro rápido'],
                publicAllowed: true,
                kioskAllowed: true,
                endpoint: site_url($isPublicQuickAccess || $isKioskMode ? 'punch-terminal/cpf' : 'timesheet/punch/cpf')
            ),
            'facial' => $this->buildMethod(
                key: 'facial',
                label: 'Reconhecimento facial',
                description: 'Validação com câmera e índice biométrico facial do colaborador.',
                icon: 'bi bi-camera-video-fill',
                enabled: (bool) ($enabled['facial'] ?? false),
                requiresCamera: true,
                requiresDevice: false,
                recommendedContexts: ['terminal', 'totem', 'mobile com câmera'],
                publicAllowed: true,
                kioskAllowed: true,
                endpoint: site_url($isPublicQuickAccess || $isKioskMode ? 'punch-terminal/face' : 'timesheet/punch/face')
            ),
            'biometria' => $this->buildMethod(
                key: 'biometria',
                label: 'Biometria digital',
                description: 'Validação por impressão digital em dispositivo compatível.',
                icon: 'bi bi-fingerprint',
                enabled: (bool) ($enabled['biometria'] ?? false),
                requiresCamera: false,
                requiresDevice: true,
                recommendedContexts: ['terminal dedicado', 'dispositivo compatível'],
                publicAllowed: true,
                kioskAllowed: true,
                endpoint: site_url($isPublicQuickAccess || $isKioskMode ? 'punch-terminal/fingerprint' : 'timesheet/punch/fingerprint')
            ),
            'qrcode' => $this->buildMethod(
                key: 'qrcode',
                label: 'QR Code',
                description: 'Leitura rápida do token visual do colaborador por câmera.',
                icon: 'bi bi-qr-code-scan',
                enabled: (bool) ($enabled['qrcode'] ?? false),
                requiresCamera: true,
                requiresDevice: false,
                recommendedContexts: ['terminal', 'portaria', 'registro rápido'],
                publicAllowed: true,
                kioskAllowed: true,
                endpoint: sp_route_url('timesheet.punch.qr')
            ),
        ];
    }

    private function buildMethod(
        string $key,
        string $label,
        string $description,
        string $icon,
        bool $enabled,
        bool $requiresCamera,
        bool $requiresDevice,
        array $recommendedContexts,
        bool $publicAllowed,
        bool $kioskAllowed,
        string $endpoint,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'icon' => $icon,
            'enabled' => $enabled,
            'status_label' => $enabled ? 'Habilitado' : 'Desabilitado',
            'status_variant' => $enabled ? 'success' : 'muted',
            'requires_camera' => $requiresCamera,
            'requires_device' => $requiresDevice,
            'recommended_contexts' => $recommendedContexts,
            'public_allowed' => $publicAllowed,
            'kiosk_allowed' => $kioskAllowed,
            'endpoint' => $endpoint,
            'homologation_checks' => $this->homologationChecks($key, $requiresCamera, $requiresDevice),
        ];
    }

    private function homologationChecks(string $key, bool $requiresCamera, bool $requiresDevice): array
    {
        $checks = [
            'Selecionar o tipo de marcação e confirmar retorno visual da operação.',
            'Validar que o registro entra no histórico e gera auditoria coerente.',
        ];

        if ($requiresCamera) {
            $checks[] = 'Validar permissão de câmera, fallback quando negada e retomada do fluxo.';
        }

        if ($requiresDevice) {
            $checks[] = 'Validar suporte do dispositivo biométrico e mensagem quando indisponível.';
        }

        if ($key === 'cpf') {
            $checks[] = 'Testar CPF válido, inválido, colaborador inativo e máscara no frontend.';
        }

        if ($key === 'codigo') {
            $checks[] = 'Testar código válido, inexistente e bloqueio de sequência inválida.';
        }

        if ($key === 'qrcode') {
            $checks[] = 'Testar token válido, expirado, já utilizado e leitura interrompida.';
        }

        if ($key === 'facial') {
            $checks[] = 'Testar colaborador com biometria cadastrada, sem cadastro e câmera indisponível.';
        }

        if ($key === 'biometria') {
            $checks[] = 'Testar template válido, não reconhecido e comportamento em navegador sem WebAuthn.';
        }

        return $checks;
    }
}
