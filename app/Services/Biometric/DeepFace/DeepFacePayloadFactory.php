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

    public function forVerify(string $photo1Base64, string $photo2Base64): array
    {
        return [
            'photo1' => $photo1Base64,
            'photo2' => $photo2Base64,
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
