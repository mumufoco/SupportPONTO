<?php

namespace App\Services\Security;

use App\Support\BootstrapEnv;
use CodeIgniter\Config\Services;

/**
 * Encryption Service
 *
 * Provides symmetric encryption/decryption using Sodium (libsodium)
 * for sensitive settings and data.
 *
 * Uses XChaCha20-Poly1305 (AEAD - Authenticated Encryption with Associated Data)
 * which is more secure than AES-256-GCM for most use cases.
 *
 * @package App\Services\Security
 */
class EncryptionService
{
    /**
     * Encryption key (32 bytes)
     * @var string|null
     */
    protected ?string $key = null;

    /**
     * Encryption key version (for key rotation)
     * @var int
     */
    protected int $keyVersion = 1;

    /**
     * Constructor
     *
     * @throws \RuntimeException if Sodium extension not available
     */
    public function __construct()
    {
        if (!extension_loaded('sodium')) {
            throw new \RuntimeException('Sodium extension is not loaded. PHP 7.2+ required.');
        }

        $this->loadKey();
    }

    /**
     * Load encryption key from environment
     *
     * @throws \RuntimeException if key is not configured
     */
    protected function loadKey(): void
    {
        $key = BootstrapEnv::encryptionKey();

        if (!$key) {
            throw new \RuntimeException(
                'encryption.key / ENCRYPTION_KEY not configured in .env. ' .
                'Generate one with: php spark encryption:generate-key'
            );
        }

        $normalizedKey = (string) $key;
        if (str_starts_with($normalizedKey, 'base64:')) {
            $normalizedKey = substr($normalizedKey, 7);
        } elseif (str_starts_with($normalizedKey, 'hex2bin:')) {
            $hex = substr($normalizedKey, 8);
            $decodedKey = ctype_xdigit($hex) ? hex2bin($hex) : false;
            if ($decodedKey === false || strlen($decodedKey) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
                throw new \RuntimeException(
                    'Invalid encryption.key. hex2bin payload must decode to ' . SODIUM_CRYPTO_SECRETBOX_KEYBYTES . ' bytes.'
                );
            }
            $this->key = $decodedKey;
            $version = BootstrapEnv::get('ENCRYPTION_KEY_VERSION');
            if ($version !== false && $version !== null && is_numeric($version)) {
                $this->keyVersion = (int) $version;
            }
            return;
        }

        $decodedKey = base64_decode($normalizedKey, true);

        if ($decodedKey === false || strlen($decodedKey) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \RuntimeException(
                'Invalid encryption.key / ENCRYPTION_KEY. Must decode to ' . SODIUM_CRYPTO_SECRETBOX_KEYBYTES . ' bytes.'
            );
        }

        $this->key = $decodedKey;

        $version = BootstrapEnv::get('ENCRYPTION_KEY_VERSION');
        if ($version !== false && $version !== null && is_numeric($version)) {
            $this->keyVersion = (int) $version;
        }
    }

    /**
     * Encrypt data
     *
     * Returns base64-encoded encrypted data with format:
     * [version:1byte][nonce:24bytes][ciphertext:variable][tag:16bytes]
     *
     * @param string $plaintext Data to encrypt
     * @return string Base64-encoded encrypted data
     * @throws \SodiumException
     */
    public function encrypt(string $plaintext): string
    {
        if (empty($plaintext)) {
            throw new \InvalidArgumentException('Cannot encrypt empty string');
        }

        // Generate random nonce (24 bytes for XChaCha20)
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        // Encrypt using XChaCha20-Poly1305
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->key);

        // Prepend version byte (for future key rotation)
        $versionByte = chr($this->keyVersion);

        // Combine: version + nonce + ciphertext (which includes auth tag)
        $encrypted = $versionByte . $nonce . $ciphertext;

        // Clean up memory
        sodium_memzero($plaintext);

