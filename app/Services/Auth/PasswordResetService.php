<?php

namespace App\Services\Auth;

use App\Models\EmployeeModel;
use App\Models\SettingModel;
use App\Services\Email\EmailTransportConfigurator;
use App\Services\Security\RateLimitService;
use Config\Services;

class PasswordResetService
{
    protected EmployeeModel $employeeModel;
    protected RateLimitService $rateLimitService;
    protected PasswordLifecycleService $passwordLifecycleService;
    protected SettingModel $settingModel;

    public function __construct(
        ?EmployeeModel $employeeModel = null,
        ?RateLimitService $rateLimitService = null,
        ?PasswordLifecycleService $passwordLifecycleService = null,
        ?SettingModel $settingModel = null,
    ) {
        $this->employeeModel            = $employeeModel            ?? model(EmployeeModel::class);
        $this->rateLimitService         = $rateLimitService         ?? new RateLimitService();
        $this->passwordLifecycleService = $passwordLifecycleService ?? new PasswordLifecycleService();
        $this->settingModel             = $settingModel             ?? model(SettingModel::class);
    }

    public function requestReset(string $email): void
    {
        $ip   = $this->rateLimitService->getClientIp();
        $keys = [
            $this->rateLimitService->generateKeyForPath('auth/forgot-password', $ip),
            'forgot_password_account_' . md5($email),
        ];

        foreach ($keys as $key) {
            $limitInfo = $this->rateLimitService->attempt($key, 'password_reset', $ip);
            if (! $limitInfo['allowed']) {
                return;
            }
        }

        $employee = $this->employeeModel->findByEmail($email);
        if (! $employee || ! $employee->active) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $this->employeeModel->update($employee->id, [
            'password_reset_token'   => hash('sha256', $token),
            'password_reset_expires' => date('Y-m-d H:i:s', time() + 3600),
        ]);

        $this->sendResetEmail((string) $employee->email, (string) $employee->name, $token);
    }

    public function validateToken(string $token): ?object
    {
        return $this->employeeModel
            ->where('password_reset_token', hash('sha256', $token))
            ->where('password_reset_expires >', date('Y-m-d H:i:s'))
            ->first();
    }

    public function resetPassword(string $token, string $password): bool
    {
        $employee = $this->validateToken($token);
        if (! $employee) {
            return false;
        }

        $this->passwordLifecycleService->updatePassword((int) $employee->id, $password, [
            'clear_reset_tokens'    => true,
            'clear_remember_tokens' => true,
        ]);

        return true;
    }

    protected function sendResetEmail(string $toEmail, string $toName, string $token): void
    {
        try {
            $resetLink = site_url('reset-password/' . $token);

            $ci4Email   = Services::email();
            $transport  = new EmailTransportConfigurator($this->settingModel);
            $config     = $transport->configure($ci4Email);

            $fromEmail  = $config['from_email'] ?: 'noreply@' . parse_url(base_url(), PHP_URL_HOST);
            $fromName   = $config['from_name']  ?: 'SupportPONTO';

            $ci4Email->setFrom($fromEmail, $fromName);
            $ci4Email->setTo($toEmail);
            $ci4Email->setSubject('Redefinição de Senha — SupportPONTO');
            $ci4Email->setMailType('html');
            $ci4Email->setMessage(
                '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="font-family:sans-serif;color:#111;">'
                . '<h2 style="color:#3B82F6;">Redefinição de Senha</h2>'
                . '<p>Olá, <strong>' . htmlspecialchars($toName) . '</strong>.</p>'
                . '<p>Recebemos uma solicitação para redefinir a senha da sua conta no <strong>SupportPONTO</strong>.</p>'
                . '<p style="margin:24px 0;">'
                . '<a href="' . $resetLink . '" style="background:#3B82F6;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:bold;">Redefinir minha senha</a>'
                . '</p>'
                . '<p style="color:#6B7280;font-size:13px;">O link expira em <strong>1 hora</strong>.<br>'
                . 'Se você não solicitou esta redefinição, ignore este e-mail — sua senha permanece a mesma.</p>'
                . '<hr style="border:none;border-top:1px solid #E5E7EB;margin:24px 0;">'
                . '<p style="color:#9CA3AF;font-size:12px;">SupportPONTO — Support Solo Sondagens</p>'
                . '</body></html>'
            );

            $sent = $ci4Email->send();
            $ci4Email->clear();

            if (! $sent) {
                log_message('error', '[PasswordResetService] Email não enviado para ' . $toEmail
                    . ' — ' . $ci4Email->printDebugger(['headers']));
            }
        } catch (\Throwable $e) {
            log_message('error', '[PasswordResetService] Falha ao enviar email de reset: ' . $e->getMessage());
        }
    }
}
