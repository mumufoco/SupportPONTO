<?php

namespace App\Filters;

/**
 * Compatibility alias for the hardened security headers filter.
 *
 * Some documentation and deployments reference App\Filters\SecurityHeaders.
 * The implementation remains centralized in SecurityHeadersFilter to avoid
 * divergent header policies.
 */
final class SecurityHeaders extends SecurityHeadersFilter
{
}
