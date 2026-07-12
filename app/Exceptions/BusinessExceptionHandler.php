<?php

namespace App\Exceptions;

use App\Exceptions\BusinessException;
use CodeIgniter\Debug\ExceptionHandler;
use CodeIgniter\Debug\ExceptionHandlerInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LogLevel;
use Throwable;

/**
 * MELHORIA 10: Handler centralizado de exceções.
 *
 * Centraliza o tratamento de erros eliminando try/catch espalhados
 * por todos os controllers. Cada tipo de exceção produz uma resposta
 * HTTP adequada, sem código duplicado.
 *
 * Comportamento:
 * - BusinessException → resposta HTTP limpa com código e mensagem
 * - Outras exceções   → HTTP 500 + log de erro (comportamento padrão CI4)
 *
 * Detecção web vs API:
 * - Requisições /api/* ou Accept: application/json → JSON
 * - Demais → redirect com flash message ou view de erro
 *
 * Registro em Config/Exceptions.php:
 *   public function handler(int $statusCode, Throwable $exception): ExceptionHandlerInterface
 *   {
 *       if ($exception instanceof \App\Exceptions\BusinessException) {
 *           return new \App\Exceptions\BusinessExceptionHandler($this);
 *       }
 *       return new ExceptionHandler($this);
 *   }
 */
class BusinessExceptionHandler implements ExceptionHandlerInterface
{
    public function __construct(
        private readonly \Config\Exceptions $config
    ) {}

    public function handle(
        Throwable         $exception,
        RequestInterface  $request,
        ResponseInterface $response,
        int               $statusCode,
        int               $exitCode
    ): void {
        /** @var BusinessException $exception */
        $status  = $exception->httpStatus;
        $code    = $exception->errorCode;
        $message = $exception->getMessage();
        $context = $exception->context;

        // Log proporcional à severidade HTTP
        $logLevel = match (true) {
            $status >= 500 => LogLevel::ERROR,
            $status >= 400 => LogLevel::WARNING,
            default        => LogLevel::INFO,
        };

        log_structured($logLevel, 'exception.' . strtolower($code), [
            'http_status' => $status,
            'message'     => $message,
            'context'     => $context,
            'file'        => $exception->getFile(),
            'line'        => $exception->getLine(),
        ]);

        if ($this->isApiRequest($request)) {
            $response = $this->buildJsonResponse($response, $status, $code, $message, $context);
        } else {
            $response = $this->buildWebResponse($response, $request, $status, $message);
        }

        $response->send();
    }

    private function buildJsonResponse(
        ResponseInterface $response,
        int    $status,
        string $code,
        string $message,
        array  $context
    ): ResponseInterface {
        $body = ['success' => false, 'error' => $code, 'message' => $message];

        if (!empty($context['errors'])) {
            $body['errors'] = $context['errors'];
        }

        return $response->setStatusCode($status)
                        ->setHeader('Content-Type', 'application/json')
                        ->setBody(json_encode($body, JSON_UNESCAPED_UNICODE));
    }

    private function buildWebResponse(
        ResponseInterface $response,
        RequestInterface  $request,
        int    $status,
        string $message
    ): ResponseInterface {
        // 404 tem view própria
        if ($status === 404) {
            return $response->setStatusCode(404)
                            ->setBody(view('errors/html/error_404', ['message' => $message]));
        }

        // 403/401: redirect de volta com flash
        if (in_array($status, [401, 403], true)) {
            session()->setFlashdata('error', $message);

            return redirect()->to($status === 401 ? route_to('login') : previous_url());
        }

        // Demais: redirect de volta com flash de erro
        session()->setFlashdata('error', $message);

        return redirect()->to(previous_url() ?: base_url());
    }

    private function isApiRequest(RequestInterface $request): bool
    {
        $uri    = trim($request->getUri()->getPath(), '/');
        $accept = $request->getHeaderLine('Accept');

        return str_starts_with($uri, 'api/') ||
               str_contains($accept, 'application/json') ||
               str_contains($accept, 'application/vnd.supportponto');
    }
}
