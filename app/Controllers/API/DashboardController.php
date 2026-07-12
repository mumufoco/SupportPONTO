<?php

namespace App\Controllers\API;

use App\Services\Analytics\DashboardService;

/**
 * Dashboard API Controller
 */
class DashboardController extends BaseApiController
{
    protected $format = 'json';

    protected DashboardService $dashboardService;

    public function __construct()
    {
        parent::__construct();
        $this->dashboardService = new DashboardService();
    }

    public function index()
    {
        if (! $this->getAuthenticatedEmployeeId()) {
            return $this->failUnauthorized('Authentication required');
        }

        $filters = [
            'startDate' => $this->request->getGet('start_date') ?? date('Y-m-d'),
            'endDate' => $this->request->getGet('end_date') ?? date('Y-m-d'),
            'departmentId' => $this->request->getGet('department_id') ? (int) $this->request->getGet('department_id') : null,
        ];

        return $this->respondStandard($this->dashboardService->getDashboardData($filters));
    }

    public function kpis()
    {
        if (! $this->getAuthenticatedEmployeeId()) {
            return $this->failUnauthorized('Authentication required');
        }

        $filters = [
            'startDate' => $this->request->getGet('start_date') ?? date('Y-m-d'),
            'endDate' => $this->request->getGet('end_date') ?? date('Y-m-d'),
            'departmentId' => $this->request->getGet('department_id') ? (int) $this->request->getGet('department_id') : null,
        ];

        $kpis = $this->dashboardService->getOverviewKPIs(
            $filters['startDate'],
            $filters['endDate'],
            $filters['departmentId']
        );

        return $this->respondStandard($kpis);
    }

    public function charts()
    {
        if (! $this->getAuthenticatedEmployeeId()) {
            return $this->failUnauthorized('Authentication required');
        }

        $startDate = $this->request->getGet('start_date') ?? date('Y-m-d');
        $endDate = $this->request->getGet('end_date') ?? date('Y-m-d');
        $departmentId = $this->request->getGet('department_id') ? (int) $this->request->getGet('department_id') : null;

        return $this->respondStandard([
            'punches_by_hour' => $this->dashboardService->getPunchesByHour($startDate, $departmentId),
            'hours_by_department' => $this->dashboardService->getHoursByDepartment($startDate, $endDate),
            'employee_status' => $this->dashboardService->getEmployeeStatusDistribution($departmentId),
        ]);
    }

    public function activity()
    {
        if (! $this->getAuthenticatedEmployeeId()) {
            return $this->failUnauthorized('Authentication required');
        }

        $limit = $this->request->getGet('limit') ? (int) $this->request->getGet('limit') : 10;
        $departmentId = $this->request->getGet('department_id') ? (int) $this->request->getGet('department_id') : null;

        return $this->respondStandard($this->dashboardService->getRecentActivity($limit, $departmentId));
    }

    public function topEmployees()
    {
        if (! $this->getAuthenticatedEmployeeId()) {
            return $this->failUnauthorized('Authentication required');
        }

        $startDate = $this->request->getGet('start_date') ?? date('Y-m-d');
        $endDate = $this->request->getGet('end_date') ?? date('Y-m-d');
        $limit = $this->request->getGet('limit') ? (int) $this->request->getGet('limit') : 10;
        $departmentId = $this->request->getGet('department_id') ? (int) $this->request->getGet('department_id') : null;

        return $this->respondStandard($this->dashboardService->getTopEmployeesByHours(
            $startDate,
            $endDate,
            $limit,
            $departmentId
        ));
    }

    public function attendance()
    {
        if (! $this->getAuthenticatedEmployeeId()) {
            return $this->failUnauthorized('Authentication required');
        }

        $startDate = $this->request->getGet('start_date') ?? date('Y-m-d');
        $endDate = $this->request->getGet('end_date') ?? date('Y-m-d');
        $departmentId = $this->request->getGet('department_id') ? (int) $this->request->getGet('department_id') : null;
        $rate = $this->dashboardService->getAttendanceRate($startDate, $endDate, $departmentId);

        return $this->respondStandard([
            'attendance_rate' => $rate,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    public function departments()
    {
        if (! $this->getAuthenticatedEmployeeId()) {
            return $this->failUnauthorized('Authentication required');
        }

        return $this->respondStandard($this->dashboardService->getDepartments());
    }
}
