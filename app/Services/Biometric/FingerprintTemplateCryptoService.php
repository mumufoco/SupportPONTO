<?php

namespace App\Services\Biometric;

use CodeIgniter\Config\Services;
use Exception;

class FingerprintTemplateCryptoService
{
    public function encrypt(string $template): string|false
    {
        try {
            return base64_encode(Services::encrypter()->encrypt($template));
        } catch (Exception $e) {
            log_message('error', 'Template encryption error: ' . $e->getMessage());
            return false;
        }
    }

    public function decrypt(string $encryptedTemplate): string|false
    {
        try {
            return Services::encrypter()->decrypt(base64_decode($encryptedTemplate));
        } catch (Exception $e) {
            log_message('error', 'Template decryption error: ' . $e->getMessage());
            return false;
        }
    }

    public function hash(string $template): string
    {
        return hash('sha256', $template);
    }
}
