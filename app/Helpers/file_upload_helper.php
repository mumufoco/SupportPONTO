<?php

/**
 * File Upload Helper
 *
 * Helper functions for file upload and validation
 */

if (!function_exists('upload_chat_file')) {
    /**
     * Upload seguro de arquivo de chat.
     * Armazena sempre fora de public/ e permite download apenas pelo controller autenticado.
     *
     * @param \CodeIgniter\HTTP\Files\UploadedFile $file
     * @param int $employeeId
     * @return array{success:bool,message:string,file_path:?string,file_name:?string,file_size:?int,file_type:?string}
     */
    function upload_chat_file($file, int $employeeId): array
    {
        if (!$file instanceof \CodeIgniter\HTTP\Files\UploadedFile) {
            return [
                'success' => false,
                'message' => 'Nenhum arquivo foi enviado.',
                'file_path' => null,
                'file_name' => null,
                'file_size' => null,
                'file_type' => null,
            ];
        }

        $security = new \App\Services\Upload\SafeUploadService();
        $allowedTypes = get_allowed_chat_file_types();
        $allowedExtensions = [];
        $allowedMimes = [];
        foreach ($allowedTypes as $config) {
            $allowedExtensions = array_merge($allowedExtensions, $config['extensions']);
            $allowedMimes = array_merge($allowedMimes, $config['mimes']);
        }

        $year = date('Y');
        $month = date('m');
        $stored = $security->storePrivate(
            $file,
            "chat/{$year}/{$month}/{$employeeId}",
            array_values(array_unique($allowedExtensions)),
            array_values(array_unique($allowedMimes)),
            10 * 1024 * 1024
        );

        if (($stored['success'] ?? false) !== true) {
            return [
                'success' => false,
                'message' => $stored['message'] ?? 'Erro ao processar o arquivo.',
                'file_path' => null,
                'file_name' => null,
                'file_size' => null,
                'file_type' => null,
            ];
        }

        $fileType = null;
        foreach ($allowedTypes as $type => $config) {
            if (in_array((string) $stored['mime_type'], $config['mimes'], true)) {
                $fileType = $type;
                break;
            }
        }

        return [
            'success' => true,
            'message' => 'Arquivo enviado com sucesso.',
            'file_path' => $stored['stored_path'],
            'file_name' => $stored['file_name'],
            'file_size' => $stored['file_size'],
            'file_type' => $fileType,
        ];
    }
}

if (!function_exists('get_allowed_chat_file_types')) {
    /**
     * Get allowed file types for chat
     *
     * @return array
     */
    function get_allowed_chat_file_types(): array
    {
        return [
            'image' => [
                'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
                'mimes'      => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            ],
            'document' => [
                'extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv'],
                'mimes'      => [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'text/plain',
                    'text/csv',
                ],
            ],
        ];
    }
}




if (!function_exists('supportponto_detect_real_mime')) {
    function supportponto_detect_real_mime(string $path): ?string
    {
        return (new \App\Services\Upload\SafeUploadService())->detectRealMime($path);
    }
}

if (!function_exists('supportponto_reencode_image_asset')) {
    /**
     * @param array<string> $allowedMimes
     */
    function supportponto_reencode_image_asset(string $sourcePath, string $destinationPath, array $allowedMimes): array
    {
        $realMime = supportponto_detect_real_mime($sourcePath);
        if ($realMime === null || !in_array($realMime, $allowedMimes, true)) {
            return ['success' => false, 'message' => 'Tipo de imagem não permitido.', 'mime_type' => $realMime];
        }

        $raw = @file_get_contents($sourcePath);
        if ($raw === false) {
            return ['success' => false, 'message' => 'Não foi possível ler a imagem enviada.', 'mime_type' => $realMime];
        }

        $image = @imagecreatefromstring($raw);
        if ($image === false) {
            return ['success' => false, 'message' => 'Imagem inválida ou corrompida.', 'mime_type' => $realMime];
        }

        $extension = strtolower(pathinfo($destinationPath, PATHINFO_EXTENSION));
        $saved = false;

        if ($extension === 'png') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $saved = imagepng($image, $destinationPath, 9);
        } elseif ($extension === 'webp' && function_exists('imagewebp')) {
            imagepalettetotruecolor($image);
            imagealphablending($image, true);
            imagesavealpha($image, true);
            $saved = imagewebp($image, $destinationPath, 90);
        } elseif (in_array($extension, ['jpg', 'jpeg'], true)) {
            imagepalettetotruecolor($image);
            $saved = imagejpeg($image, $destinationPath, 90);
        } else {
            imagedestroy($image);
            return ['success' => false, 'message' => 'Formato de imagem não suportado.', 'mime_type' => $realMime];
        }

        imagedestroy($image);

        if (!$saved || !is_file($destinationPath)) {
            @unlink($destinationPath);
            return ['success' => false, 'message' => 'Falha ao normalizar a imagem enviada.', 'mime_type' => $realMime];
        }

        @chmod($destinationPath, 0644);

        return [
            'success' => true,
            'mime_type' => $realMime,
            'file_size' => @filesize($destinationPath) ?: null,
            'width_height' => @getimagesize($destinationPath) ?: null,
        ];
    }
}

