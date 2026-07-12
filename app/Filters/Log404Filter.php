<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Log404Filter
 *
 * Logs all 404 responses to a dedicated log file for tracking and analysis.
 * Captures context including timestamp, method, URI, user agent, referer, and user info.
 *
 * Features:
 * - JSON format for easy parsing
 * - Fail-open design (errors don't break requests)
 * - Minimal performance impact
 * - Captures authenticated user information when available
 *
 * Log location: writable/logs/404_requests.log
 *
 * @package App\Filters
 */
class Log404Filter implements FilterInterface
{
    /**
     * Before filter - Not used for 404 logging
     *
     * @param RequestInterface $request
     * @param array|null $arguments
     * @return mixed
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Not used - 404 logging happens in after()
        return null;
    }

    /**
     * After filter - Logs 404 responses
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param array|null $arguments
     * @return ResponseInterface
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Only log 404 responses
        if ($response->getStatusCode() !== 404) {
            return $response;
        }

        try {
            $this->log404Request($request, $response);
        } catch (\Exception $e) {
            // Fail-open: log error but don't break the response
            log_message('error', 'Failed to log 404 request: ' . $e->getMessage());
        }

        return $response;
    }

    /**
     * Log 404 request to dedicated log file
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return void
     */
    protected function log404Request(RequestInterface $request, ResponseInterface $response): void
    {
        $uri = $request->getUri();
        $session = session();

        // Build log entry with context
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $request->getMethod(),
            'uri' => (string) $uri,
            'path' => $uri->getPath(),
            'query' => $uri->getQuery(),
            'user_agent' => ($userAgent = $request->getUserAgent()) ? $userAgent->toString() : 'Unknown',
            'referer' => $request->getHeaderLine('Referer') ?: null,
            'ip' => $this->getClientIp($request),
            'user_id' => $session->get('user_id') ?? null,
            'user_email' => $session->get('user_email') ?? null,
            'user_name' => $session->get('user_name') ?? null,
        ];

        // Convert to JSON
        $jsonLine = json_encode($logEntry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        // Check for JSON encoding errors
        if ($jsonLine === false) {
            log_message('error', 'Failed to encode 404 log entry as JSON: ' . json_last_error_msg());
            return;
        }

        // Append to log file with file locking
        $logFile = WRITEPATH . 'logs/404_requests.log';
        
        // Ensure directory exists
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        // Append with exclusive lock
        file_put_contents($logFile, $jsonLine . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get client IP address with proxy support
     *
     * @param RequestInterface $request
     * @return string
     */
    protected function getClientIp(RequestInterface $request): string
    {
        $ip = (string) ($request->getIPAddress() ?? '');

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'Unknown';
    }
}
