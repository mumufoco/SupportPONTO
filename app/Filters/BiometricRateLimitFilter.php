<?php

namespace App\Filters;

use App\Services\Security\RateLimitService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class BiometricRateLimitFilter implements FilterInterface
{
    protected RateLimitService $rateLimitService;

    public function __construct()
    {
        $this->rateLimitService = Services::rateLimitService();
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        $ip = $this->rateLimitService->getClientIp();
        $path = trim($request->getUri()->getPath(), '/');
        $limitInfo = $this->rateLimitService->attempt('biometric:' . $path . ':' . md5($ip), 'biometric', $ip);

        if (($limitInfo['allowed'] ?? false) === true) {
            return null;
        }

        return Services::response()
            ->setJSON([
                'success' => false,
                'message' => $this->rateLimitService->getErrorMessage($limitInfo),
                'error' => 'Rate limit exceeded',
            ])
            ->setStatusCode(429)
            ->setHeader('Retry-After', (string) max(1, (($limitInfo['reset_at'] ?? time()) - time())));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
