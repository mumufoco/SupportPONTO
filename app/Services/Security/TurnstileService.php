<?php

namespace App\Services\Security;

use Config\Turnstile as TurnstileConfig;

/**
 * Verificação server-side do Cloudflare Turnstile.
 *
 * Sem TURNSTILE_SITE_KEY/TURNSTILE_SECRET_KEY configuradas no .env, o
 * Turnstile fica desativado automaticamente (isEnabled() = false): o
 * widget não é renderizado e verify() sempre libera, sem quebrar o
 * formulário -- decisão explícita para não deixar login/autocadastro
 * inutilizáveis antes das chaves serem geradas.
 */
class TurnstileService
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(
        private ?TurnstileConfig $config = null,
    ) {
        $this->config ??= config(TurnstileConfig::class);
    }

    public function isEnabled(): bool
    {
        return $this->config->isConfigured();
    }

    public function siteKey(): string
    {
        return $this->config->siteKey;
    }

    /**
     * @param string $token IP do cliente (X-Forwarded-For já resolvido pelo caller)
     */
    public function verify(string $token, string $remoteIp): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        if (trim($token) === '') {
            return false;
        }

        try {
            $client = \Config\Services::curlrequest(['timeout' => 8]);
            $response = $client->post(self::VERIFY_URL, [
                'form_params' => [
                    'secret'   => $this->config->secretKey,
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ],
                'http_errors' => false,
            ]);

            $body = json_decode((string) $response->getBody(), true);

            if (!is_array($body)) {
                log_message('warning', '[TurnstileService] Resposta invalida da API do Turnstile -- liberando por indisponibilidade do provedor.');
                return true;
            }

            return (bool) ($body['success'] ?? false);
        } catch (\Throwable $e) {
            // Falha de rede/infra ao FALAR com o Cloudflare (não confundir com
            // "Cloudflare respondeu e disse que o token é inválido", que
            // legitimamente bloqueia acima) -- se o provedor estiver fora do
            // ar, é pior travar todo login/autocadastro do que aceitar sem
            // verificar por um tempo. O rate limit já em vigor nessas rotas
            // continua servindo de proteção nesse cenário.
            log_message('warning', '[TurnstileService] Falha ao verificar token: ' . $e->getMessage());
            return true;
        }
    }
}
