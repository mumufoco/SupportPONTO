<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Services\Security\RateLimitService;

/**
 * Rate Limit Filter
 *
 * Applies rate limiting to requests using RateLimitService
 *
 * Features:
 * - Per-endpoint rate limiting with configurable types
 * - IP-based throttling with proxy header support
 * - HTTP 429 responses with Retry-After header
 * - X-RateLimit-* headers for API compliance
 * - IP whitelisting support
 * - Audit logging for rate limit violations
 *
 * Usage in app/Config/Filters.php:
 * - Add to $aliases: 'ratelimit' => \App\Filters\RateLimitFilter::class
 * - Add to $globals['before'] for all routes
 * - Or apply to specific routes/groups
 *
 * @package App\Filters
 */
class RateLimitFilter implements FilterInterface
{
    /**
     * Rate limit service
     * @var RateLimitService
     */
    protected RateLimitService $rateLimitService;

    /**
     * Temporary storage for rate limit headers
     * (Avoids dynamic property deprecation warning in PHP 8.2+)
     * @var array
     */
    protected array $rateLimitHeaders = [];

    /**
     * Endpoint-to-limit-type mapping
     *
     * Maps URL patterns to rate limit types defined in RateLimitService
     *
     * @var array
     */
    protected array $endpointLimits = [
        'auth/login' => 'login',
        'auth/forgot-password' => 'password_reset',
        'auth/reset-password' => 'password_reset',
        'auth/register' => 'register',
        'auth/positions-by-department' => 'register',
        'auth/2fa/verify' => '2fa_verify',
        'api/oauth/token' => 'oauth_token',
        'api/' => 'api',
        'timesheet/punch' => 'general',
        'biometric' => 'biometric',
    ];

    /**
     * Routes that should bypass rate limiting
     *
     * @var array
     */
    protected array $excludedRoutes = [
        'api/health',
        'health',
        'ping',
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->rateLimitService = new RateLimitService();
    }

    /**
     * Apply rate limiting before request processing
     *
     * IMPORTANT: For 'login' endpoint, only POST requests are counted
     * to avoid rate-limiting GET requests (page views) or redirects.
     *
     * @param RequestInterface $request
     * @param array|null $arguments
     * @return mixed
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Get current route
        $uri = $request->getUri();
        $path = trim($uri->getPath(), '/');

        // Check if route should be excluded
        foreach ($this->excludedRoutes as $excludedRoute) {
            if ($path === $excludedRoute || str_ends_with($path, '/' . $excludedRoute)) {
                return null;
            }
        }

        // Determine rate limit type for this endpoint
        $limitType = $this->getLimitType($path);

        // FIX: Only apply login rate limiting on POST requests
        // This prevents GET requests (page loads, redirects) from counting against the limit
        // which was causing login loops
        if ($limitType === 'login' && $request->getMethod() !== 'post') {
            // Don't rate limit GET requests to login page
            return null;
        }

        // Generate unique key for this request
        // Combine path and IP for granular rate limiting
        $ip = $this->rateLimitService->getClientIp();
        // Use MD5 hash to avoid IP collision issues (e.g., 192.168.1.1 vs 19.216.81.11)
        $key = str_replace('/', '_', $path) . '_' . md5($ip);

        // Attempt to perform action (check and increment)
        $limitInfo = $this->rateLimitService->attempt($key, $limitType, $ip);

        // Store headers to add in after() (using class property to avoid PHP 8.2+ deprecation warning)
        $this->rateLimitHeaders = $this->rateLimitService->getHeaders($limitInfo);

        // Check if rate limit exceeded
        if (!$limitInfo['allowed']) {
            // Log rate limit exceeded with improved detail
            $this->logRateLimitExceeded($ip, $limitType, $path, $limitInfo);

            return $this->rateLimitExceededResponse($request, $limitInfo);
        }

        // If we're close to the limit, log a warning
        if ($limitInfo['remaining'] <= 5 && $limitInfo['remaining'] > 0) {
            log_message('notice', "Rate limit warning for IP {$ip} on {$path}: {$limitInfo['remaining']} attempts remaining");
        }

        return null;
    }

    /**
     * Add rate limit headers to response
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param array|null $arguments
     * @return ResponseInterface
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Add rate limit headers if available (using class property instead of request property)
        if (!empty($this->rateLimitHeaders)) {
            foreach ($this->rateLimitHeaders as $header => $value) {
                $response->setHeader($header, (string) $value);
            }
        }

        return $response;
    }

    /**
     * Generate HTTP 429 response for rate limit exceeded
     *
     * @param RequestInterface $request
     * @param array $limitInfo
     * @return ResponseInterface
     */
    protected function rateLimitExceededResponse(RequestInterface $request, array $limitInfo): ResponseInterface
    {
        $response = service('response');

        // Calculate retry after time
        $retryAfter = max(1, $limitInfo['reset_at'] - time());

        // Set HTTP 429 status
        $response->setStatusCode(429, 'Too Many Requests');

        // Add Retry-After header (RFC 6585)
        $response->setHeader('Retry-After', (string) $retryAfter);

        // Add rate limit headers
        foreach ($this->rateLimitService->getHeaders($limitInfo) as $header => $value) {
            $response->setHeader($header, (string) $value);
        }

        // Get error message
        $errorMessage = $this->rateLimitService->getErrorMessage($limitInfo);

        // Return JSON response for API endpoints
        if ($this->isApiRequest($request)) {
            $response->setJSON([
                'error' => true,
                'success' => false,
                'message' => $errorMessage,
                'retry_after' => $retryAfter,
                'limit' => $limitInfo['max_attempts'] ?? 0,
                'remaining' => 0,
                'reset_at' => $limitInfo['reset_at'] ?? 0,
            ]);
        } else {
            // For web requests, show flash message and redirect
            session()->setFlashdata('error', $errorMessage);
            return redirect()->back();
        }

        return $response;
    }

