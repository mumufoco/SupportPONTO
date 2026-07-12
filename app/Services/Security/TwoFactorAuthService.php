<?php

namespace App\Services\Security;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Two-Factor Authentication Service
 *
 * Implements TOTP (Time-based One-Time Password) for 2FA
 * Compatible with Google Authenticator, Authy, Microsoft Authenticator, etc.
 *
 * Based on RFC 6238 (TOTP) and RFC 4226 (HOTP)
 *
 * @package App\Services\Security
 */
class TwoFactorAuthService
{
    /**
     * Number of digits in OTP code
     * @var int
     */
    protected int $digits = 6;

    /**
     * Time step in seconds (30 seconds is standard)
     * @var int
     */
    protected int $period = 30;

    /**
     * Hash algorithm (sha1 is standard for TOTP)
     * @var string
     */
    protected string $algorithm = 'sha1';

    /**
     * Window size for time drift tolerance
     * Allows codes from previous/next time windows
     * @var int
     */
    protected int $window = 1;

    /**
     * Issuer name (app name) for QR codes
     * @var string
     */
    protected string $issuer;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->issuer = getenv('COMPANY_NAME') ?: 'Sistema Ponto Eletrônico';
    }

    /**
     * Generate a new secret key for 2FA
     *
     * @param int $length Secret length in bytes (default 20 bytes = 160 bits)
     * @return string Base32-encoded secret
     */
    public function generateSecret(int $length = 20): string
    {
        $randomBytes = random_bytes($length);
        return $this->base32Encode($randomBytes);
    }

    /**
     * Generate TOTP code for current time
     *
     * @param string $secret Base32-encoded secret
     * @param int|null $timestamp Unix timestamp (null = current time)
     * @return string 6-digit code
     */
    public function generateCode(string $secret, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();

        // Calculate time counter
        $counter = (int)floor($timestamp / $this->period);

        return $this->generateHOTP($secret, $counter);
    }

    /**
     * Verify TOTP code
     *
     * @param string $secret Base32-encoded secret
     * @param string $code User-provided code
     * @param int|null $timestamp Unix timestamp (null = current time)
     * @return bool True if code is valid
     */
    public function verifyCode(string $secret, string $code, ?int $timestamp = null): bool
    {
        $timestamp = $timestamp ?? time();
        $counter = (int)floor($timestamp / $this->period);

        // Check current time window
        if ($this->generateHOTP($secret, $counter) === $code) {
            return true;
        }

        // Check previous/next time windows (tolerance for clock drift)
        for ($i = 1; $i <= $this->window; $i++) {
            // Check previous window
            if ($this->generateHOTP($secret, $counter - $i) === $code) {
                return true;
            }

            // Check next window
            if ($this->generateHOTP($secret, $counter + $i) === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate QR code data URI for authenticator apps
     *
     * @param string $secret Base32-encoded secret
     * @param string $accountName User identifier (email, username, etc.)
     * @return string Data URI for QR code image
     */
    public function getQRCodeDataUri(string $secret, string $accountName): string
    {
        $otpauthUrl = $this->getOTPAuthURL($secret, $accountName);

        // Generate QR code using a simple implementation
        // For production, consider using a library like endroid/qr-code
        return $this->generateQRCodeDataUri($otpauthUrl);
    }

    /**
     * Get OTPAuth URL for QR code
     *
     * Format: otpauth://totp/ISSUER:ACCOUNT?secret=SECRET&issuer=ISSUER
     *
     * @param string $secret Base32-encoded secret
     * @param string $accountName User identifier
     * @return string OTPAuth URL
     */
    public function getOTPAuthURL(string $secret, string $accountName): string
    {
        $label = rawurlencode($this->issuer) . ':' . rawurlencode($accountName);

        $params = [
            'secret' => $secret,
            'issuer' => $this->issuer,
            'algorithm' => strtoupper($this->algorithm),
            'digits' => $this->digits,
            'period' => $this->period,
        ];

        return 'otpauth://totp/' . $label . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Generate backup codes for account recovery
     *
     * @param int $count Number of backup codes to generate
     * @return array Array of backup codes
     */
    public function generateBackupCodes(int $count = 10): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            // Generate 8-digit code
            $code = '';
            for ($j = 0; $j < 8; $j++) {
                $code .= random_int(0, 9);
            }

            // Format as XXXX-XXXX
            $formatted = substr($code, 0, 4) . '-' . substr($code, 4, 4);
            $codes[] = $formatted;
        }

        return $codes;
    }

    /**
     * Hash backup code for storage
     *
     * @param string $code Backup code
     * @return string Hashed code
     */
    public function hashBackupCode(string $code): string
    {
        // Remove hyphen
        $code = str_replace('-', '', $code);

        return password_hash($code, PASSWORD_ARGON2ID);
    }

    /**
     * Verify backup code
     *
     * @param string $code User-provided code
     * @param string $hash Stored hash
     * @return bool True if code matches
     */
    public function verifyBackupCode(string $code, string $hash): bool
    {
        // Remove hyphen
        $code = str_replace('-', '', $code);

        return password_verify($code, $hash);
    }

    /**
     * Generate HOTP code (HMAC-based One-Time Password)
     *
     * @param string $secret Base32-encoded secret
     * @param int $counter Counter value
     * @return string N-digit code
     */
    protected function generateHOTP(string $secret, int $counter): string
    {
        // Decode secret from base32
        $key = $this->base32Decode($secret);

        // Convert counter to binary string (8 bytes, big-endian)
        $counterBytes = pack('J', $counter);

        // Generate HMAC
        $hash = hash_hmac($this->algorithm, $counterBytes, $key, true);

        // Dynamic truncation (RFC 4226)
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $truncated = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        // Generate N-digit code
        $otp = $truncated % (10 ** $this->digits);

        return str_pad((string)$otp, $this->digits, '0', STR_PAD_LEFT);
    }

    /**
     * Encode data to Base32
     *
     * @param string $data Binary data
     * @return string Base32-encoded string
     */
    protected function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $output = '';
        $v = 0;
        $vbits = 0;

        for ($i = 0, $j = strlen($data); $i < $j; $i++) {
            $v <<= 8;
            $v += ord($data[$i]);
            $vbits += 8;

            while ($vbits >= 5) {
                $vbits -= 5;
                $output .= $alphabet[($v >> $vbits) & 31];
            }
        }

        if ($vbits > 0) {
            $v <<= (5 - $vbits);
            $output .= $alphabet[$v & 31];
        }

        return $output;
    }

    /**
     * Decode Base32 string
     *
     * @param string $data Base32-encoded string
     * @return string Binary data
     */
    protected function base32Decode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $data = strtoupper($data);
        $output = '';
        $v = 0;
        $vbits = 0;

        for ($i = 0, $j = strlen($data); $i < $j; $i++) {
            $v <<= 5;

            $char = $data[$i];
            if ($char === '=') {
                continue;
            }

            $pos = strpos($alphabet, $char);
            if ($pos === false) {
                throw new \InvalidArgumentException('Invalid base32 character: ' . $char);
            }

            $v += $pos;
            $vbits += 5;

            if ($vbits >= 8) {
                $vbits -= 8;
                $output .= chr(($v >> $vbits) & 255);
            }
        }

        return $output;
    }

    /**
     * Generate QR code data URI
     *
     * Simple inline QR code generator
     * For production, use a proper library like endroid/qr-code
     *
     * @param string $data Data to encode
     * @return string Data URI
     */
    protected function generateQRCodeDataUri(string $data): string
    {
        if (class_exists(QRCode::class) && class_exists(QROptions::class)) {
            $options = new QROptions([
                'outputInterface' => \chillerlan\QRCode\Output\QRGdImagePNG::class,
                'eccLevel'        => \chillerlan\QRCode\Common\EccLevel::M,
                'scale'           => 8,
                'outputBase64'    => true,
                'quietzoneSize'   => 4,
            ]);

            return (new QRCode($options))->render($data);
        }

        if ($this->allowsExternalQrFallback()) {
            return 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=8&data=' . rawurlencode($data);
        }

        throw new \RuntimeException(
            'A biblioteca local de QR Code é obrigatória para o setup de 2FA em produção. Instale as dependências do Composer antes de habilitar o fluxo.'
        );
    }

    protected function allowsExternalQrFallback(): bool
    {
        $flag = getenv('TWO_FACTOR_ALLOW_EXTERNAL_QR_FALLBACK');
        $allowExternalFallback = $flag === false
            ? true
            : filter_var($flag, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($allowExternalFallback === null) {
            $allowExternalFallback = true;
        }

        return ! $this->isProductionEnvironment() && $allowExternalFallback;
    }

    protected function isProductionEnvironment(): bool
    {
        if (defined('ENVIRONMENT')) {
            return ENVIRONMENT === 'production';
        }

        return (getenv('CI_ENVIRONMENT') ?: getenv('APP_ENV') ?: 'production') === 'production';
    }

    /**
     * Get current Unix timestamp (for testing)
     *
     * @return int Current timestamp
     */
    protected function getCurrentTimestamp(): int
    {
        return time();
    }

    /**
     * Set time window for code validation
     *
     * @param int $window Window size (0-5 recommended)
     * @return self
     */
    public function setWindow(int $window): self
    {
        if ($window < 0 || $window > 10) {
            throw new \InvalidArgumentException('Window must be between 0 and 10');
        }

        $this->window = $window;
        return $this;
    }

    /**
     * Set issuer name
     *
     * @param string $issuer Issuer name
     * @return self
     */
    public function setIssuer(string $issuer): self
    {
        $this->issuer = $issuer;
        return $this;
    }
}
