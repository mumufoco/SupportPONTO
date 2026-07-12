<?php

namespace App\Filters;

use App\Services\Terminal\PublicTerminalSecurityService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class PublicTerminalSecurityFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (strtoupper($request->getMethod()) === 'GET') {
            return null;
        }

        $token = $request->getPost('kiosk_token') ?: $request->getHeaderLine('X-Kiosk-Token');
        $result = (new PublicTerminalSecurityService())->validateKioskTokenDetailed($token, $request);
        if (($result['success'] ?? false) === true) {
            return null;
        }

        return Services::response()
            ->setStatusCode((int) ($result['status'] ?? 403))
            ->setJSON([
                'success' => false,
                'message' => $result['message'] ?? 'Terminal não autorizado.',
                'error' => $result['error'] ?? 'terminal_security_denied',
            ]);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
