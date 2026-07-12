<?php

namespace App\Filters;

use App\Traits\ObservabilityTrait;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Valida payload JSON enviado para rotas de API antes de chegar aos controllers.
 *
 * Não força JSON em todos os endpoints, pois /oauth/token mantém compatibilidade
 * com application/x-www-form-urlencoded. Apenas bloqueia JSON malformado quando o
 * cliente declara Content-Type JSON ou envia um corpo que aparenta ser JSON.
 */
class ApiJsonRequestFilter implements FilterInterface
{
    use ObservabilityTrait;

    public function before(RequestInterface $request, $arguments = null)
    {
        if (! in_array(strtolower($request->getMethod()), ['post', 'put', 'patch', 'delete'], true)) {
            return null;
        }

        $body = (string) $request->getBody();
        if (trim($body) === '') {
            return null;
        }

        $contentType = strtolower($request->getHeaderLine('Content-Type'));
        $looksLikeJson = in_array(ltrim($body)[0] ?? '', ['{', '['], true);
        $declaresJson = str_contains($contentType, 'application/json')
            || str_contains($contentType, '+json')
            || str_contains($contentType, 'text/json');

        if (! $declaresJson && ! $looksLikeJson) {
            return null;
        }

        json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return null;
        }

        $this->logSecurityEvent('warning', 'API request rejected due to malformed JSON', [
            'json_error' => json_last_error_msg(),
        ]);

        return $this->attachResponseContext(
            Services::response()
                ->setStatusCode(400, 'Bad Request')
                ->setJSON($this->apiErrorPayload('invalid_json', 'JSON inválido ou malformado.')),
            true
        );
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