if (!function_exists('supportponto_store_public_image_asset')) {
    /**
     * @param array<string> $allowedExtensions
     * @param array<string> $allowedMimes
     */
    function supportponto_store_public_image_asset($file, string $directory, string $baseName, array $allowedExtensions, array $allowedMimes, int $maxBytes): array
    {
        if (!$file instanceof \CodeIgniter\HTTP\Files\UploadedFile || !$file->isValid() || $file->hasMoved()) {
            return ['success' => false, 'message' => 'Arquivo inválido.'];
        }

        $extension = strtolower((string) $file->getClientExtension());
        if ($extension === '' || in_array($extension, supportponto_blocked_upload_extensions(), true) || !in_array($extension, $allowedExtensions, true)) {
            return ['success' => false, 'message' => 'Extensão de arquivo não permitida.'];
        }

        if ($file->getSize() > $maxBytes) {
            return ['success' => false, 'message' => 'Arquivo excede o tamanho permitido.'];
        }

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            return ['success' => false, 'message' => 'Não foi possível preparar o diretório do arquivo.'];
        }
        @chmod($directory, 0755);

        $filename = supportponto_generate_upload_name($baseName, $extension);
        $targetPath = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        if (in_array($extension, ['png', 'jpg', 'jpeg', 'webp'], true)) {
            $normalized = supportponto_reencode_image_asset($file->getTempName(), $targetPath, $allowedMimes);
            if (($normalized['success'] ?? false) !== true) {
                @unlink($targetPath);
                return $normalized;
            }

            return [
                'success' => true,
                'filename' => $filename,
                'path' => $targetPath,
                'mime_type' => $normalized['mime_type'] ?? null,
                'file_size' => $normalized['file_size'] ?? null,
            ];
        }

        try {
            $file->move($directory, $filename);
        } catch (\Throwable) {
            return ['success' => false, 'message' => 'Falha ao mover o arquivo enviado.'];
        }

        $realMime = supportponto_detect_real_mime($targetPath);
        if ($realMime === null || !in_array($realMime, $allowedMimes, true)) {
            @unlink($targetPath);
            return ['success' => false, 'message' => 'Tipo de imagem não permitido.', 'mime_type' => $realMime];
        }

        @chmod($targetPath, 0644);

        return [
            'success' => true,
            'filename' => $filename,
            'path' => $targetPath,
            'mime_type' => $realMime,
            'file_size' => @filesize($targetPath) ?: null,
        ];
    }
}

if (!function_exists('supportponto_safe_download_path')) {
    /**
     * @param array<int,string>|null $allowedRoots
     */
    function supportponto_safe_download_path(string $candidatePath, ?array $allowedRoots = null): ?string
    {
        return (new \App\Services\Upload\SafeUploadService())->safeDownloadPath($candidatePath, $allowedRoots);
    }
}

if (!function_exists('supportponto_blocked_upload_extensions')) {
    /**
     * @return string[]
     */
    function supportponto_blocked_upload_extensions(): array
    {
        return (new \App\Services\Upload\SafeUploadService())->blockedExtensions();
    }
}

if (!function_exists('supportponto_generate_upload_name')) {
    function supportponto_generate_upload_name(string $originalName, string $extension): string
    {
        return (new \App\Services\Upload\SafeUploadService())->randomFilename($extension);
    }
}

