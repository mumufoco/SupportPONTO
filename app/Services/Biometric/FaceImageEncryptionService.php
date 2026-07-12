<?php

namespace App\Services\Biometric;

use App\Services\Security\EncryptionService;
use App\Support\BootstrapEnv;

/**
 * v1.1.279 — Criptografia at-rest de imagens faciais
 *
 * Criptografa e descriptografa arquivos de imagem facial armazenados pelo
 * sistema PHP. Para o volume do DeepFace (armazenamento direto em Python),
 * recomenda-se criptografia a nível de host via LUKS ou dm-crypt.
 *
 * Os arquivos são criptografados com XChaCha20-Poly1305 (via EncryptionService)
 * e armazenados com extensão .enc. A descriptografia ocorre em arquivo
 * temporário quando necessário para processamento.
 */
class FaceImageEncryptionService
{
    private EncryptionService $encryptionService;
    private bool $enabled;
    private string $encryptedDir;

    public function __construct()
    {
        $this->encryptionService = new EncryptionService();
        $this->enabled    = filter_var(BootstrapEnv::get('BIOMETRIC_ENCRYPTION_ENABLED', 'true'), FILTER_VALIDATE_BOOL);
        $this->encryptedDir = rtrim(WRITEPATH, '/') . '/biometric/encrypted_faces';

        if ($this->enabled) {
            if (!is_dir($this->encryptedDir)) {
                mkdir($this->encryptedDir, 0700, true);
            }
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Criptografa uma imagem facial e salva em armazenamento seguro.
     * Retorna o caminho do arquivo criptografado.
     *
     * @param string $sourcePath Caminho do arquivo original (pode ser destruído após)
     * @param int    $employeeId ID do colaborador (usado no nome do arquivo)
     * @throws \RuntimeException se a criptografia falhar
     */
    public function encryptAndStore(string $sourcePath, int $employeeId): string
    {
        if (!$this->enabled) {
            return $sourcePath;
        }

        if (!file_exists($sourcePath) || !is_readable($sourcePath)) {
            throw new \RuntimeException("Arquivo de imagem não encontrado: {$sourcePath}");
        }

        $rawImage      = file_get_contents($sourcePath);
        if ($rawImage === false) {
            throw new \RuntimeException("Não foi possível ler o arquivo: {$sourcePath}");
        }

        $encrypted     = $this->encryptionService->encrypt($rawImage);
        $encryptedPath = $this->encryptedDir . '/' . $employeeId . '_face_' . date('YmdHis') . '.enc';

        if (file_put_contents($encryptedPath, $encrypted) === false) {
            throw new \RuntimeException("Não foi possível salvar o arquivo criptografado: {$encryptedPath}");
        }

        chmod($encryptedPath, 0600);

        log_message('info', "[FaceImageEncryptionService] Imagem facial criptografada para employee #{$employeeId}: {$encryptedPath}");

        return $encryptedPath;
    }

    /**
     * Descriptografa uma imagem facial para um arquivo temporário.
     * O chamador é responsável por deletar o arquivo temporário após o uso.
     *
     * @param  string $encryptedPath Caminho do arquivo .enc
     * @return string Caminho do arquivo temporário descriptografado
     * @throws \RuntimeException se a descriptografia falhar
     */
    public function decryptToTemp(string $encryptedPath): string
    {
        if (!$this->enabled) {
            return $encryptedPath;
        }

        if (!file_exists($encryptedPath) || !is_readable($encryptedPath)) {
            throw new \RuntimeException("Arquivo criptografado não encontrado: {$encryptedPath}");
        }

        $encrypted = file_get_contents($encryptedPath);
        if ($encrypted === false) {
            throw new \RuntimeException("Não foi possível ler o arquivo criptografado: {$encryptedPath}");
        }

        $decrypted = $this->encryptionService->decrypt($encrypted);

        $tempPath  = sys_get_temp_dir() . '/sp_face_' . bin2hex(random_bytes(8)) . '.jpg';

        if (file_put_contents($tempPath, $decrypted) === false) {
            throw new \RuntimeException("Não foi possível criar arquivo temporário para descriptografia.");
        }

        chmod($tempPath, 0600);

        return $tempPath;
    }

    /**
     * Remove de forma segura um arquivo criptografado.
     */
    public function secureDelete(string $encryptedPath): bool
    {
        if (!file_exists($encryptedPath)) {
            return true;
        }

        // Sobrescrever com zeros antes de deletar (mitigação forense)
        $size = filesize($encryptedPath);
        if ($size > 0) {
            file_put_contents($encryptedPath, str_repeat("\0", $size));
        }

        return unlink($encryptedPath);
    }

    /**
     * Lista todos os arquivos criptografados de um colaborador.
     */
    public function listForEmployee(int $employeeId): array
    {
        $pattern = $this->encryptedDir . '/' . $employeeId . '_face_*.enc';
        return glob($pattern) ?: [];
    }

    /**
     * Remove todos os arquivos criptografados de um colaborador.
     * Usado no fluxo de anonimização LGPD.
     */
    public function purgeForEmployee(int $employeeId): int
    {
        $files   = $this->listForEmployee($employeeId);
        $deleted = 0;

        foreach ($files as $file) {
            if ($this->secureDelete($file)) {
                $deleted++;
            }
        }

        if ($deleted > 0) {
            log_message('info', "[FaceImageEncryptionService] {$deleted} imagem(ns) facial(is) purgada(s) para employee #{$employeeId}");
        }

        return $deleted;
    }

    /**
     * Verifica integridade do armazenamento criptografado.
     * Tenta descriptografar cada arquivo para confirmar que a chave atual os abre.
     */
    public function verifyStorageIntegrity(): array
    {
        if (!$this->enabled) {
            return ['enabled' => false, 'checked' => 0, 'corrupted' => []];
        }

        $allFiles  = glob($this->encryptedDir . '/*.enc') ?: [];
        $corrupted = [];

        foreach ($allFiles as $file) {
            try {
                $encrypted = file_get_contents($file);
                if ($encrypted === false) {
                    $corrupted[] = basename($file);
                    continue;
                }
                $this->encryptionService->decrypt($encrypted);
            } catch (\Throwable) {
                $corrupted[] = basename($file);
            }
        }

        return [
            'enabled'    => true,
            'checked'    => count($allFiles),
            'corrupted'  => $corrupted,
            'healthy'    => empty($corrupted),
        ];
    }
}
