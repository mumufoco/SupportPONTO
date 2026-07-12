<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Controllers\BaseController;
use App\Models\SettingModel;
use App\Services\Security\TwoFactorAuthService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Psr\Log\LoggerInterface;

class TwoFactorController extends BaseController
{
    protected SettingModel $settingModel;
    protected TwoFactorAuthService $twoFactorService;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->settingModel = model(SettingModel::class);
        $this->twoFactorService = Services::twoFactorAuthService();
    }

    public function index()
    {
        $this->requireRole('admin');
        $settings = $this->settingModel->getByGroupMap('authentication') ?? [];

        try {
            $db = \Config\Database::connect();
            $recentEvents = $db->table('audit_logs')
                ->whereIn('action', ['2fa_success', '2fa_failure', '2fa_setup', '2fa_disabled'])
                ->orderBy('created_at', 'DESC')
                ->limit(20)
                ->get()->getResultArray();
        } catch (\Throwable $e) {
            $recentEvents = [];
        }

        return view('admin/settings/two_factor', [
            'title' => 'Autenticação de Dois Fatores (2FA)',
            'breadcrumbs' => [
                ['label' => 'Configurações', 'url' => sp_admin_settings_index_url()],
                ['label' => 'Autenticação', 'url' => route_to('admin.settings.authentication')],
                ['label' => '2FA', 'url' => ''],
            ],
            'settings' => $settings,
            'recent_events' => $recentEvents,
        ]);
    }

    public function update()
    {
        $this->requireRole('admin');
        if (!$this->request->is('post')) {
            return redirect()->back()->with('error', 'Método inválido');
        }
        $data = security_sanitize($this->request->getPost() ?? []);
        $boolFields = ['enable_2fa', '2fa_force_all_users'];
        foreach ($boolFields as $f) {
            $data[$f] = array_key_exists($f, $data) ? '1' : '0';
        }
        try {
            $save = array_filter($data, fn($k) => in_array($k, ['enable_2fa', '2fa_method', '2fa_force_all_users', '2fa_backup_codes_count']), ARRAY_FILTER_USE_KEY);
            $this->settingModel->setMultiple($save, 'authentication');
            $this->settingModel->clearCache();
            return redirect()->back()->with('success', 'Configurações de 2FA salvas.');
        } catch (\Throwable $e) {
            log_message('error', 'TwoFactorController::update ' . $e->getMessage());
            return redirect()->back()->with('error', 'Erro ao salvar configurações de 2FA.');
        }
    }

    public function generateQr()
    {
        $this->requireRole('admin');
        try {
            $secret = $this->twoFactorService->generateSecret();
            $accountName = (string) session()->get('user_email');
            $qrDataUri = $this->twoFactorService->getQRCodeDataUri($secret, $accountName);
            $otpauthUrl = $this->twoFactorService->getOTPAuthURL($secret, $accountName);
            return $this->response->setJSON([
                'success' => true,
                'message' => 'QR Code gerado. Use Google Authenticator, Authy ou similar para escanear.',
                'secret' => $secret,
                'qr_url' => $qrDataUri,
                'otpauth_url' => $otpauthUrl, 'csrf_hash' => csrf_hash(),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'TwoFactorController::generateQr ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'Erro ao gerar QR Code: ' . $e->getMessage()]);
        }
    }

    public function verify()
    {
        $this->requireRole('admin');
        $code = (string)($this->request->getPost('code') ?? '');
        $secret = (string)($this->request->getPost('secret') ?? '');

        if (strlen($code) !== 6 || !ctype_digit($code)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Código inválido. Informe os 6 dígitos.']);
        }

        if ($secret === '') {
            return $this->response->setJSON(['success' => false, 'message' => 'Segredo não informado.']);
        }

        try {
            $valid = $this->twoFactorService->verifyCode($secret, $code);
            if ($valid) {
                return $this->response->setJSON(['success' => true, 'message' => '2FA verificado com sucesso.', 'csrf_hash' => csrf_hash()]);
            }
            return $this->response->setJSON(['success' => false, 'message' => 'Código inválido ou expirado.', 'csrf_hash' => csrf_hash()]);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success' => false, 'message' => 'Erro ao verificar: ' . $e->getMessage()]);
        }
    }
}
