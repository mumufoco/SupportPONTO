<?php

namespace App\Services\Timesheet\Endpoint;

use App\Services\QRCodeService;

class TimePunchEndpointQrService
{
    public function __construct(private readonly QRCodeService $qrCodeService = new QRCodeService())
    {
    }

    public function generateForEmployee(int $employeeId): array
    {
        $qrData = $this->qrCodeService->generateQRCode($employeeId, true);

        return [
            'success' => true,
            'status' => 200,
            'message' => 'QR Code gerado com sucesso.',
            'data' => [
                'qr_data' => $qrData['token'],
                'qr_image' => $qrData['qr_image'],
                'expires_at' => $qrData['expires_at'],
            ],
        ];
    }
}
