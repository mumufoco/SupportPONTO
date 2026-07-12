<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * CORS Filter
 *
 * Handles Cross-Origin Resource Sharing (CORS) for API endpoints.
 *
 * Security rules:
 * - Production must use explicit origin allowlists.
 * - Wildcard origins never send credentials.
 * - Disallowed origins are rejected during preflight and request processing.
 */
class CorsFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $origin = trim($request->getHeaderLine('Origin'));

        if ($origin !== '' && ! $this->isOriginAllowed($origin)) {
            return $this->forbiddenOriginResponse($origin);
        }

        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            $response = service('response');
            $this->addCorsHeaders($request, $response);

            return $response
                ->setStatusCode(200)
                ->setBody('');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $this->addCorsHeaders($request, $response);

        return $response;
    }

    protected function addCorsHeaders(RequestInterface $request, ResponseInterface $response): void
    {
        $allowedOrigins = $this->getAllowedOrigins();
        $origin = trim($request->getHeaderLine('Origin'));
        $allowCredentials = $this->shouldAllowCredentials($allowedOrigins);

        $this->mergeVaryHeader($response, ['Origin', 'Access-Control-Request-Method', 'Access-Control-Request-Headers']);

        if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
            $response->setHeader('Access-Control-Allow-Origin', $origin);
        } elseif ($origin === '' && in_array('*', $allowedOrigins, true) && ! $allowCredentials) {
            $response->setHeader('Access-Control-Allow-Origin', '*');
        }

        if ($allowCredentials && $origin !== '' && ! in_array('*', $allowedOrigins, true)) {
            $response->setHeader('Access-Control-Allow-Credentials', 'true');
        }

        $response->setHeader(
            'Access-Control-Allow-Methods',
            'GET, POST, PUT, PATCH, DELETE, OPTIONS'
        );

        $response->setHeader(
            'Access-Control-Allow-Headers',
            'Accept, Authorization, Content-Type, Origin, X-Requested-With, X-CSRF-TOKEN, X-Request-ID'
        );

        $response->setHeader(
            'Access-Control-Expose-Headers',
            'Content-Length, X-JSON, X-Request-ID'
        );

        $response->setHeader('Access-Control-Max-Age', '86400');
    }

    protected function forbiddenOriginResponse(string $origin): ResponseInterface
    {
        $response = service('response');
        $this->mergeVaryHeader($response, ['Origin']);

        return $response
            ->setStatusCode(403)
            ->setJSON([
                'success' => false,
                'code' => 'cors_origin_denied',
                'message' => 'Origem não autorizada para este endpoint.',
                'meta' => [
                    'timestamp' => gmdate(DATE_ATOM),
                ],
            ]);
    }

    protected function isOriginAllowed(string $origin): bool
    {
        $allowedOrigins = $this->getAllowedOrigins();

        if (in_array('*', $allowedOrigins, true)) {
            return true;
        }

        return in_array($origin, $allowedOrigins, true);
    }

    protected function shouldAllowCredentials(array $allowedOrigins): bool
    {
        $credentials = filter_var((string) env('CORS_ALLOW_CREDENTIALS', 'false'), FILTER_VALIDATE_BOOL);

        if (in_array('*', $allowedOrigins, true)) {
            return false;
        }

        return $credentials;
    }

    protected function getAllowedOrigins(): array
    {
        $originsEnv = trim((string) env('CORS_ALLOWED_ORIGINS', ''));
        $origins = [];

        if ($originsEnv !== '') {
            if ($originsEnv === '*') {
                $origins = ['*'];
            } else {
                $origins = array_values(array_filter(array_map('trim', explode(',', $originsEnv))));
            }
        }

        if ($origins === [] && ENVIRONMENT === 'development') {
            $origins = [
                'http://localhost:3000',
                'http://localhost:8080',
                'http://127.0.0.1:3000',
                'http://127.0.0.1:8080',
            ];
        }

        if (ENVIRONMENT === 'production' && in_array('*', $origins, true)) {
            log_message('critical', 'CORS_ALLOWED_ORIGINS="*" é inválido em produção; usando allowlist vazia.');
            return [];
        }

        return array_values(array_unique($origins));
    }

    /**
     * Merge Vary header values without clobbering previously defined values.
     */
    protected function mergeVaryHeader(ResponseInterface $response, array $values): void
    {
        $existing = $response->getHeaderLine('Vary');
        $merged = [];

        if ($existing !== '') {
            $merged = array_map('trim', explode(',', $existing));
        }

        foreach ($values as $value) {
            if (! in_array($value, $merged, true)) {
                $merged[] = $value;
            }
        }

        $response->setHeader('Vary', implode(', ', array_filter($merged)));
    }
}
