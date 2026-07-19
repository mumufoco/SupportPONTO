<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SettingModel;
use App\Services\Auth\SessionSecurityService;
use App\Services\Backup\BackupCheckService;
use App\Services\Backup\DatabaseBackupService;
use App\Services\Backup\RestoreTestRegistryService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Psr\Log\LoggerInterface;

/**
 * Backup
 *
 * Reproduz a forma como o SupportCHECK trata backup (BackupSettingsController +
 * BackupCheckService + RestoreTestRegistryService): status de prontidao real,
 * historico de checagens, lista de backups existentes e registro de teste de
 * restauracao -- em vez do formulario decorativo anterior (frequencia/destino
 * S3-GCS que nunca eram lidos em nenhum lugar do codigo).
 *
 * A geracao de backup em si reaproveita o job assincrono ja existente e testado
 * (admin.settings.controls.backup, o mesmo do botao "Snapshot de seguranca" em
 * Controles) em vez de duplicar a logica de enfileiramento aqui.
 */
class BackupController extends BaseController
{
    protected SettingModel $settingModel;
    protected SessionSecurityService $sessionSecurityService;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->settingModel = model(SettingModel::class);
        $this->sessionSecurityService = Services::sessionSecurityService();
    }

    public function index()
    {
        $this->requireRole('admin');

        $checkService = new BackupCheckService();
        $restoreTests = new RestoreTestRegistryService();
        $backupService = new DatabaseBackupService();

        $latestCheck = $checkService->latest() ?? $checkService->run();

        return view('admin/settings/backup', [
            'title'       => 'Backup',
            'breadcrumbs' => [
                ['label' => 'Configurações', 'url' => sp_admin_settings_index_url()],
                ['label' => 'Backup',        'url' => ''],
            ],
            'readiness'         => $backupService->verifyReadiness(),
            'backups'           => $backupService->listBackups(),
            'latestCheck'       => $latestCheck,
            'latestRestoreTest' => $restoreTests->latest(),
            'retentionDays'     => (int) ($this->settingModel->get('backup_retention_days') ?? 30),
        ]);
    }

    /**
     * Salva a retencao (dias) de backups locais -- unico campo real que
     * sobrou do formulario antigo (os outros nunca tinham efeito nenhum).
     */
    public function updateRetention()
    {
        $this->requireRole('admin');

        if (! $this->request->is('post')) {
            return redirect()->back()->with('error', 'Método inválido');
        }

        // Teto elevado de 365 para 1825 dias (5 anos): o valor antigo era
        // estruturalmente incompativel com a retencao minima legal CLT/MTE
        // (5 anos) usada em outros pontos do sistema -- um admin nao conseguia
        // configurar retencao de backup alinhada a esse minimo nem que quisesse.
        $days = (int) $this->request->getPost('backup_retention_days');
        if ($days < 1 || $days > 1825) {
            return redirect()->back()->with('error', 'Retenção deve ser entre 1 e 1825 dias (5 anos).');
        }

        $this->settingModel->setSetting('backup_retention_days', (string) $days, 'integer', 'backup');
        $this->settingModel->clearCache();

        return redirect()->back()->with('success', 'Retenção de backup atualizada.');
    }

    /**
     * Roda uma nova checagem de saude do backup (prontidao + arquivo mais
     * recente + risco) e persiste no historico.
     */
    public function check()
    {
        $this->requireRole('admin');

        if (! $this->request->is('post')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Método inválido']);
        }

        try {
            $result = (new BackupCheckService())->run();

            return $this->response->setJSON([
                'success' => true,
                'status'  => $result->status,
                'risks'   => $result->risks,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'BackupController::check ' . $e->getMessage());

            return $this->response->setJSON(['success' => false, 'message' => 'Erro ao verificar backup.']);
        }
    }

    /**
     * Registra que um admin efetivamente testou a restauracao de um backup
     * (confirmacao manual -- nao dispara restauracao automatica, que
     * sobrescreveria o banco de producao).
     */
    public function recordRestoreTest()
    {
        $this->requireRole('admin');

        if (! $this->request->is('post')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Método inválido']);
        }

        $guard = $this->sessionSecurityService->ensureCriticalActionAllowed($this->currentUser, $this->request, session());
        if (! ($guard['success'] ?? false)) {
            return $this->response->setJSON($guard)->setStatusCode(422);
        }

        $notes = trim((string) $this->request->getPost('notes'));
        $userId = (int) ($this->currentUser->id ?? session()->get('user_id') ?? 0) ?: null;

        try {
            (new RestoreTestRegistryService())->record($userId, 'ok', $notes !== '' ? $notes : null);
            (new BackupCheckService())->run();

            return $this->response->setJSON(['success' => true, 'message' => 'Teste de restauração registrado.']);
        } catch (\Throwable $e) {
            log_message('error', 'BackupController::recordRestoreTest ' . $e->getMessage());

            return $this->response->setJSON(['success' => false, 'message' => 'Erro ao registrar teste de restauração.']);
        }
    }

    /**
     * Baixa um arquivo de backup existente pelo nome (POST, com reconfirmacao
     * de senha -- o dump contem TODOS os dados pessoais/biometricos da base,
     * mesmo nivel de cautela ja usado pro Snapshot de seguranca). Valida
     * contra a lista real retornada por DatabaseBackupService::listBackups()
     * -- nunca confia no nome de arquivo vindo da requisicao diretamente
     * (previne path traversal).
     */
    public function downloadFile(string $filename)
    {
        $this->requireRole('admin');

        if (! $this->request->is('post')) {
            return redirect()->back()->with('error', 'Método inválido');
        }

        $guard = $this->sessionSecurityService->ensureCriticalActionAllowed($this->currentUser, $this->request, session());
        if (! ($guard['success'] ?? false)) {
            return redirect()->back()->with('error', $guard['message'] ?? 'Confirme sua senha para baixar o backup.');
        }

        $backups = (new DatabaseBackupService())->listBackups();
        $match = null;
        foreach ($backups as $backup) {
            if ($backup['filename'] === $filename) {
                $match = $backup;
                break;
            }
        }

        if (! $match) {
            return redirect()->back()->with('error', 'Arquivo de backup não encontrado.');
        }

        return $this->response->download($match['filepath'], null);
    }
}
