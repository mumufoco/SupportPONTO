<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * MELHORIA 5: Filtro de Correlation ID para rastreamento de requisições.
 *
 * Cada requisição HTTP recebe um ID único que é propagado em todos os logs.
 * Em produção com múltiplos workers FPM, isso permite reconstruir o fluxo
 * completo de uma requisição específica filtrando por correlation_id.
 *
 * Uso em logs:
 *   log_message('error', json_encode([
 *       'event'          => 'punch_failed',
 *       'correlation_id' => correlation_id(),
 *       'employee_id'    => $id,
 *       'error'          => $e->getMessage(),
 *   ]));
 *
 * Clientes podem enviar X-Correlation-ID próprio para rastrear end-to-end.
 */
class CorrelationIdFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Aceitar correlation_id do cliente (útil para rastreamento end-to-end)
        // ou gerar um novo
        $correlationId = $request->getHeaderLine('X-Correlation-ID');

        if (empty($correlationId) || !preg_match('/^[a-zA-Z0-9_\-]{8,64}$/', $correlationId)) {
            $correlationId = sprintf(
                '%s-%s',
                date('ymdHis'),
                bin2hex(random_bytes(4))
            );
        }

        $requestId = $request->getHeaderLine('X-Request-ID');
        if (empty($requestId) || !preg_match('/^[a-zA-Z0-9_.:\-]{8,120}$/', $requestId)) {
            $requestId = $correlationId;
        }

        $_SERVER['SUPPORTPONTO_CORRELATION_ID'] = $correlationId;
        $_SERVER['SUPPORTPONTO_REQUEST_ID'] = $requestId;

        // Armazenar no ambiente da requisição via constante dinâmica
        if (!defined('CORRELATION_ID')) {
            define('CORRELATION_ID', $correlationId);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Propagar o ID na resposta para que clientes possam correlacionar
        if (defined('CORRELATION_ID')) {
            $response->setHeader('X-Correlation-ID', CORRELATION_ID);
            $response->setHeader('X-Request-ID', $_SERVER['SUPPORTPONTO_REQUEST_ID'] ?? CORRELATION_ID);
        }

        return $response;
    }
}
