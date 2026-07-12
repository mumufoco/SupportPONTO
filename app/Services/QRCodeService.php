<?php

namespace App\Services;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Common\Version;
use chillerlan\QRCode\Output\QRGdImagePNG;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\EmployeeModel;

class QRCodeService
{
    private const TOKEN_ISSUER = 'supportponto';
    private const TOKEN_AUDIENCE = 'supportponto-terminal';

    protected string $secretKey;
    protected int $tokenExpiration = 300;
    protected EmployeeModel $employeeModel;

    public function __construct()
    {
        $secret = env('JWT_SECRET_KEY');
        if (!is_string($secret) || trim($secret) === '') {
            throw new \RuntimeException('JWT_SECRET_KEY must be configured for QR Code generation.');
        }

        $this->secretKey = trim($secret);
        $this->employeeModel = new EmployeeModel();
    }

    public function generateToken(int $employeeId): array
    {
        $employee = $this->employeeModel->find($employeeId);
        
        if (!$employee) {
            throw new \Exception('Colaborador não encontrado');
        }

        $jti = bin2hex(random_bytes(16));
        $now = time();
        
        $payload = [
            'iss' => self::TOKEN_ISSUER,
            'aud' => self::TOKEN_AUDIENCE,
            'iat' => $now,
            'exp' => $now + $this->tokenExpiration,
            'jti' => $jti,
            'sub' => $employeeId,
            'emp' => [
                'id' => $employee->id,
                'code' => $employee->employee_code ?? null,
                'name' => $employee->name,
            ],
        ];

        $token = JWT::encode($payload, $this->secretKey, 'HS256');

        return [
            'token' => $token,
            'jti' => $jti,
            'expires_at' => date('Y-m-d H:i:s', $now + $this->tokenExpiration),
            'employee' => $employee,
        ];
    }

    public function generateQRCode(int $employeeId, bool $asDataUri = true): array
    {
        $tokenData = $this->generateToken($employeeId);

        $options = new QROptions([
            'version'          => Version::AUTO,
            'outputInterface'  => QRGdImagePNG::class,
            'eccLevel'         => 'H',
            'scale'            => 5,
            'outputBase64'     => $asDataUri,
            'addQuietzone'     => true,
            'quietzoneSize'    => 2,
        ]);

        $qrcode = new QRCode($options);
        $qrImage = $qrcode->render($tokenData['token']);

        return [
            'qr_image' => $qrImage,
            'token' => $tokenData['token'],
            'jti' => $tokenData['jti'],
            'expires_at' => $tokenData['expires_at'],
            'employee' => $tokenData['employee'],
        ];
    }

    public function validateToken(string $token): array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, 'HS256'));
            
            if ($decoded->iss !== self::TOKEN_ISSUER) {
                return ['valid' => false, 'error' => 'Token com emissor inválido'];
            }

            if ($decoded->aud !== self::TOKEN_AUDIENCE) {
                return ['valid' => false, 'error' => 'Token com audiência inválida'];
            }

            if ($this->isTokenUsed($decoded->jti)) {
                return ['valid' => false, 'error' => 'QR Code já foi utilizado'];
            }

            $employee = $this->employeeModel->find($decoded->sub);
            
            if (!$employee) {
                return ['valid' => false, 'error' => 'Colaborador não encontrado'];
            }

            if (!$employee->active) {
                return ['valid' => false, 'error' => 'Colaborador inativo'];
            }

            return [
                'valid' => true,
                'employee' => $employee,
                'jti' => $decoded->jti,
                'payload' => $decoded,
            ];

        } catch (\Firebase\JWT\ExpiredException $e) {
            return ['valid' => false, 'error' => 'QR Code expirado'];
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            return ['valid' => false, 'error' => 'QR Code com assinatura inválida'];
        } catch (\Exception $e) {
            log_message('warning', 'QR code token validation failed: {message}', ['message' => $e->getMessage()]);
            return ['valid' => false, 'error' => 'QR Code inválido'];
        }
    }

    public function markTokenAsUsed(string $jti, int $employeeId): bool
    {
        $db = \Config\Database::connect();
        
        try {
            $db->table('qrcode_used_tokens')->insert([
                'jti' => $jti,
                'employee_id' => $employeeId,
                'used_at' => date('Y-m-d H:i:s'),
            ]);
            return true;
        } catch (\Exception $e) {
            log_message('error', 'Failed to mark token as used: ' . $e->getMessage());
            return false;
        }
    }

    protected function isTokenUsed(string $jti): bool
    {
        $db = \Config\Database::connect();
        $result = $db->table('qrcode_used_tokens')
            ->where('jti', $jti)
            ->countAllResults();
        
        return $result > 0;
    }

    public function cleanupExpiredTokens(): int
    {
        $db = \Config\Database::connect();
        $cutoff = date('Y-m-d H:i:s', strtotime('-1 day'));
        
        return $db->table('qrcode_used_tokens')
            ->where('used_at <', $cutoff)
            ->delete();
    }

    public function setTokenExpiration(int $seconds): self
    {
        $this->tokenExpiration = $seconds;
        return $this;
    }

    public function getTokenExpiration(): int
    {
        return $this->tokenExpiration;
    }
}
