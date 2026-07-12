<?php

namespace App\Services\Auth\OAuth2;

use CodeIgniter\Config\Services;

class OAuth2DeviceFingerprint
{
    public static function generate(): string
    {
        $request = Services::request();

        $components = [
            $request->getUserAgent()->getAgentString(),
            $request->getIPAddress(),
            $request->getHeaderLine('Accept-Language'),
        ];

        return hash('sha256', implode('|', $components));
    }
}
