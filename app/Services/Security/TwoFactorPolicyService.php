<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\SettingModel;
use Config\Services;

/**
 * Política de 2FA em nível de sistema (não confundir com o 2FA pessoal de
 * cada funcionário, gerenciado por App\Services\Auth\TwoFactorManagerService).
 *
 * Mesmo modelo de política do SupportCHECK (Admin\TwoFactorAdminController):
 * um modo único define quando o 2FA é exigido no login, em vez de dois
 * booleans independentes (enable_2fa + force_all_users) que nunca chegavam
 * a ser lidos no fluxo de autenticação real.
 */
class TwoFactorPolicyService
{
    public const MODE_DISABLED = 'disabled';
    public const MODE_OPTIONAL = 'optional_per_user';
    public const MODE_REQUIRED_ADMIN = 'required_for_admin';
    public const MODE_REQUIRED_ADMIN_MANAGER = 'required_for_admin_and_manager';
    public const MODE_REQUIRED_ALL = 'required_for_all';

    /**
     * @var array<string, array{label:string, desc:string, icon:string}>
     */
    public const MODES = [
        self::MODE_DISABLED => [
            'label' => '2FA desativado',
            'desc'  => 'Nenhum usuário precisará de 2FA no login.',
            'icon'  => 'bi bi-unlock-fill',
        ],
        self::MODE_OPTIONAL => [
            'label' => '2FA opcional',
            'desc'  => 'Cada usuário decide se ativa ou não no próprio perfil.',
            'icon'  => 'bi bi-toggles',
        ],
        self::MODE_REQUIRED_ADMIN => [
            'label' => 'Obrigatório para Administradores',
            'desc'  => 'Apenas usuários com perfil Admin precisam configurar 2FA.',
            'icon'  => 'bi bi-shield-lock-fill',
        ],
        self::MODE_REQUIRED_ADMIN_MANAGER => [
            'label' => 'Obrigatório para Admin e Gestores',
            'desc'  => 'Admins e gestores precisam de 2FA; demais usuários, opcional.',
            'icon'  => 'bi bi-shield-fill-check',
        ],
        self::MODE_REQUIRED_ALL => [
            'label' => '2FA obrigatório para todos',
            'desc'  => 'Todos os usuários precisarão configurar e usar 2FA no login.',
            'icon'  => 'bi bi-shield-fill-exclamation',
        ],
    ];

    public function __construct(
        private readonly ?SettingModel $settingModel = null
    ) {
    }

    private function settings(): SettingModel
    {
        return $this->settingModel ?? Services::settings(false);
    }

    /**
     * Lê o modo de política atual. Se '2fa_mode' ainda não foi salvo (banco
     * criado antes desta mudança), deriva um modo equivalente dos campos
     * antigos (enable_2fa / 2fa_force_all_users) para não perder o que já
     * estava configurado.
     */
    public function getMode(): string
    {
        $settings = $this->settings()->getByGroupMap('authentication') ?? [];

        $mode = (string) ($settings['2fa_mode'] ?? '');
        if (array_key_exists($mode, self::MODES)) {
            return $mode;
        }

        $legacyEnabled = ($settings['enable_2fa'] ?? '0') === '1';
        $legacyForced = ($settings['2fa_force_all_users'] ?? '0') === '1';

        if (! $legacyEnabled) {
            return self::MODE_DISABLED;
        }

        return $legacyForced ? self::MODE_REQUIRED_ALL : self::MODE_OPTIONAL;
    }

    public function setMode(string $mode): bool
    {
        if (! array_key_exists($mode, self::MODES)) {
            return false;
        }

        return $this->settings()->setMultiple(['2fa_mode' => $mode], 'authentication');
    }

    /**
     * Verifica se o modo de política atual exige 2FA para o role informado.
     */
    public function isRequiredForRole(string $role): bool
    {
        return match ($this->getMode()) {
            self::MODE_REQUIRED_ADMIN => $role === 'admin',
            self::MODE_REQUIRED_ADMIN_MANAGER => in_array($role, ['admin', 'gestor'], true),
            self::MODE_REQUIRED_ALL => true,
            default => false,
        };
    }
}
