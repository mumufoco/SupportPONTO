<?php

namespace App\Services\Warning;

use CodeIgniter\HTTP\IncomingRequest;

class WarningControllerActionService
{
    public function createValidationRules(): array
    {
        return [
            'employee_id' => 'required|integer',
            'warning_type' => 'required|in_list[verbal,escrita,suspensao]',
            'occurrence_date' => 'required|valid_date',
            'reason' => 'required|min_length[50]|max_length[5000]',
            'evidence_files.*' => 'permit_empty|max_size[evidence_files.*,10240]|ext_in[evidence_files.*,pdf,jpg,jpeg,png,doc,docx]',
        ];
    }

    public function witnessValidationRules(): array
    {
        return [
            'witness_name' => 'required|min_length[3]|max_length[255]',
            'witness_cpf' => 'required|exact_length[14]',
            'witness_signature' => 'required',
        ];
    }

    public function createPayload(IncomingRequest $request): array
    {
        return [
            'employee_id' => (int) $request->getPost('employee_id'),
            'warning_type' => (string) $request->getPost('warning_type'),
            'occurrence_date' => (string) $request->getPost('occurrence_date'),
            'reason' => (string) $request->getPost('reason'),
        ];
    }

    public function witnessPayload(IncomingRequest $request): array
    {
        $json = $request->getJSON(true);
        if (is_array($json) && $json !== []) {
            return $json;
        }

        return $request->getPost();
    }

    public function canAddWitnessByTime(object $warning): bool
    {
        $hoursElapsed = (time() - strtotime((string) $warning->created_at)) / 3600;
        return $hoursElapsed >= 48;
    }
}
