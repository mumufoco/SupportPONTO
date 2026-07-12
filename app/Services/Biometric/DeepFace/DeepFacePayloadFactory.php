<?php

namespace App\Services\Biometric\DeepFace;

class DeepFacePayloadFactory
{
    public function forEnroll(int $employeeId, string $photoBase64): array
    {
        return [
            'employee_id' => $employeeId,
            'photo' => $photoBase64,
        ];
    }

    public function forRecognize(string $photoBase64, float $threshold): array
    {
        return [
            'photo' => $photoBase64,
            'threshold' => $threshold,
        ];
    }

    public function forVerify(int $employeeId, string $photoBase64): array
    {
        return [
            'employee_id' => $employeeId,
            'photo' => $photoBase64,
        ];
    }

    public function forAnalyze(string $photoBase64): array
    {
        return ['photo' => $photoBase64];
    }

    public function forDeleteByHash(string $imageHash): array
    {
        return ['image_hash' => $imageHash];
    }
}
