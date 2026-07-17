<?php

declare(strict_types=1);

namespace App\Services\Security;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Sanitiza o HTML rico digitado no editor de termos de consentimento
 * (Admin\BiometricConsentController::saveTerm()) antes de gravar no banco.
 *
 * Usa ezyang/htmlpurifier (ja disponivel no vendor/ como dependencia
 * transitiva -- nunca usado no app ate agora, confirmado por grep) em vez
 * de security_sanitize()/InputSanitizerService, que faz strip_tags() bruto
 * e apagaria toda a formatacao do editor. O texto sai da purificacao ja
 * seguro pra ecoar direto (sem esc()) em qualquer tela que exiba o termo.
 *
 * Allowlist restrita ao que um documento juridico realmente precisa:
 * paragrafos, quebras, negrito/italico/sublinhado, listas, titulos,
 * citacoes, links (com rel=noopener automatico) e tabelas simples.
 * Nada de scripts, iframes, formularios ou atributos de evento.
 */
class ConsentTermSanitizerService
{
    public function sanitize(string $html): string
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed',
            'p,br,strong,b,em,i,u,s,ul,ol,li,h3,h4,h5,h6,blockquote,' .
            'a[href|rel|target],table,thead,tbody,tr,th,td'
        );
        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('HTML.TargetBlank', true);
        $config->set('URI.DisableExternalResources', true);
        $config->set('Cache.SerializerPath', WRITEPATH . 'cache/htmlpurifier');
        $config->set('Cache.SerializerPermissions', 0755);

        if (! is_dir(WRITEPATH . 'cache/htmlpurifier')) {
            mkdir(WRITEPATH . 'cache/htmlpurifier', 0755, true);
        }

        $purifier = new HTMLPurifier($config);

        return $purifier->purify($html);
    }

    /**
     * Diferencia termos legados em texto puro (sem nenhuma marcacao) dos
     * novos termos redigidos no editor rico.
     */
    public function containsMarkup(string $value): bool
    {
        return trim(strip_tags($value)) !== trim($value);
    }
}
