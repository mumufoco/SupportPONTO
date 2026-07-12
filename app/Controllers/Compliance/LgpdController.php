<?php

namespace App\Controllers\Compliance;

use App\Controllers\BaseController;
use App\Services\Compliance\LgpdService;

class LgpdController extends BaseController
{
    public function index()
    {
        $service = new LgpdService();

        $db       = \Config\Database::connect();
        $requests = $db->tableExists('lgpd_subject_requests')
            ? $db->table('lgpd_subject_requests')
                ->select('lgpd_subject_requests.*, employees.name AS employee_name, employees.cpf AS employee_cpf', false)
                ->join('employees', 'employees.id = lgpd_subject_requests.employee_id', 'left')
                ->orderBy('CASE WHEN lgpd_subject_requests.status = \'pending\' THEN 0 ELSE 1 END, lgpd_subject_requests.created_at DESC', '', false)
                ->get()->getResult()
            : [];

        // MED-11 (auditoria): employees.cpf agora fica criptografado — este JOIN cru
        // não passa por EmployeeModel::afterFind(), então precisa decriptar aqui.
        foreach ($requests as $request) {
            $request->employee_cpf = \App\Models\EmployeeModel::decryptCpfValue($request->employee_cpf ?? null);
        }

        return view('compliance/lgpd', [
            'cards'      => $service->getCards(),
            'guidelines' => $service->getGuidelines(),
            'requests'   => $requests,
        ]);
    }
}