        // Return base64 encoded
        return base64_encode($encrypted);
    }

    /**
     * Decrypt data
     *
     * @param string $encryptedData Base64-encoded encrypted data
     * @return string Decrypted plaintext
     * @throws \InvalidArgumentException if data is invalid
     * @throws \RuntimeException if decryption fails
     * @throws \SodiumException
     */
    public function decrypt(string $encryptedData): string
    {
        if (empty($encryptedData)) {
            throw new \InvalidArgumentException('Cannot decrypt empty string');
        }

        // Decode from base64
        $decoded = base64_decode($encryptedData, true);

        if ($decoded === false) {
            throw new \InvalidArgumentException('Invalid base64 encrypted data');
        }

        // Minimum length: 1 (version) + 24 (nonce) + 16 (tag) = 41 bytes
        if (strlen($decoded) < 41) {
            throw new \InvalidArgumentException('Encrypted data too short');
        }

        // Extract version
        $version = ord($decoded[0]);

        // Check version (for future key rotation support)
        if ($version !== $this->keyVersion) {
            log_message('warning', "Encrypted data uses key version {$version}, current version is {$this->keyVersion}");
            // In future, we could support multiple keys for rotation
            // For now, we'll try to decrypt with current key
        }

        // Extract nonce (24 bytes)
        $nonce = substr($decoded, 1, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        // Extract ciphertext (remaining bytes)
        $ciphertext = substr($decoded, 1 + SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        // Decrypt
        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed. Data may be corrupted or tampered with.');
        }

        // Clean up memory
        sodium_memzero($ciphertext);

        return $plaintext;
    }

    /**
     * Encrypt array/object as JSON
     *
     * @param array|object $data Data to encrypt
     * @return string Base64-encoded encrypted JSON
     * @throws \SodiumException
     */
    public function encryptJson($data): string
    {
        $json = json_encode($data);

        if ($json === false) {
            throw new \InvalidArgumentException('Failed to encode data as JSON');
        }

        return $this->encrypt($json);
    }

    /**
     * Decrypt JSON to array
     *
     * @param string $encryptedData Base64-encoded encrypted JSON
     * @param bool $associative Return as associative array (default true)
     * @return array|object Decrypted data
     * @throws \RuntimeException
     * @throws \SodiumException
     */
    public function decryptJson(string $encryptedData, bool $associative = true)
    {
        $json = $this->decrypt($encryptedData);

        $data = json_decode($json, $associative);

        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to decode decrypted JSON: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Generate a new encryption key
     *
     * @return string Base64-encoded key (ready for .env)
     */
    public static function generateKey(): string
    {
        $key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        return base64_encode($key);
    }

    /**
     * Hash data (one-way, cannot be decrypted)
     *
     * Uses Argon2id for password hashing
     *
     * @param string $data Data to hash
     * @return string Hashed data
     * @throws \SodiumException
     */
    public function hash(string $data): string
    {
        return sodium_crypto_pwhash_str(
            $data,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE
        );
    }

    /**
     * Verify hashed data
     *
     * @param string $data Original data
     * @param string $hash Hash to verify against
     * @return bool True if hash matches
     * @throws \SodiumException
     */
    public function verifyHash(string $data, string $hash): bool
    {
        return sodium_crypto_pwhash_str_verify($hash, $data);
    }

    /**
     * Check if hash needs rehash (e.g., parameters changed)
     *
     * @param string $hash Hash to check
     * @return bool True if needs rehash
     */
    public function needsRehash(string $hash): bool
    {
        return sodium_crypto_pwhash_str_needs_rehash(
            $hash,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE
        );
    }

    /**
     * Secure compare two strings (timing-safe)
     *
     * Prevents timing attacks when comparing secrets
     *
     * @param string $a First string
     * @param string $b Second string
     * @return bool True if strings are equal
     */
    public function secureCompare(string $a, string $b): bool
    {
        if (strlen($a) !== strlen($b)) {
            return false;
        }

        return sodium_memcmp($a, $b) === 0;
    }

    /**
     * Generate random token
     *
     * @param int $length Length in bytes (default 32)
     * @return string Base64-encoded random token
     */
    public function generateToken(int $length = 32): string
    {
        if ($length < 16) {
            throw new \InvalidArgumentException('Token length must be at least 16 bytes');
        }

        return base64_encode(random_bytes($length));
    }

    /**
     * Clean up sensitive data from memory
     *
     * Called automatically on destruction, but can be called manually
     */
    public function cleanup(): void
    {
        if ($this->key !== null) {
            sodium_memzero($this->key);
        }
    }

    /**
     * Destructor - clean up key from memory
     */
    public function __destruct()
    {
        $this->cleanup();
    }
}
