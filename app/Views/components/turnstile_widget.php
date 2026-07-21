<?php
/**
 * Widget do Cloudflare Turnstile. Sem TURNSTILE_SITE_KEY/TURNSTILE_SECRET_KEY
 * configuradas no .env, não renderiza nada (feature desativada por padrão).
 */
$turnstile = \Config\Services::turnstileService();
if (!$turnstile->isEnabled()) {
    return;
}
?>
<div class="cf-turnstile mb-3" data-sitekey="<?= esc($turnstile->siteKey(), 'attr') ?>" data-theme="auto"></div>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer <?= csp_script_nonce_attr() ?>></script>
