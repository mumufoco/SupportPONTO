<?php

namespace App\Services\Settings\Catalog;

class CatalogSettingsActionService
{
    public function togglePayload(?bool $active, string $notFoundMessage): array
    {
        if ($active === null) {
            return ['success' => false, 'message' => $notFoundMessage];
        }

        return [
            'success'   => true,
            'message'   => 'Status alterado com sucesso',
            'active'    => $active,
            'csrf_hash' => csrf_hash(),
        ];
    }

    public function persistResult(bool $success, array $errors, string $logContext, string $errorMessage): array
    {
        if ($success) {
            return ['success' => true];
        }

        log_message('error', $logContext . ': ' . json_encode($errors));

        return [
            'success'       => false,
            'errors'        => $errors,
            'error_message' => $errorMessage,
        ];
    }
}
