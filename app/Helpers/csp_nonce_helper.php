<?php

declare(strict_types=1);

// Independent nonce generator. Named sp_csp_nonce() to avoid conflict with CI4's
// csp_script_nonce() (Common.php) which returns the full attribute string when CSP
// is enabled, or empty string when disabled -- neither is usable as a bare token.
if (! function_exists('sp_csp_nonce')) {
    function sp_csp_nonce(): string
    {
        static $nonce = null;

        if ($nonce !== null) {
            return $nonce;
        }

        try {
            $nonce = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
        } catch (Throwable $e) {
            $nonce = bin2hex(random_bytes(18));
        }

        return $nonce;
    }
}

if (! function_exists('csp_script_nonce_attr')) {
    function csp_script_nonce_attr(): string
    {
        return 'nonce="' . sp_csp_nonce() . '"';
    }
}
