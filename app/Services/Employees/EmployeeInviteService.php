<?php
namespace App\Services\Employees;

use App\Models\EmployeeInviteModel;
use App\Services\EmailService;

class EmployeeInviteService
{
    public function __construct(
        private EmployeeInviteModel $inviteModel = new EmployeeInviteModel(),
    ) {}

    public function create(array $data, int $createdBy): array
    {
        $token     = bin2hex(random_bytes(32));
        $hours     = (int) ($data['expires_hours'] ?? 72);
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$hours} hours"));

        $this->inviteModel->insert([
            'token'      => $token,
            'email'      => strtolower(trim($data['email'] ?? '')),
            'name'       => trim($data['name'] ?? ''),
            'department' => trim($data['department'] ?? ''),
            'work_unit'  => trim($data['work_unit'] ?? ''),
            'position'   => trim($data['position'] ?? ''),
            'role'       => $data['role'] ?? 'funcionario',
            'tipo_contrato' => trim((string) ($data['tipo_contrato'] ?? '')) ?: null,
            'message'    => trim($data['message'] ?? ''),
            'expires_at' => $expiresAt,
            'created_by' => $createdBy,
        ]);

        $inviteUrl = site_url('convite/' . $token);

        if (!empty($data['send_email']) && !empty($data['email'])) {
            $this->sendInviteEmail($data['email'], $inviteUrl, $data);
        }

        return ['success' => true, 'token' => $token, 'url' => $inviteUrl];
    }

    public function validate(string $token): array
    {
        $invite = $this->inviteModel->findByToken($token);
        if (!$invite) {
            return ['success' => false, 'message' => 'Link de convite inválido.'];
        }
        if (!$this->inviteModel->isValid($invite)) {
            $msg = $invite->used_at
                ? 'Este convite já foi utilizado.'
                : 'Este convite expirou. Solicite um novo link.';
            return ['success' => false, 'message' => $msg];
        }
        return ['success' => true, 'invite' => $invite];
    }

    public function markUsed(string $token): void
    {
        $invite = $this->inviteModel->findByToken($token);
        if ($invite) {
            $this->inviteModel->markUsed((int) $invite->id);
        }
    }

    private function sendInviteEmail(string $to, string $url, array $data): void
    {
        try {
            $emailService = new EmailService();
            $name    = !empty($data['name']) ? ', ' . $data['name'] : '';
            $message = !empty($data['message']) ? '<p style="margin:1rem 0;color:#374151">' . nl2br(esc($data['message'])) . '</p>' : '';
            $hours   = (int) ($data['expires_hours'] ?? 72);
            $expiry  = $hours >= 24 ? ($hours / 24) . ' dias' : $hours . ' horas';

            $html = '
            <div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto">
                <div style="background:#1A4A7A;padding:1.5rem 2rem;border-radius:8px 8px 0 0">
                    <h1 style="color:#fff;font-size:1.4rem;margin:0">Convite para cadastro</h1>
                    <p style="color:rgba(255,255,255,.8);margin:.3rem 0 0;font-size:.9rem">SupportPONTO — Sistema de Controle de Ponto</p>
                </div>
                <div style="background:#f8fafc;padding:2rem;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 8px 8px">
                    <p style="color:#374151;font-size:1rem">Olá' . $name . ',</p>
                    <p style="color:#374151">Você foi convidado(a) para se cadastrar no sistema de ponto eletrônico da empresa.</p>
                    ' . $message . '
                    <div style="text-align:center;margin:2rem 0">
                        <a href="' . $url . '" style="background:#1A4A7A;color:#fff;padding:.85rem 2rem;border-radius:6px;text-decoration:none;font-weight:bold;font-size:1rem;display:inline-block">
                            Acessar formulário de cadastro
                        </a>
                    </div>
                    <p style="color:#6b7280;font-size:.82rem;text-align:center">
                        Este link expira em <strong>' . $expiry . '</strong>.<br>
                        Se não solicitou este cadastro, ignore este e-mail.
                    </p>
                    <hr style="border:none;border-top:1px solid #e5e7eb;margin:1.5rem 0">
                    <p style="color:#9ca3af;font-size:.75rem;text-align:center">
                        Link direto: <a href="' . $url . '" style="color:#1A4A7A">' . $url . '</a>
                    </p>
                </div>
            </div>';

            $emailService->send($to, 'Convite para cadastro — SupportPONTO', $html);
        } catch (\Throwable $e) {
            log_message('error', '[InviteService] Email send failed: ' . $e->getMessage());
        }
    }
}
