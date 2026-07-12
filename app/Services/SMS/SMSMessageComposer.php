<?php

namespace App\Services\SMS;

class SMSMessageComposer
{
    public function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function verificationMessage(string $code): string
    {
        return "Seu código de verificação é: {$code}\nVálido por 5 minutos.";
    }

    public function maskPhone(string $phone): string
    {
        $cleaned = preg_replace('/\D/', '', $phone) ?: '';

        if (strlen($cleaned) === 11 || strlen($cleaned) === 10) {
            return '(' . substr($cleaned, 0, 2) . ') ****-' . substr($cleaned, -4);
        }

        return '****-' . substr($cleaned, -4);
    }
}
