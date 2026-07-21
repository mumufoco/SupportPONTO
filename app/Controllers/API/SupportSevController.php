<?php

namespace App\Controllers\API;

use App\Services\Employees\API\EmployeeApiService;

/**
 * Endpoints consumidos pelo SupportSEV (autenticação por token estático via
 * SupportSevApiFilter, não OAuth2 -- não há "ator" logado, é uma integração
 * sistema-a-sistema).
 */
class SupportSevController extends BaseApiController
{
    public function __construct(private readonly EmployeeApiService $employeeApiService = new EmployeeApiService())
    {
        parent::__construct();
    }

    public function team()
    {
        $result = $this->employeeApiService->teamDataForIntegration();

        return $this->respond($result, 200);
    }
}