if (!function_exists('supportponto_ensure_upload_directory')) {
    function supportponto_ensure_upload_directory(string $path): bool
    {
        return (new \App\Services\Upload\SafeUploadService())->ensureDirectory($path, 0750);
    }
}

if (!function_exists('upload_justification_attachment')) {
    /**
     * Upload seguro de anexo de justificativa.
     *
     * @param \CodeIgniter\HTTP\Files\UploadedFile $file
     * @return array{success:bool,message:string,file_path:?string,file_name:?string,file_size:?int,mime_type:?string}
     */
    function upload_justification_attachment($file, int $employeeId): array
    {
        helper(['observability', 'security']);

        if (!$file->isValid() || $file->hasMoved()) {
            return [
                'success' => false,
                'message' => 'Arquivo inválido.',
                'file_path' => null,
                'file_name' => null,
                'file_size' => null,
                'mime_type' => null,
            ];
        }

        $maxSize = 5 * 1024 * 1024;
        if ($file->getSize() > $maxSize) {
            return [
                'success' => false,
                'message' => 'Cada anexo deve ter no máximo 5MB.',
                'file_path' => null,
                'file_name' => null,
                'file_size' => null,
                'mime_type' => null,
            ];
        }

        $clientExtension = strtolower((string) $file->getClientExtension());
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
        if ($clientExtension === '' || in_array($clientExtension, supportponto_blocked_upload_extensions(), true) || !in_array($clientExtension, $allowedExtensions, true)) {
            supportponto_log_event('warning', 'upload', 'justification.blocked_extension', [
                'employee_id' => $employeeId,
                'extension' => $clientExtension,
            ]);

            return [
                'success' => false,
                'message' => 'Tipo de arquivo não permitido.',
                'file_path' => null,
                'file_name' => null,
                'file_size' => null,
                'mime_type' => null,
            ];
        }

        $allowedMimes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
        ];

        $year = date('Y');
        $month = date('m');
        $uploadPath = WRITEPATH . "uploads/justifications/{$year}/{$month}/{$employeeId}";
        if (!supportponto_ensure_upload_directory($uploadPath)) {
            supportponto_log_event('error', 'upload', 'justification.directory_failed', [
                'employee_id' => $employeeId,
                'path' => $uploadPath,
            ]);

            return [
                'success' => false,
                'message' => 'Não foi possível preparar o diretório do anexo.',
                'file_path' => null,
                'file_name' => null,
                'file_size' => null,
                'mime_type' => null,
            ];
        }

        $newName = supportponto_generate_upload_name((string) $file->getClientName(), $clientExtension);

        try {
            $file->move($uploadPath, $newName);
        } catch (\Throwable $e) {
            supportponto_log_exception('upload', 'justification.move_failed', $e, [
                'employee_id' => $employeeId,
                'file_name' => $file->getClientName(),
            ]);

            return [
                'success' => false,
                'message' => 'Erro ao processar o anexo.',
                'file_path' => null,
                'file_name' => null,
                'file_size' => null,
                'mime_type' => null,
            ];
        }

        $fullPath = $uploadPath . DIRECTORY_SEPARATOR . $newName;
        $realMime = mime_content_type($fullPath) ?: null;
        if ($realMime === null || !in_array($realMime, $allowedMimes, true)) {
            @unlink($fullPath);
            supportponto_log_event('warning', 'upload', 'justification.invalid_mime', [
                'employee_id' => $employeeId,
                'mime_type' => $realMime,
                'extension' => $clientExtension,
            ]);

            return [
                'success' => false,
                'message' => 'Tipo de arquivo não permitido.',
                'file_path' => null,
                'file_name' => null,
                'file_size' => null,
                'mime_type' => null,
            ];
        }

        if (str_starts_with($realMime, 'image/')) {
            $image = @imagecreatefromstring((string) file_get_contents($fullPath));
            if ($image === false) {
                @unlink($fullPath);
                supportponto_log_event('warning', 'upload', 'justification.invalid_image', [
                    'employee_id' => $employeeId,
                    'mime_type' => $realMime,
                ]);

                return [
                    'success' => false,
                    'message' => 'Arquivo de imagem inválido ou corrompido.',
                    'file_path' => null,
                    'file_name' => null,
                    'file_size' => null,
                    'mime_type' => null,
                ];
            }
            imagedestroy($image);
        }

        @chmod($fullPath, 0640);

        return [
            'success' => true,
            'message' => 'Arquivo enviado com sucesso.',
            'file_path' => "uploads/justifications/{$year}/{$month}/{$employeeId}/{$newName}",
            'file_name' => security_sanitize_filename((string) $file->getClientName()),
            'file_size' => filesize($fullPath),
            'mime_type' => $realMime,
        ];
    }
}

