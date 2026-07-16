<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Controllers\BaseController;
use App\Models\EmployeeModel;
use App\Services\Auth\TwoFactorManagerService;
use App\Services\Security\TwoFactorPolicyService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Psr\Log\LoggerInterface;

class TwoFactorController extends BaseController
{
    protected EmployeeModel $employeeModel;
    protected TwoFactorPolicyService $policyService;
    protected TwoFactorManagerService $twoFactorManagerService;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->employeeModel = model(EmployeeModel::class);
        $this->policyService = new TwoFactorPolicyService();
        $this->twoFactorManagerService = Services::twoFactorManagerService();
    }

    public function index()
    {
        $this->requireRole('admin');

        $totalUsers = $this->employeeModel->where('active', true)->countAllResults();
        $enabledUsers = $this->employeeModel->where('active', true)->where('two_factor_enabled', true)->countAllResults();
        $confirmedUsers = $this->employeeModel->where('active', true)
            ->where('two_factor_enabled', true)
            ->where('two_factor_verified_at IS NOT NULL', null, false)
            ->countAllResults();

        $stats = [
            'enabled' => $enabledUsers,
            'confirmed' => $confirmedUsers,
            'total_users' => $totalUsers,
            'coverage' => $totalUsers > 0 ? (int) round($enabledUsers / $totalUsers * 100) : 0,
        ];

        $usersWithTwoFactor = $this->employeeModel
            ->where('two_factor_enabled', true)
            ->orderBy('two_factor_verified_at', 'DESC')
            ->limit(20)
            ->find();

        $recoveryCodesLeft = [];
        foreach ($usersWithTwoFactor as $employee) {
            $recoveryCodesLeft[$employee->id] = $this->twoFactorManagerService->countRemainingBackupCodes($employee);
        }

        return view('admin/settings/two_factor', [
            'title' => 'Autenticação de Dois Fatores (2FA)',
            'breadcrumbs' => [
                ['label' => 'Configurações', 'url' => sp_admin_settings_index_url()],
                ['label' => 'Controles', 'url' => route_to('admin.settings.controls')],
                ['label' => '2FA', 'url' => ''],
            ],
            'currentMode' => $this->policyService->getMode(),
            'modes' => TwoFactorPolicyService::MODES,
            'stats' => $stats,
            'usersWithTwoFactor' => $usersWithTwoFactor,
            'recoveryCodesLeft' => $recoveryCodesLeft,
        ]);
    }

    public function update()
    {
        $this->requireRole('admin');
        if (!$this->request->is('post')) {
            return redirect()->back()->with('error', 'Método inválido');
        }

        $mode = (string) $this->request->getPost('mode');
        if (! $this->policyService->setMode($mode)) {
            return redirect()->back()->with('error', 'Modo de política de 2FA inválido.');
        }

        return redirect()->back()->with('success', 'Configuração de 2FA salva com sucesso.');
    }

    public function resetUser(int $id)
    {
        $this->requireRole('admin');
        if (!$this->request->is('post')) {
            return redirect()->back()->with('error', 'Método inválido');
        }

        $employee = $this->employeeModel->find($id);
        if (! $employee) {
            return redirect()->back()->with('error', 'Colaborador não encontrado.');
        }

        try {
            $this->twoFactorManagerService->disableForEmployee($id);

            helper('observability');
            $actorId = (int) ($this->currentUser->id ?? session()->get('user_id') ?? 0);
            log_structured('warning', 'admin.two_factor.user_reset', [
                'actor_id' => $actorId,
                'target_employee_id' => $id,
            ]);

            return redirect()->back()->with('success', 'O 2FA do colaborador "' . $employee->name . '" foi redefinido.');
        } catch (\Throwable $e) {
            log_message('error', 'TwoFactorController::resetUser ' . $e->getMessage());
            return redirect()->back()->with('error', 'Erro ao redefinir o 2FA do colaborador.');
        }
    }
}
