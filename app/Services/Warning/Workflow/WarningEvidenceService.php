<?php

namespace App\Services\Warning\Workflow;

use App\Services\Warning\WarningPdfStorageService;

class WarningEvidenceService
{
    public function __construct(private readonly ?WarningPdfStorageService $pdfStorageService = null)
    {
    }

    private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
    ];
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;

    public function handleEvidenceUploads(array $files): array
    {
        $evidenceFiles = [];
        $uploadErrors = [];

        if (!isset($files['evidence_files'])) {
            return ['success' => true, 'files' => []];
        }

        helper('file_upload');
        $uploadSecurity = new \App\Services\Upload\SafeUploadService();
        $fileCount = 0;

        foreach ($files['evidence_files'] as $file) {
            if (!$file instanceof \CodeIgniter\HTTP\Files\UploadedFile || !$file->isValid() || $file->hasMoved()) {
                continue;
            }

            if ($fileCount >= 5) {
                $uploadErrors[] = 'Máximo de 5 arquivos de evidência permitidos.';
                break;
            }

            $stored = $uploadSecurity->storePrivate(
                $file,
                'warnings/evidence/' . date('Y') . '/' . date('m'),
                self::ALLOWED_EXTENSIONS,
                self::ALLOWED_MIME_TYPES,
                self::MAX_FILE_SIZE
            );

            if (($stored['success'] ?? false) !== true) {
                $uploadErrors[] = "Arquivo '" . htmlspecialchars((string) $file->getClientName(), ENT_QUOTES, 'UTF-8') . "': " . ($stored['message'] ?? 'falhou na validação segura.');
                continue;
            }

            $evidenceFiles[] = (string) $stored['stored_path'];
            $fileCount++;
        }

        return [
            'success' => empty($uploadErrors),
            'files' => $evidenceFiles,
            'errors' => $uploadErrors,
        ];
    }

    public function deleteWarningAssets(object $warning): void
    {
        if (!empty($warning->evidence_files)) {
            foreach ($warning->evidence_files as $file) {
                $filepath = WRITEPATH . $file;
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
            }
        }

        if (!empty($warning->pdf_path)) {
            ($this->pdfStorageService ?? new WarningPdfStorageService())->deleteStoredFile((string) $warning->pdf_path);
        }
    }
}