if (!function_exists('delete_chat_file')) {
    /**
     * Delete chat file
     *
     * @param string $filePath
     * @return bool
     */
    function delete_chat_file(string $filePath): bool
    {
        $fullPath = WRITEPATH . $filePath;

        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }

        return false;
    }
}

if (!function_exists('get_file_icon')) {
    /**
     * Get Font Awesome icon for file type
     *
     * @param string $fileType
     * @param string $extension
     * @return string
     */
    function get_file_icon(string $fileType, string $extension = ''): string
    {
        $icons = [
            'image'    => 'fa-file-image',
            'document' => 'fa-file-alt',
            'archive'  => 'fa-file-archive',
        ];

        // Specific extensions
        $specificIcons = [
            'pdf'  => 'fa-file-pdf',
            'doc'  => 'fa-file-word',
            'docx' => 'fa-file-word',
            'xls'  => 'fa-file-excel',
            'xlsx' => 'fa-file-excel',
            'txt'  => 'fa-file-alt',
            'csv'  => 'fa-file-csv',
            'zip'  => 'fa-file-archive',
            'rar'  => 'fa-file-archive',
        ];

        if ($extension && isset($specificIcons[$extension])) {
            return $specificIcons[$extension];
        }

        return $icons[$fileType] ?? 'fa-file';
    }
}

if (!function_exists('format_file_size')) {
    /**
     * Format file size in human-readable format
     *
     * @param int $bytes
     * @return string
     */
    function format_file_size(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}

if (!function_exists('is_image_file')) {
    /**
     * Check if file is an image
     *
     * @param string $fileType
     * @return bool
     */
    function is_image_file(string $fileType): bool
    {
        return $fileType === 'image';
    }
}

if (!function_exists('get_file_url')) {
    /**
     * Get public URL for file
     *
     * @param string $filePath
     * @return string
     */
    function get_file_url(string $filePath): string
    {
        return base_url('chat/file/download?path=' . urlencode($filePath));
    }
}

if (!function_exists('validate_file_access')) {
    /**
     * Validate if employee has access to file
     *
     * @param string $filePath
     * @param int    $employeeId
     * @return bool
     */
    function validate_file_access(string $filePath, int $employeeId): bool
    {
        // Extract employee ID from path
        // Format: uploads/chat/{year}/{month}/{employee_id}/{filename}
        $parts = explode('/', $filePath);

        if (count($parts) >= 5) {
            $fileEmployeeId = (int) $parts[4];

            // Employee can access their own files
            if ($fileEmployeeId === $employeeId) {
                return true;
            }

            // Check if employee is admin or in the same room
            $db = \Config\Database::connect();

            // Find message with this file
            $message = $db->table('chat_messages')
                ->where('file_path', $filePath)
                ->get()
                ->getRow();

            if ($message) {
                // Check if employee is member of the room
                $isMember = $db->table('chat_room_members')
                    ->where('room_id', $message->room_id)
                    ->where('employee_id', $employeeId)
                    ->countAllResults() > 0;

                return $isMember;
            }
        }

        return false;
    }
}

if (!function_exists('sanitize_filename')) {
    /**
     * Sanitize filename for safe storage
     *
     * @param string $filename
     * @return string
     */
    function sanitize_filename(string $filename): string
    {
        // Remove directory traversal attempts
        $filename = basename($filename);

        // Remove special characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        // Limit length
        if (strlen($filename) > 200) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $name = substr(pathinfo($filename, PATHINFO_FILENAME), 0, 190);
            $filename = $name . '.' . $ext;
        }

        return $filename;
    }
}
