<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * MELHORIA 4: Filtro de Versionamento de API.
 *
 * Adiciona headers informativos de versão em todas as respostas da API:
 *  - X-API-Version: v1            (versão sendo servida)
 *  - X-API-Latest: v1             (versão mais recente disponível)
 *  - X-API-Deprecated: true       (se a versão está sendo descontinuada)
 *  - X-API-Sunset: 2027-01-01     (data de encerramento de versões deprecadas)
 *
 * Clientes devem verificar X-API-Deprecated para migrar com antecedência.
 */
class ApiVersionFilter implements FilterInterface
{
    /** Versão atual canônica */
    private const CURRENT_VERSION = 'v1';

    /** Versões deprecadas e suas datas de sunset */
    private const DEPRECATED_VERSIONS = [
        // 'v0' => '2026-06-01', // exemplo de versão deprecada
    ];

    public function before(RequestInterface $request, $arguments = null)
    {
        return null; // Apenas manipula response
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Detectar versão pelo path
        $path    = trim($request->getUri()->getPath(), '/');
        $version = $this->detectVersion($path);

        $response->setHeader('X-API-Version', $version);
        $response->setHeader('X-API-Latest', self::CURRENT_VERSION);

        // Adicionar warnings de deprecação
        if (isset(self::DEPRECATED_VERSIONS[$version])) {
            $sunset = self::DEPRECATED_VERSIONS[$version];
            $response->setHeader('X-API-Deprecated', 'true');
            $response->setHeader('X-API-Sunset', $sunset);
            $response->setHeader('Warning',
                "299 - \"API version {$version} is deprecated and will be removed on {$sunset}. " .
                "Please migrate to " . self::CURRENT_VERSION . ".\""
            );
        }

        // Para clientes sem versão (/api/* sem /v1/), informar que usam v1
        if (!str_contains($path, '/v')) {
            $response->setHeader('X-API-Routing', 'unversioned-to-' . self::CURRENT_VERSION);
        }

        return $response;
    }

    private function detectVersion(string $path): string
    {
        if (preg_match('#^api/(v\d+)/#', $path, $matches)) {
            return $matches[1];
        }

        return self::CURRENT_VERSION; // default para rotas sem versão explícita
    }
}
