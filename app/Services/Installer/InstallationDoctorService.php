<?php

namespace App\Services\Installer;

use App\Services\Biometric\BiometricProductionReadinessService;
use App\Support\BootstrapEnv;
use Config\Database;
use Config\Encryption;

class InstallationDoctorService
{
    public function inspect(bool $withConnections = true): array
    {
        $checks = [];

        $checks[] = $this->checkEnvironmentMode();
        $checks[] = $this->checkPhpVersion();
        $checks = array_merge($checks, $this->checkPhpExtensions());
        $checks = array_merge($checks, $this->checkWritableDirectories());
        $checks[] = $this->checkEnvFile();
        $checks[] = $this->checkSessionSavePath();
        $checks[] = $this->checkEncryptionKey();
        $checks = array_merge($checks, $this->checkRequiredSecrets());
        $checks[] = $this->checkDatabaseConfiguration($withConnections);
        $checks[] = $this->checkRedisConfiguration($withConnections);
        $checks[] = $this->checkDeepFaceConfiguration($withConnections);
        $checks[] = $this->checkBiometricReadiness($withConnections);

        return [
            'status' => $this->overallStatus($checks),
            'generated_at' => date('c'),
            'checks' => $checks,
        ];
    }


    private function checkEnvironmentMode(): array
    {
        $appEnv = BootstrapEnv::environment();
        $ciEnv = BootstrapEnv::get('CI_ENVIRONMENT', $appEnv);
        $consistent = $appEnv === $ciEnv;

        return [
            'key' => 'environment_mode',
            'label' => 'APP_ENV / CI_ENVIRONMENT',
            'severity' => $consistent ? 'ok' : 'warning',
            'value' => 'APP_ENV=' . $appEnv . ' | CI_ENVIRONMENT=' . $ciEnv,
            'details' => $consistent ? 'Ambiente bootstrap consistente.' : 'Padronize APP_ENV e CI_ENVIRONMENT para o mesmo valor.',
        ];
    }

    private function checkPhpVersion(): array
    {
        $required = '8.3.0';
        $current = PHP_VERSION;
        return [
            'key' => 'php_version',
            'label' => 'Versão do PHP',
            'severity' => version_compare($current, $required, '>=') ? 'ok' : 'blocker',
            'value' => $current,
            'details' => 'Requerido >= ' . $required,
        ];
    }

    private function checkPhpExtensions(): array
    {
        $required = ['curl', 'json', 'mbstring', 'openssl', 'pdo', 'pdo_pgsql', 'pgsql'];
        $checks = [];

        foreach ($required as $extension) {
            $loaded = extension_loaded($extension);
            $checks[] = [
                'key' => 'php_ext_' . $extension,
                'label' => 'Extensão PHP: ' . $extension,
                'severity' => $loaded ? 'ok' : 'blocker',
                'value' => $loaded ? 'carregada' : 'ausente',
                'details' => 'Obrigatória para instalação/execução do SupportPONTO.',
            ];
        }

        return $checks;
    }

    private function checkWritableDirectories(): array
    {
        $targets = [
            ROOTPATH . 'writable',
            ROOTPATH . 'writable/cache',
            ROOTPATH . 'writable/logs',
            BootstrapEnv::sessionSavePath(ROOTPATH),
            ROOTPATH . 'storage',
        ];

        $checks = [];
        foreach ($targets as $path) {
            $exists = is_dir($path);
            $writable = $exists && is_writable($path);
            $checks[] = [
                'key' => 'path_' . md5($path),
                'label' => 'Diretório gravável',
                'severity' => ($exists && $writable) ? 'ok' : 'blocker',
                'value' => $path,
                'details' => $exists ? ($writable ? 'OK' : 'Existe, mas não é gravável.') : 'Diretório ausente.',
            ];
        }

        return $checks;
    }

    private function checkEnvFile(): array
    {
        $envPath = ROOTPATH . '.env';
        $exists = is_file($envPath);
        $readable = $exists && is_readable($envPath);

        return [
            'key' => 'env_file',
            'label' => 'Arquivo .env',
            'severity' => ($exists && $readable) ? 'ok' : 'warning',
            'value' => $envPath,
            'details' => $exists ? ($readable ? 'Arquivo acessível.' : 'Arquivo existe, mas não é legível.') : 'Arquivo ainda não criado.',
        ];
    }


    private function checkSessionSavePath(): array
    {
        $path = BootstrapEnv::sessionSavePath(ROOTPATH);
        $exists = is_dir($path);
        $writable = $exists && is_writable($path);

        return [
            'key' => 'session_save_path',
            'label' => 'session.savePath resolvido',
            'severity' => ($exists && $writable) ? 'ok' : 'blocker',
            'value' => $path,
            'details' => $exists ? ($writable ? 'Diretório resolvido e gravável.' : 'Diretório resolvido existe, mas não é gravável.') : 'Diretório resolvido não existe.',
        ];
    }

