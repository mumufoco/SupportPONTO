<?php

namespace App\Controllers\Upload;

use App\Controllers\BaseController;
use App\Services\Upload\SafeUploadService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

class SecureDownloadController extends BaseController
{
    public function __construct(private readonly SafeUploadService $uploadSecurity = new SafeUploadService())
    {
    }

    /**
     * Download autenticado e controlado de arquivos operacionais em writable/uploads.
     * Não deve ser usado para assets públicos de marca; estes passam apenas por public/uploads/system.
     */
    public function show(string ...$segments): ResponseInterface
    {
        $relative = implode('/', $segments);
        $relative = ltrim(str_replace('\\', '/', $relative), '/');

        if ($relative === '' || str_contains($relative, '../') || str_contains($relative, '/..') || str_contains($relative, "\0")) {
            $this->uploadSecurity->audit('controlled_download_blocked_path', ['path' => $relative]);
            throw PageNotFoundException::forPageNotFound();
        }

        $absolute = $this->uploadSecurity->safeDownloadPath(WRITEPATH . 'uploads/' . $relative, [WRITEPATH . 'uploads']);
        if ($absolute === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $mime = $this->uploadSecurity->detectRealMime($absolute) ?: 'application/octet-stream';
        $this->uploadSecurity->audit('controlled_download_allowed', ['path' => $relative, 'mime_type' => $mime]);

        return $this->response
            ->download($absolute, null)
            ->setFileName(basename($absolute))
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Content-Type', $mime);
    }
}
