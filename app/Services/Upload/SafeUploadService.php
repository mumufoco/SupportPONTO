<?php

namespace App\Services\Upload;

use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Serviço central para validação, armazenamento e resolução segura de uploads.
 *
 * Regras do Pacote 447:
 * - arquivos operacionais devem ficar em WRITEPATH/uploads sempre que possível;
 * - public/uploads deve receber somente imagens públicas normalizadas;
 * - extensões executáveis, HTML/SVG e tipos de script são sempre bloqueados;
 * - downloads devem passar por resolução controlada com realpath e raízes permitidas.
 */
class SafeUploadService
{
    public const MAX_DEFAULT_BYTES = 10_485_760; // 10MB

    /** @var list<string> */
    private const BLOCKED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar',
        'exe', 'dll', 'so', 'dylib', 'sh', 'bash', 'zsh', 'bat', 'cmd', 'ps1',
        'js', 'mjs', 'vbs', 'cgi', 'pl', 'py', 'rb', 'asp', 'aspx', 'jsp',
        'com', 'scr', 'msi', 'jar', 'war', 'ear', 'htaccess', 'htpasswd',
        'html', 'htm', 'xhtml', 'svg', 'xml', 'shtml', 'swf',
    ];

    /** @var array<string,list<string>> */
    private const MIME_GROUPS = [
        'image_public' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/x-icon', 'image/vnd.microsoft.icon'],
        'image_private' => ['image/jpeg', 'image/png', 'image/webp'],
        'document_private' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'text/csv',
        ],
    ];

    /** @return list<string> */
    public function blockedExtensions(): array
    {
        return self::BLOCKED_EXTENSIONS;
    }

    /** @return list<string> */
    public function allowedMimesForGroups(array $groups): array
    {
        $mimes = [];
        foreach ($groups as $group) {
            foreach (self::MIME_GROUPS[$group] ?? [] as $mime) {
                $mimes[] = $mime;
            }
        }

        return array_values(array_unique($mimes));
    }

    /**
     * @param list<string> $allowedExtensions
     * @param list<string> $allowedMimes
     * @return array{success:bool,message:string,extension:?string,mime_type:?string,size:int,client_name:string}
     */
    public function validateUploadedFile(UploadedFile $file, array $allowedExtensions, array $allowedMimes, int $maxBytes = self::MAX_DEFAULT_BYTES): array
    {
        if (!$file->isValid() || $file->hasMoved()) {
            return $this->invalid('Arquivo inválido.', null, null, (int) $file->getSize(), (string) $file->getClientName());
        }

        $size = (int) $file->getSize();
        if ($size <= 0 || $size > $maxBytes) {
            return $this->invalid('Arquivo excede o tamanho permitido.', null, null, $size, (string) $file->getClientName());
        }

        $extension = strtolower((string) ($file->getClientExtension() ?: $file->getExtension()));
        $extension = preg_replace('/[^a-z0-9]+/', '', $extension) ?: '';
        if ($extension === '' || in_array($extension, self::BLOCKED_EXTENSIONS, true) || !in_array($extension, $allowedExtensions, true)) {
            $this->audit('blocked_extension', ['extension' => $extension, 'client_name' => $file->getClientName()]);
            return $this->invalid('Extensão de arquivo não permitida.', $extension, null, $size, (string) $file->getClientName());
        }

        $realMime = $this->detectRealMime((string) $file->getTempName());
        if ($realMime === null || in_array($realMime, ['text/html', 'image/svg+xml', 'application/x-php'], true) || !in_array($realMime, $allowedMimes, true)) {
            $this->audit('blocked_mime', ['extension' => $extension, 'mime_type' => $realMime, 'client_name' => $file->getClientName()]);
            return $this->invalid('Tipo MIME não permitido.', $extension, $realMime, $size, (string) $file->getClientName());
        }

        if (str_starts_with($realMime, 'image/')) {
            $imageInfo = @getimagesize((string) $file->getTempName());
            if ($imageInfo === false) {
                $this->audit('invalid_image', ['extension' => $extension, 'mime_type' => $realMime]);
                return $this->invalid('Arquivo de imagem inválido ou corrompido.', $extension, $realMime, $size, (string) $file->getClientName());
            }
        }

        return [
            'success' => true,
            'message' => 'Arquivo validado.',
            'extension' => $extension,
            'mime_type' => $realMime,
            'size' => $size,
            'client_name' => $this->sanitizeClientName((string) $file->getClientName()),
        ];
    }

    /**
     * @param list<string> $allowedExtensions
     * @param list<string> $allowedMimes
     * @return array{success:bool,message:string,stored_path:?string,absolute_path:?string,file_name:?string,mime_type:?string,file_size:?int}
     */
    public function storePrivate(UploadedFile $file, string $relativeDirectory, array $allowedExtensions, array $allowedMimes, int $maxBytes = self::MAX_DEFAULT_BYTES): array
    {
        $validation = $this->validateUploadedFile($file, $allowedExtensions, $allowedMimes, $maxBytes);
        if (!$validation['success']) {
            return $this->storeFailure($validation['message'], $validation['mime_type'] ?? null);
        }

        $directory = $this->privateDirectory($relativeDirectory);
        if (!$this->ensureDirectory($directory, 0750)) {
            return $this->storeFailure('Não foi possível preparar o diretório de upload.', null);
        }

        $filename = $this->randomFilename((string) $validation['extension']);
        $targetPath = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        try {
            $file->move($directory, $filename);
        } catch (\Throwable $e) {
            $this->audit('move_failed', ['exception' => $e->getMessage(), 'dir' => $relativeDirectory]);
            return $this->storeFailure('Erro ao processar o arquivo.', null);
        }

        $realMime = $this->detectRealMime($targetPath);
        if ($realMime === null || !in_array($realMime, $allowedMimes, true)) {
            @unlink($targetPath);
            $this->audit('post_move_mime_blocked', ['mime_type' => $realMime, 'path' => $relativeDirectory]);
            return $this->storeFailure('Tipo de arquivo não permitido.', $realMime);
        }

        @chmod($targetPath, 0640);

        $storedPath = trim('uploads/' . trim($relativeDirectory, '/\\') . '/' . $filename, '/');
        $this->audit('stored_private', ['stored_path' => $storedPath, 'mime_type' => $realMime, 'size' => @filesize($targetPath) ?: null]);

        return [
            'success' => true,
            'message' => 'Arquivo enviado com sucesso.',
            'stored_path' => $storedPath,
            'absolute_path' => $targetPath,
            'file_name' => (string) $validation['client_name'],
            'mime_type' => $realMime,
            'file_size' => @filesize($targetPath) ?: null,
        ];
    }

    public function detectRealMime(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = @finfo_file($finfo, $path);
                @finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return $mime;
                }
            }
        }

        $mime = @mime_content_type($path);
        return is_string($mime) && $mime !== '' ? $mime : null;
    }

    public function safeDownloadPath(string $candidatePath, ?array $allowedRoots = null): ?string
    {
        $candidatePath = trim($candidatePath);
        if ($candidatePath === '' || str_contains($candidatePath, "\0")) {
            return null;
        }

        $real = realpath($candidatePath);
        if ($real === false || !is_file($real)) {
            return null;
        }

        $roots = $allowedRoots ?? [
            realpath(WRITEPATH . 'uploads') ?: WRITEPATH . 'uploads',
            realpath(WRITEPATH . 'certificates') ?: WRITEPATH . 'certificates',
            realpath(ROOTPATH . 'storage') ?: ROOTPATH . 'storage',
        ];

        $normalizedReal = str_replace('\\', '/', $real);
        foreach ($roots as $root) {
            if (!is_string($root) || $root === '') {
                continue;
            }
            $normalizedRoot = rtrim(str_replace('\\', '/', realpath($root) ?: $root), '/') . '/';
            if (str_starts_with($normalizedReal, $normalizedRoot)) {
                return $real;
            }
        }

        $this->audit('download_path_blocked', ['candidate' => $candidatePath]);
        return null;
    }

    public function publicUploadsDirectory(string $relativeDirectory = ''): string
    {
        $root = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads';
        return $relativeDirectory === '' ? $root : $root . DIRECTORY_SEPARATOR . trim($relativeDirectory, '/\\');
    }

    public function privateDirectory(string $relativeDirectory): string
    {
        return rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . trim($relativeDirectory, '/\\');
    }

    public function ensureDirectory(string $path, int $mode = 0750): bool
    {
        if (!is_dir($path) && !@mkdir($path, $mode, true) && !is_dir($path)) {
            return false;
        }

        @chmod($path, $mode);
        $this->writeDenyFiles($path);
        return true;
    }

    public function writeDenyFiles(string $path): void
    {
        $htaccess = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, "Options -Indexes -ExecCGI\nRemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phar .pl .py .cgi .sh\nRemoveType .php .phtml .php3 .php4 .php5 .php7 .php8 .phar .pl .py .cgi .sh\n<FilesMatch \".*\">\n    Require all denied\n</FilesMatch>\n");
            @chmod($htaccess, 0640);
        }

        $index = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index.html';
        if (!is_file($index)) {
            @file_put_contents($index, '<!doctype html><title>403 Forbidden</title>');
            @chmod($index, 0640);
        }
    }

    public function randomFilename(string $extension): string
    {
        $extension = strtolower(preg_replace('/[^a-z0-9]+/', '', $extension) ?: 'bin');
        return date('Ymd_His') . '_' . bin2hex(random_bytes(16)) . '.' . $extension;
    }

    public function sanitizeClientName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^A-Za-z0-9._ -]+/u', '_', $name) ?: 'arquivo';
        return trim($name, ' ._-') ?: 'arquivo';
    }

    public function audit(string $event, array $context = []): void
    {
        $safe = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $safe[$key] = $value;
            }
        }

        log_message('info', '[UploadSecurity] ' . $event . ' ' . json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @return array{success:bool,message:string,extension:?string,mime_type:?string,size:int,client_name:string} */
    private function invalid(string $message, ?string $extension, ?string $mime, int $size, string $clientName): array
    {
        return [
            'success' => false,
            'message' => $message,
            'extension' => $extension,
            'mime_type' => $mime,
            'size' => $size,
            'client_name' => $this->sanitizeClientName($clientName),
        ];
    }

    /** @return array{success:bool,message:string,stored_path:?string,absolute_path:?string,file_name:?string,mime_type:?string,file_size:?int} */
    private function storeFailure(string $message, ?string $mime): array
    {
        return [
            'success' => false,
            'message' => $message,
            'stored_path' => null,
            'absolute_path' => null,
            'file_name' => null,
            'mime_type' => $mime,
            'file_size' => null,
        ];
    }
}
