<?php

namespace App\Controllers\Admin;

use Config\Services;

use App\Controllers\BaseController;
use App\Models\BiometricTemplateModel;
use App\Models\EmployeeModel;
use App\Models\AuditModel;
use App\Support\Dates\DashboardDateRange;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Biometric Dashboard Controller
 * 
 * Administrative dashboard for biometric fingerprint system monitoring
 * and statistics. Requires admin role.
 */
class BiometricDashboardController extends BaseController
{
    protected BiometricTemplateModel $biometricModel;
    protected EmployeeModel $employeeModel;
    protected AuditModel $auditModel;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        
        $this->biometricModel = Services::biometricTemplateModel(false);
        $this->employeeModel = Services::employeeModel(false);
        $this->auditModel = Services::auditModel(false);

        // Check if user is admin
        $session = session();
        $employee = $this->employeeModel->find($session->get('user_id'));
        
        if (!$employee || $employee->role !== 'admin') {
            throw new \App\Exceptions\ForbiddenException('acessar o dashboard biométrico');
        }
    }

    /**
     * Dashboard index
     */
    public function index()
    {
        $data = [
            'title' => 'Dashboard - Biometria Digital',
            'statistics' => $this->getStatistics(),
            'recentEnrollments' => $this->getRecentEnrollments(),
            'recentAuthentications' => $this->getRecentAuthentications(),
            'qualityDistribution' => $this->getQualityDistribution(),
            'departmentDistribution' => $this->getDepartmentDistribution(),
        ];

        return view('admin/biometric/dashboard', $data);
    }

    /**
     * Get dashboard statistics
     */
    private function getStatistics(): array
    {
        // Total active templates
        $totalTemplates = $this->biometricModel
            ->where('biometric_type', 'fingerprint')
            ->where('is_active', true)
            ->countAllResults();

        // Total users with biometric
        $totalUsers = $this->employeeModel
            ->where('has_fingerprint_biometric', true)
            ->where('active', true)
            ->countAllResults();

        // Average quality score
        $avgQualityResult = $this->biometricModel
            ->select('AVG(quality_score) as avg_quality')
            ->where('biometric_type', 'fingerprint')
            ->where('is_active', true)
            ->where('quality_score IS NOT NULL')
            ->first();
        
        $avgQuality = round((float) ($avgQualityResult->avg_quality ?? 0), 2);

        // Enrollments last 30 days
        $enrollmentsLast30Days = $this->biometricModel
            ->where('biometric_type', 'fingerprint')
            ->where('enrolled_at >=', date('Y-m-d H:i:s', strtotime('-30 days')))
            ->countAllResults();

        $auditTable = $this->auditTable();
        [$todayStartAt, $tomorrowStartAt] = DashboardDateRange::day();

        // Successful authentications today
        $successToday = $this->auditModelBuilder()
            ->where($auditTable . '.action', 'BIOMETRIC_AUTH_SUCCESS')
            ->where($auditTable . '.created_at >=', $todayStartAt)
            ->where($auditTable . '.created_at <', $tomorrowStartAt)
            ->countAllResults();

        // Failed authentications today
        $failedToday = $this->auditModelBuilder()
            ->where($auditTable . '.action', 'BIOMETRIC_AUTH_FAILED')
            ->where($auditTable . '.created_at >=', $todayStartAt)
            ->where($auditTable . '.created_at <', $tomorrowStartAt)
            ->countAllResults();

        // Duplicate attempts (all time)
        $duplicateAttempts = $this->auditModelBuilder()
            ->where($auditTable . '.action', 'BIOMETRIC_DUPLICATE_ATTEMPT')
            ->countAllResults();

        return [
            'total_templates' => $totalTemplates,
            'total_users' => $totalUsers,
            'avg_quality' => $avgQuality,
            'enrollments_30_days' => $enrollmentsLast30Days,
            'success_today' => $successToday,
            'failed_today' => $failedToday,
            'duplicate_attempts' => $duplicateAttempts,
        ];
    }

    /**
     * Get recent enrollments
     */
    private function getRecentEnrollments(int $limit = 10): array
    {
        $templates = $this->biometricModel
            ->select('biometric_templates.*, employees.name, employees.cpf')
            ->join('employees', 'employees.id = biometric_templates.employee_id')
            ->where('biometric_templates.biometric_type', 'fingerprint')
            ->orderBy('biometric_templates.enrolled_at', 'DESC')
            ->limit($limit)
            ->findAll();

        // MED-11 (auditoria): JOIN cru — decripta o CPF explicitamente.
        foreach ($templates as $template) {
            $template->cpf = \App\Models\EmployeeModel::decryptCpfValue($template->cpf ?? null);
        }

        return $templates;
    }

    /**
     * Get recent authentication attempts
     */
    private function getRecentAuthentications(int $limit = 20): array
    {
        $auditTable = $this->auditTable();

        $logs = $this->auditModelBuilder()
            ->select($auditTable . '.*, employees.name, employees.cpf')
            ->join('employees', 'employees.id = ' . $auditTable . '.user_id', 'left')
            ->whereIn($auditTable . '.action', ['BIOMETRIC_AUTH_SUCCESS', 'BIOMETRIC_AUTH_FAILED', 'BIOMETRIC_IDENTIFY_SUCCESS', 'BIOMETRIC_IDENTIFY_FAILED'])
            ->orderBy($auditTable . '.created_at', 'DESC')
            ->limit($limit)
            ->get()
            ->getResult();

        // MED-11 (auditoria): JOIN cru — decripta o CPF explicitamente.
        foreach ($logs as $log) {
            if (isset($log->cpf)) {
                $log->cpf = \App\Models\EmployeeModel::decryptCpfValue($log->cpf);
            }
        }

        return $logs;
    }

    /**
     * Get quality score distribution
     */
    private function getQualityDistribution(): array
    {
        $distribution = [
            'excellent' => 0, // 80-100
            'good' => 0,      // 60-79
            'fair' => 0,      // 40-59
            'poor' => 0,      // 30-39
        ];

        $templates = $this->biometricModel
            ->select('quality_score')
            ->where('biometric_type', 'fingerprint')
            ->where('is_active', true)
            ->where('quality_score IS NOT NULL')
            ->findAll();

        foreach ($templates as $template) {
            $score = $template->quality_score;
            if ($score >= 80) {
                $distribution['excellent']++;
            } elseif ($score >= 60) {
                $distribution['good']++;
            } elseif ($score >= 40) {
                $distribution['fair']++;
            } else {
                $distribution['poor']++;
            }
        }

        return $distribution;
    }

    /**
     * Get department distribution
     */
    private function getDepartmentDistribution(): array
    {
        $result = $this->employeeModel
            ->select('department, COUNT(*) as count')
            ->where('has_fingerprint_biometric', true)
            ->where('active', true)
            ->groupBy('department')
            ->orderBy('count', 'DESC')
            ->findAll();

        $distribution = [];
        foreach ($result as $row) {
            $distribution[$row->department ?? 'Sem Departamento'] = $row->count;
        }

        return $distribution;
    }

    /**
     * Get authentication history (AJAX)
     */
    public function getAuthHistory()
    {
        $days = $this->request->getGet('days') ?? 7;
        $days = min(30, max(1, (int) $days));

        // Build the ordered date list once
        $dates = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $dates[] = date('Y-m-d', strtotime("-{$i} days"));
        }
        $rangeStart = $dates[0] . ' 00:00:00';
        $rangeEnd   = date('Y-m-d H:i:s', strtotime(end($dates) . ' +1 day'));
        reset($dates);

        $auditTable = $this->auditTable();

        // Batch-fetch success/failure counts by date in one query — eliminates N+1 (was 2 queries per day)
        $rows = $this->auditModelBuilder()
            ->select("DATE({$auditTable}.created_at) AS day, {$auditTable}.action, COUNT(*) AS cnt", false)
            ->whereIn("{$auditTable}.action", [
                'BIOMETRIC_AUTH_SUCCESS', 'BIOMETRIC_IDENTIFY_SUCCESS',
                'BIOMETRIC_AUTH_FAILED',  'BIOMETRIC_IDENTIFY_FAILED',
            ])
            ->where("{$auditTable}.created_at >=", $rangeStart)
            ->where("{$auditTable}.created_at <", $rangeEnd)
            ->groupBy("DATE({$auditTable}.created_at), {$auditTable}.action", false)
            ->get()
            ->getResultArray();

        $successActions = ['BIOMETRIC_AUTH_SUCCESS', 'BIOMETRIC_IDENTIFY_SUCCESS'];
        $countsByDay    = [];
        foreach ($rows as $row) {
            $day = (string) $row['day'];
            $countsByDay[$day] ??= ['success' => 0, 'failed' => 0];
            if (in_array($row['action'], $successActions, true)) {
                $countsByDay[$day]['success'] += (int) $row['cnt'];
            } else {
                $countsByDay[$day]['failed'] += (int) $row['cnt'];
            }
        }

        $history = [];
        foreach ($dates as $date) {
            $history[] = [
                'date'    => $date,
                'success' => $countsByDay[$date]['success'] ?? 0,
                'failed'  => $countsByDay[$date]['failed'] ?? 0,
            ];
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $history,
        ]);
    }


    private function auditTable(): string
    {
        return $this->auditModel->getTable();
    }

    private function auditModelBuilder()
    {
        return $this->auditModel->builder();
    }

    /**
     * Export statistics (CSV)
     */
    public function exportStats(): ResponseInterface
    {
        $templates = $this->biometricModel
            ->select('biometric_templates.*, employees.name, employees.cpf, employees.department')
            ->join('employees', 'employees.id = biometric_templates.employee_id')
            ->where('biometric_templates.biometric_type', 'fingerprint')
            ->orderBy('biometric_templates.enrolled_at', 'DESC')
            ->findAll();

        // MED-11 (auditoria): JOIN cru — decripta o CPF explicitamente antes do CSV.
        foreach ($templates as $template) {
            $template->cpf = \App\Models\EmployeeModel::decryptCpfValue($template->cpf ?? null);
        }

        $filename = 'biometric_stats_' . date('Y-m-d_His') . '.csv';

        $rows = [];
        foreach ($templates as $template) {
            $rows[] = [
                $template->id,
                $template->name,
                $template->cpf,
                $template->department ?? 'N/A',
                $template->finger_position,
                $template->quality_score,
                $template->capture_method,
                $template->enrolled_at,
                $template->last_used_at ?? 'Nunca',
                $template->usage_count,
            ];
        }

        return csv_download_response(
            $this->response,
            $filename,
            [
                'ID',
                'Colaborador',
                'CPF',
                'Departamento',
                'Dedo',
                'Qualidade',
                'Método de Captura',
                'Cadastrado em',
                'Último Uso',
                'Uso (contagem)',
            ],
            $rows
        );
    }
}
