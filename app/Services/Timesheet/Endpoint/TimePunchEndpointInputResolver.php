<?php

namespace App\Services\Timesheet\Endpoint;

use CodeIgniter\HTTP\RequestInterface;

class TimePunchEndpointInputResolver
{
    public function resolvePunchMethod(RequestInterface $request): ?string
    {
        $method = $this->input($request, 'method', 'punch_method');
        if ($method === null) {
            return null;
        }

        return match (strtolower((string) $method)) {
            'qr', 'qrcode' => 'qrcode',
            'face', 'facial' => 'facial',
            'fingerprint', 'digital', 'biometria' => 'biometria',
            'cpf' => 'cpf',
            'codigo', 'code', 'unique_code' => 'codigo',
            default => strtolower((string) $method),
        };
    }

    public function resolvePunchType(RequestInterface $request): ?string
    {
        $type = $this->input($request, 'punch_type', 'type');
        if ($type === null) {
            return null;
        }

        return match (strtolower((string) $type)) {
            'entrada', 'in' => 'entrada',
            'saida', 'out' => 'saida',
            'intervalo_inicio', 'inicio_intervalo', 'saida_intervalo', 'almoco_saida', 'intervalo-inicio', 'break_start' => 'intervalo_inicio',
            'intervalo_fim', 'fim_intervalo', 'volta_intervalo', 'almoco_retorno', 'intervalo-fim', 'break_end' => 'intervalo_fim',
            default => strtolower((string) $type),
        };
    }

    public function input(RequestInterface $request, string ...$keys): mixed
    {
        $method = strtoupper($request->getMethod());
        $allowQueryString = in_array($method, ['GET', 'HEAD'], true);
        $jsonBody = $this->resolveJsonBody($request);

        foreach ($keys as $key) {
            $post = $request->getPost($key);
            if ($post !== null && $post !== '') {
                return $post;
            }

            if (array_key_exists($key, $jsonBody) && $jsonBody[$key] !== null && $jsonBody[$key] !== '') {
                return $jsonBody[$key];
            }

            if ($allowQueryString) {
                $get = $request->getGet($key);
                if ($get !== null && $get !== '') {
                    return $get;
                }
            }
        }

        return null;
    }

    private function resolveJsonBody(RequestInterface $request): array
    {
        $jsonBody = $request->getJSON(true);

        return is_array($jsonBody) ? $jsonBody : [];
    }
}
