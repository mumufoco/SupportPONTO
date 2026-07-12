<?php

namespace App\Services\Timesheet\Endpoint;

class TimePunchEndpointResultFactory
{
    public function error(string $message, int $status): array
    {
        return [
            'success' => false,
            'status' => $status,
            'message' => $message,
            'errors' => null,
        ];
    }
}
