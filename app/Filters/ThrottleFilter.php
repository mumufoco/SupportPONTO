<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class ThrottleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $throttler = Services::throttler();
        
        $ip = $request->getIPAddress();
        $path = $request->getUri()->getPath();
        
        $key = 'throttle_' . md5($ip . '_' . $path);
        
        $maxRequests = 30;
        $timeWindow = MINUTE;
        
        if ($throttler->check($key, $maxRequests, $timeWindow) === false) {
            $response = Services::response();
            $response->setStatusCode(429);
            $response->setHeader('Retry-After', '60');
            
            if ($request->isAJAX()) {
                return $response->setJSON([
                    'success' => false,
                    'error' => 'Muitas requisições. Aguarde 1 minuto antes de tentar novamente.',
                ]);
            }
            
            return $response->setBody('Too Many Requests. Please wait before trying again.');
        }
        
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }
}
