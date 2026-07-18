<?php

namespace App\Services\Warning\Workflow;

use App\Services\Warning\WarningPdfStorageService;

class WarningEvidenceService
{
    public function __construct(private readonly ?WarningPdfStorageService $pdfStorageService = null)
    {
    }

    // Precisa bater com o que a tela (warnings/create.php) e a regra de validacao
    // (WarningControllerActionService::createValidationRules()) anunciam ao usuario -
    // doc/docx passavam na validacao do formulario mas eram rejeitados aqui, quebrando
    // silenciosamente o upload de evidencias nesse formato.
    private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx'];
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
        $allowedMimes = $uploadSecurity->allowedMimesForGroups(['image_private', 'document_private']);
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
                $allowedMimes,
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