    private function checkEncryptionKey(): array
    {
$key = (string) (BootstrapEnv::encryptionKey() ?: '');
        $details = 'Chave ausente.';
        $severity = 'blocker';

        if ($key !== '') {
            $raw = null;
            if (str_starts_with($key, 'base64:')) {
                $raw = base64_decode(substr($key, 7), true);
            } elseif (str_starts_with($key, 'hex2bin:')) {
                $payload = substr($key, 8);
                $raw = ctype_xdigit($payload) ? hex2bin($payload) : false;
            }

            if (is_string($raw) && strlen($raw) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
                $severity = 'ok';
                $details = 'Formato válido e compatível com sodium.';
            } else {
                $severity = 'blocker';
                $details = 'Formato inválido. Esperado base64: ou legado hex2bin: com 32 bytes válidos.';
            }
        }

        return [
            'key' => 'encryption_key',
            'label' => 'encryption.key',
            'severity' => $severity,
            'value' => $key !== '' ? '[CONFIGURADA]' : '[AUSENTE]',
            'details' => $details,
        ];
    }

    private function checkRequiredSecrets(): array
    {
        $required = [
            'JWT_SECRET_KEY' => true,
            'QR_SECRET_KEY' => true,
            'DEEPFACE_API_KEY' => true,
            'security.tokenName' => false,
            'app.baseURL' => false,
                ];

        $checks = [];
        foreach ($required as $key => $blocker) {
            $value = (string) (BootstrapEnv::get($key, '') ?: '');
            $configured = trim($value) !== '';
            $severity = $configured ? 'ok' : ($blocker ? 'blocker' : 'warning');
            $details = $configured ? 'Valor presente no ambiente.' : 'Defina este valor antes da produção.';

            if ($configured && $key === 'JWT_SECRET_KEY') {
                [$jwtSeverity, $jwtDetails] = $this->validateJwtSecretKey($value);
                $severity = $jwtSeverity;
                $details = $jwtDetails;
            }

            $checks[] = [
                'key' => 'secret_' . str_replace(['.', '_'], '-', strtolower($key)),
                'label' => $key,
                'severity' => $severity,
                'value' => $configured ? '[CONFIGURADO]' : '[AUSENTE]',
                'details' => $details,
            ];
        }

        return $checks;
    }


    /**
     * @return array{0:string,1:string}
     */
    private function validateJwtSecretKey(string $secret): array
    {
        $secret = trim($secret);
        $raw = false;

        $base64 = base64_decode($secret, true);
        if (is_string($base64) && $base64 !== '') {
            $raw = $base64;
        } elseif (ctype_xdigit($secret) && strlen($secret) % 2 === 0) {
            $raw = hex2bin($secret);
        }

        if (is_string($raw) && strlen($raw) >= 32) {
            return ['ok', 'JWT_SECRET_KEY compatível com baseline segura: 32 bytes ou mais.'];
        }

        if (strlen($secret) >= 43) {
            return ['warning', 'JWT_SECRET_KEY usa formato textual longo, mas recomenda-se base64_encode(random_bytes(32)) para compatibilidade e previsibilidade.'];
        }

        return ['blocker', 'JWT_SECRET_KEY fraca ou curta demais. Use base64_encode(random_bytes(32)) ou outro segredo com pelo menos 32 bytes de entropia.'];
    }

    private function checkDatabaseConfiguration(bool $withConnections): array
    {
        $dbConfig = config('Database');
        $group = $dbConfig->defaultGroup ?? 'default';
        $settings = $dbConfig->{$group} ?? [];
        $hostname = is_array($settings) ? ($settings['hostname'] ?? '') : ($settings->hostname ?? '');
        $database = is_array($settings) ? ($settings['database'] ?? '') : ($settings->database ?? '');
        $username = is_array($settings) ? ($settings['username'] ?? '') : ($settings->username ?? '');

        if ($hostname === '' || $database === '' || $username === '') {
            return [
                'key' => 'database',
                'label' => 'Banco de dados',
                'severity' => 'blocker',
                'value' => 'configuração incompleta',
                'details' => 'Hostname, database e username são obrigatórios.',
            ];
        }

        if (! $withConnections) {
            return [
                'key' => 'database',
                'label' => 'Banco de dados',
                'severity' => 'ok',
                'value' => $hostname . '/' . $database,
                'details' => 'Configuração presente; teste ativo não executado.',
            ];
        }

        try {
            $db = Database::connect();
            $db->query('SELECT 1');
            return [
                'key' => 'database',
                'label' => 'Banco de dados',
                'severity' => 'ok',
                'value' => $hostname . '/' . $database,
                'details' => 'Conexão validada com sucesso.',
            ];
        } catch (\Throwable $e) {
            return [
                'key' => 'database',
                'label' => 'Banco de dados',
                'severity' => 'blocker',
                'value' => $hostname . '/' . $database,
                'details' => $e->getMessage(),
            ];
        }
    }