    /**
     * Determine rate limit type based on path
     *
     * @param string $path
     * @return string
     */
    protected function getLimitType(string $path): string
    {
        // Check exact matches first
        if (isset($this->endpointLimits[$path])) {
            return $this->endpointLimits[$path];
        }

        // Check pattern matches
        foreach ($this->endpointLimits as $pattern => $limitType) {
            if (strpos($path, $pattern) !== false) {
                return $limitType;
            }
        }

        return 'general';
    }

    /**
     * Check if request is an API request
     *
     * @param RequestInterface $request
     * @return bool
     */
    protected function isApiRequest(RequestInterface $request): bool
    {
        // Check Accept header
        $acceptHeader = $request->getHeaderLine('Accept');
        if (strpos($acceptHeader, 'application/json') !== false) {
            return true;
        }

        // Check if path starts with /api
        $path = trim($request->getUri()->getPath(), '/');
        if (strpos($path, 'api/') === 0) {
            return true;
        }

        // Check Content-Type header
        $contentType = $request->getHeaderLine('Content-Type');
        if (strpos($contentType, 'application/json') !== false) {
            return true;
        }

        // Check if AJAX request
        if ($request->isAJAX()) {
            return true;
        }

        return false;
    }

    /**
     * Log rate limit exceeded event
     *
     * @param string $ip IP address
     * @param string $limitType Rate limit type
     * @param string $path Request path
     * @param array $limitInfo Limit information
     * @return void
     */
    protected function logRateLimitExceeded(string $ip, string $limitType, string $path, array $limitInfo): void
    {
        try {
            // Enhanced logging with more detail
            $resetIn = max(0, $limitInfo['reset_at'] - time());
            $resetMinutes = ceil($resetIn / 60);
            
            log_message('warning', sprintf(
                "Rate limit exceeded: IP=%s, Type=%s, Path=%s, Attempts=%d/%d, Reset in %d seconds (%d minutes)",
                $ip,
                $limitType,
                $path,
                $limitInfo['attempts'] ?? 0,
                $limitInfo['max_attempts'] ?? 0,
                $resetIn,
                $resetMinutes
            ));

            // Log to audit if user is authenticated
            helper('session_context');
            $employeeId = sp_session_user_id();

            if ($employeeId) {
                $auditModel = new \App\Models\AuditModel();
                $auditModel->log(
                    $employeeId,
                    'RATE_LIMIT_EXCEEDED',
                    'system',
                    null,
                    null,
                    [
                        'limit_type' => $limitType,
                        'path' => $path,
                        'ip' => $ip,
                        'attempts' => $limitInfo['attempts'] ?? 0,
                        'max_attempts' => $limitInfo['max_attempts'] ?? 0,
                        'reset_at' => $limitInfo['reset_at'] ?? 0,
                        'reset_in_seconds' => $resetIn,
                    ],
                    "Limite de requisições excedido: {$limitType} em {$path} - Tente novamente em {$resetMinutes} minuto(s)",
                    'warning'
                );
            }
        } catch (\Exception $e) {
            log_message('error', 'Failed to log rate limit exceeded: ' . $e->getMessage());
        }
    }

    /**
     * Add custom endpoint limit mapping
     *
     * Useful for dynamic configuration or testing
     *
     * @param string $endpoint
     * @param string $limitType
     * @return void
     */
    public function addEndpointLimit(string $endpoint, string $limitType): void
    {
        $this->endpointLimits[$endpoint] = $limitType;
    }

    /**
     * Exclude route from rate limiting
     *
     * @param string $route
     * @return void
     */
    public function excludeRoute(string $route): void
    {
        if (!in_array($route, $this->excludedRoutes, true)) {
            $this->excludedRoutes[] = $route;
        }
    }

    /**
     * Get current endpoint limits configuration
     *
     * @return array
     */
    public function getEndpointLimits(): array
    {
        return $this->endpointLimits;
    }

    /**
     * Get excluded routes
     *
     * @return array
     */
    public function getExcludedRoutes(): array
    {
        return $this->excludedRoutes;
    }
}