    private function checkRedisConfiguration(bool $withConnections): array
    {
$host = (string) (BootstrapEnv::get('REDIS_HOST', '') ?: '');
$port = (int) (BootstrapEnv::get('REDIS_PORT', '6379') ?: 6379);

        if ($host === '') {
            return [
                'key' => 'redis',
                'label' => 'Redis',
                'severity' => 'warning',
                'value' => '[NÃO CONFIGURADO]',
                'details' => 'Defina REDIS_HOST e REDIS_PORT para produção com rate limiting consistente.',
            ];
        }

        if (! $withConnections) {
            return [
                'key' => 'redis',
                'label' => 'Redis',
                'severity' => 'ok',
                'value' => $host . ':' . $port,
                'details' => 'Configuração presente; teste ativo não executado.',
            ];
        }

        $conn = @fsockopen($host, $port, $errno, $errstr, 1.5);
        if (is_resource($conn)) {
            fclose($conn);
            return [
                'key' => 'redis',
                'label' => 'Redis',
                'severity' => 'ok',
                'value' => $host . ':' . $port,
                'details' => 'Porta acessível.',
            ];
        }

        return [
            'key' => 'redis',
            'label' => 'Redis',
            'severity' => 'warning',
            'value' => $host . ':' . $port,
            'details' => trim(($errstr ?: 'não foi possível conectar') . ' (' . $errno . ')'),
        ];
    }

    private function checkDeepFaceConfiguration(bool $withConnections): array
    {
$url = rtrim((string) (BootstrapEnv::get('DEEPFACE_API_URL', '') ?: ''), '/');
$apiKey = (string) (BootstrapEnv::get('DEEPFACE_API_KEY', '') ?: '');

        if ($url === '') {
            return [
                'key' => 'deepface',
                'label' => 'DeepFace API',
                'severity' => 'warning',
                'value' => '[NÃO CONFIGURADA]',
                'details' => 'Defina DEEPFACE_API_URL antes da operação biométrica.',
            ];
        }

        if ($apiKey === '') {
            return [
                'key' => 'deepface',
                'label' => 'DeepFace API',
                'severity' => 'blocker',
                'value' => $url,
                'details' => 'DEEPFACE_API_KEY ausente.',
            ];
        }

        if (! $withConnections || ! function_exists('curl_init')) {
            return [
                'key' => 'deepface',
                'label' => 'DeepFace API',
                'severity' => 'ok',
                'value' => $url,
                'details' => 'Configuração presente; health remoto não testado.',
            ];
        }

        $ch = curl_init($url . '/health');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_HTTPHEADER => ['X-API-Key: ' . $apiKey],
        ]);
        $body = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($http >= 200 && $http < 300) {
            return [
                'key' => 'deepface',
                'label' => 'DeepFace API',
                'severity' => 'ok',
                'value' => $url,
                'details' => 'Health endpoint respondeu com HTTP ' . $http . '.',
            ];
        }

        return [
            'key' => 'deepface',
            'label' => 'DeepFace API',
            'severity' => 'warning',
            'value' => $url,
            'details' => $err !== '' ? $err : ('Health endpoint retornou HTTP ' . $http . '. Body: ' . substr((string) $body, 0, 120)),
        ];
    }




    private function checkBiometricReadiness(bool $withConnections): array
    {
        $service = new BiometricProductionReadinessService();
        $diagnostics = $service->diagnostics($withConnections);
        $summary = $diagnostics['summary'] ?? [];
        $status = $diagnostics['status'] ?? 'error';

        return [
            'key' => 'biometric_readiness',
            'label' => 'Biometria facial / DeepFace',
            'severity' => match ($status) {
                'ok' => 'ok',
                'warning' => 'warning',
                default => 'blocker',
            },
            'value' => sprintf(
                'templates=%d missing=%d orphans=%d',
                (int) ($summary['active_face_templates'] ?? 0),
                (int) ($summary['missing_local_files'] ?? 0),
                (int) ($summary['orphan_files'] ?? 0)
            ),
            'details' => match ($status) {
                'ok' => 'Diagnóstico biométrico operacional sem bloqueadores.',
                'warning' => 'Há pendências controladas na biometria/DeepFace; revise o diagnóstico dedicado.',
                default => 'Há bloqueadores na biometria facial/DeepFace; execute php spark biometric:doctor.',
            },
        ];
    }

    private function overallStatus(array $checks): string
    {
        $severities = array_column($checks, 'severity');
        if (in_array('blocker', $severities, true)) {
            return 'failed';
        }
        if (in_array('warning', $severities, true)) {
            return 'warning';
        }
        return 'ok';
    }
}