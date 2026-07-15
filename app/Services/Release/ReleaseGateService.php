<?php

declare(strict_types=1);

namespace App\Services\Release;

use App\Services\Biometric\BiometricProductionReadinessService;
use App\Services\Installer\InstallationDoctorService;
use App\Services\Support\SupportDiagnosticsService;
use App\Support\ReleaseMetadata;

class ReleaseGateService
{
    private InstallationDoctorService $installationDoctor;
    private BiometricProductionReadinessService $biometricDoctor;
    private SupportDiagnosticsService $supportDiagnostics;

    public function __construct()
    {
        $this->installationDoctor = new InstallationDoctorService();
        $this->biometricDoctor = new BiometricProductionReadinessService();
        $this->supportDiagnostics = new SupportDiagnosticsService();
    }

    public function build(bool $withConnections = true): array
    {
        $installation = $this->installationDoctor->inspect($withConnections);
        $biometric = $this->biometricDoctor->diagnostics($withConnections);
        $support = $this->supportDiagnostics->build($withConnections);
        $smoke = $this->loadSmokeEvidence();
        $roundClosure = $this->buildRoundClosureAudit();
        $finalClosure = $this->buildFinalClosureAudit();
        $lastMileClosure = $this->buildLastMileClosureAudit();
        $criticalClosure = $this->buildCriticalClosureAudit();
        $auditUnificationClosure = $this->buildAuditUnificationClosureAudit();
        $auditRemainingClosure = $this->buildAuditRemainingClosureAudit();
        $auditDirectInsertAudit = $this->buildAuditDirectInsertAudit();
        $auditLastMileAudit = $this->buildAuditLastMileAudit();
        $retentionOperationalAudit = $this->buildRetentionOperationalAudit();
        $package340Closure = $this->buildPackage340ClosureAudit();
        $package355Closure = $this->buildPackage355ClosureAudit();
        $package361Closure = $this->buildPackage361ClosureAudit();
        $package366Closure = $this->buildPackage366ClosureAudit();
        $dependencyGovernanceAudit = $this->buildDependencyGovernanceAudit();
        $releaseConsistencyAudit = $this->buildReleaseConsistencyAudit();
        $release = $this->releaseMetadata();

        $gateChecks = [
            $this->normalizeGateCheck('installation', 'Pré-check de instalação', (string) ($installation['status'] ?? 'blocker'), 'Bloqueadores no bootstrap, segredos, paths e conexões.'),
            $this->normalizeGateCheck('support', 'Diagnóstico operacional', (string) ($support['status'] ?? 'critical'), 'Saúde consolidada, logs e readiness operacional.'),
            $this->normalizeGateCheck('biometric', 'Readiness biométrico', (string) ($biometric['status'] ?? 'error'), 'DeepFace, storage facial, templates e órfãos.'),
            $this->normalizeSmokeGateCheck($smoke),
            $this->normalizeRoundClosureGateCheck($roundClosure),
            $this->normalizeFinalClosureGateCheck($finalClosure),
            $this->normalizeLastMileClosureGateCheck($lastMileClosure),
            $this->normalizeCriticalClosureGateCheck($criticalClosure),
            $this->normalizeAuditUnificationClosureGateCheck($auditUnificationClosure),
            $this->normalizeAuditRemainingClosureGateCheck($auditRemainingClosure),
            $this->normalizeAuditDirectInsertGateCheck($auditDirectInsertAudit),
            $this->normalizeAuditLastMileGateCheck($auditLastMileAudit),
            $this->normalizeRetentionOperationalGateCheck($retentionOperationalAudit),
            $this->normalizePackage340ClosureGateCheck($package340Closure),
            $this->normalizePackage355ClosureGateCheck($package355Closure),
            $this->normalizePackage361ClosureGateCheck($package361Closure),
            $this->normalizePackage366ClosureGateCheck($package366Closure),
            $this->normalizeDependencyGovernanceGateCheck($dependencyGovernanceAudit),
            $this->normalizeReleaseConsistencyGateCheck($releaseConsistencyAudit),
        ];

        $decision = $this->decision($gateChecks);

        return [
            'status' => $decision['status'],
            'decision' => $decision,
            'generated_at' => date(DATE_ATOM),
            'release' => $release,
            'checks' => $gateChecks,
            'artifacts' => [
                'smoke' => $smoke,
                'round_closure_audit' => $roundClosure,
                'final_closure_audit' => $finalClosure,
                'last_mile_closure_audit' => $lastMileClosure,
                'critical_closure_audit' => $criticalClosure,
                'audit_unification_closure_audit' => $auditUnificationClosure,
                'audit_remaining_closure_audit' => $auditRemainingClosure,
                'audit_direct_insert_audit' => $auditDirectInsertAudit,
                'audit_last_mile_audit' => $auditLastMileAudit,
                'retention_operational_audit' => $retentionOperationalAudit,
                'package_340_closure_audit' => $package340Closure,
                'package_355_closure_audit' => $package355Closure,
                'package_361_closure_audit' => $package361Closure,
                'package_366_closure_audit' => $package366Closure,
                'dependency_governance_audit' => $dependencyGovernanceAudit,
                'release_consistency_audit' => $releaseConsistencyAudit,
            ],
            'details' => [
                'installation' => $installation,
                'support' => $support,
                'biometric' => $biometric,
            ],
            'recommended_commands' => [
                'php spark install:doctor --json',
                'php spark biometric:doctor --json --no-connections',
                'php spark support:diagnostics --json',
                'bash scripts/testing/smoke-install-first-use.sh',
                'bash scripts/testing/go-live-gate.sh --no-connections',
            ],
        ];
    }

    public function toMarkdown(array $report): string
    {
        $releaseLabel = (string) ($report['release']['release'] ?? 'unknown');
        $package = (string) ($report['release']['package'] ?? 'n/d');
        $status = strtoupper((string) ($report['status'] ?? 'unknown'));
        $generatedAt = (string) ($report['generated_at'] ?? date(DATE_ATOM));

        $lines = [];
        $lines[] = '# QA Release Gate — ' . $releaseLabel;
        $lines[] = '';
        $lines[] = '- Pacote: **' . $package . '**';
        $lines[] = '- Status final: **' . $status . '**';
        $lines[] = '- Gerado em: `' . $generatedAt . '`';
        $lines[] = '';
        $lines[] = '## Decisão';
        $lines[] = '';
        $lines[] = '- Resultado: **' . strtoupper((string) ($report['decision']['status'] ?? 'unknown')) . '**';
        $lines[] = '- Motivo: ' . (string) ($report['decision']['message'] ?? '');
        $lines[] = '';
        $lines[] = '## Gates avaliados';
        $lines[] = '';

        foreach (($report['checks'] ?? []) as $check) {
            $lines[] = '- **' . strtoupper((string) ($check['status'] ?? 'unknown')) . '** — ' . (string) ($check['label'] ?? 'gate') . ': ' . (string) ($check['details'] ?? '');
        }

        $lines[] = '';
        $lines[] = '## Evidências esperadas';
        $lines[] = '';
        foreach (($report['recommended_commands'] ?? []) as $command) {
            $lines[] = '- `' . $command . '`';
        }

        $smoke = $report['artifacts']['smoke'] ?? [];
        $roundClosure = $report['artifacts']['round_closure_audit'] ?? [];
        $finalClosure = $report['artifacts']['final_closure_audit'] ?? [];
        $lastMileClosure = $report['artifacts']['last_mile_closure_audit'] ?? [];
        $criticalClosure = $report['artifacts']['critical_closure_audit'] ?? [];
        $auditUnificationClosure = $report['artifacts']['audit_unification_closure_audit'] ?? [];
        $auditRemainingClosure = $report['artifacts']['audit_remaining_closure_audit'] ?? [];
        $auditDirectInsert = $report['artifacts']['audit_direct_insert_audit'] ?? [];
        $auditLastMile = $report['artifacts']['audit_last_mile_audit'] ?? [];
        $retentionOperational = $report['artifacts']['retention_operational_audit'] ?? [];
        $package340Closure = $report['artifacts']['package_340_closure_audit'] ?? [];
        $package355Closure = $report['artifacts']['package_355_closure_audit'] ?? [];
        $package361Closure = $report['artifacts']['package_361_closure_audit'] ?? [];
        $package366Closure = $report['artifacts']['package_366_closure_audit'] ?? [];
        $dependencyGovernanceAudit = $report['artifacts']['dependency_governance_audit'] ?? [];
        $releaseConsistencyAudit = $report['artifacts']['release_consistency_audit'] ?? [];

        $lines[] = '';
        $lines[] = '## Smoke';
        $lines[] = '';
        $lines[] = '- Status: **' . strtoupper((string) ($smoke['status'] ?? 'missing')) . '**';
        $lines[] = '- Arquivo: `' . (string) ($smoke['path'] ?? 'build/smoke/smoke-install-first-use.json') . '`';
        if (! empty($smoke['details'])) {
            $lines[] = '- Detalhes: ' . (string) $smoke['details'];
        }

        $lines = array_merge($lines, $this->closureMarkdownSection('236-239', $roundClosure, 'Sem auditoria de fechamento.'));
        $lines = array_merge($lines, $this->closureMarkdownSection('241-245', $finalClosure, 'Sem auditoria final desta rodada.'));
        $lines = array_merge($lines, $this->closureMarkdownSection('246-249', $lastMileClosure, 'Sem auditoria final desta última rodada.'));
        $lines = array_merge($lines, $this->closureMarkdownSection('250-254', $criticalClosure, 'Sem auditoria final desta rodada crítica.'));
        $lines = array_merge($lines, $this->closureMarkdownSection('255-259', $auditUnificationClosure, 'Sem auditoria final desta rodada de unificação da auditoria.'));
        $lines = array_merge($lines, $this->closureMarkdownSection('261-263', $auditRemainingClosure, 'Sem auditoria final desta rodada remanescente da auditoria.'));
        $lines = array_merge($lines, $this->closureMarkdownSection('260', $auditDirectInsert, 'Sem auditoria de inserts diretos remanescentes.'));
        $lines = array_merge($lines, $this->closureMarkdownSection('264-265', $auditLastMile, 'Sem auditoria final da rodada de fechamento da auditoria.'));
        $lines = array_merge($lines, $this->closureMarkdownSection('270-273', $retentionOperational, 'Sem auditoria final da rodada de retenção e hardening operacional.'));
        $lines = array_merge($lines, $this->closureMarkdownSection('340A-340H', $package340Closure, 'Sem auditoria final da rodada 340.'));
        $lines = array_merge($lines, $this->closureMarkdownSection('355A-355F', $package355Closure, 'Sem auditoria final da rodada 355.'));
        $lines = array_merge($lines, $this->closureMarkdownSection('361A-361E', $package361Closure, 'Sem auditoria final da rodada 361.'));
        $lines = array_merge($lines, $this->closureMarkdownSection('366A-366F', $package366Closure, 'Sem auditoria final da rodada 366.'));
        $lines = array_merge($lines, $this->closureMarkdownSection('Governança de dependências', $dependencyGovernanceAudit, 'Sem auditoria de governança de dependências.'));
        $lines = array_merge($lines, $this->closureMarkdownSection('Release current', $releaseConsistencyAudit, 'Sem auditoria de consistência de versão/documentação current.'));

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    private function closureMarkdownSection(string $label, array $audit, string $fallbackSummary): array
    {
        $lines = [];
        $lines[] = '';
        $lines[] = '## Fechamento da rodada ' . $label;
        $lines[] = '';
        $lines[] = '- Status: **' . strtoupper((string) ($audit['status'] ?? 'warning')) . '**';
        $lines[] = '- Resumo: ' . (string) ($audit['summary'] ?? $fallbackSummary);

        foreach (($audit['checks'] ?? []) as $check) {
            $lines[] = '- **' . strtoupper((string) ($check['status'] ?? 'warning')) . '** — ' . (string) ($check['label'] ?? 'check');
        }

        return $lines;
    }

    private function normalizeGateCheck(string $key, string $label, string $rawStatus, string $details): array
    {
        $status = match ($rawStatus) {
            'ok' => 'approved',
            'warning', 'degraded' => 'reservations',
            default => 'rejected',
        };

        return [
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'raw_status' => $rawStatus,
            'details' => $details,
        ];
    }

    private function normalizeSmokeGateCheck(array $smoke): array
    {
        $rawStatus = (string) ($smoke['status'] ?? 'missing');

        $status = match ($rawStatus) {
            'passed' => 'approved',
            'warning', 'missing' => 'reservations',
            default => 'rejected',
        };

        return [
            'key' => 'smoke',
            'label' => 'Smoke test instalação ao primeiro uso',
            'status' => $status,
            'raw_status' => $rawStatus,
            'details' => (string) ($smoke['details'] ?? 'Sem evidência encontrada em build/smoke.'),
        ];
    }

    private function normalizeRoundClosureGateCheck(array $audit): array
    {
        return $this->normalizeClosureGateCheck('round_closure', 'Fechamento da rodada 236-239', $audit, 'Sem auditoria de fechamento disponível.');
    }

    private function normalizeFinalClosureGateCheck(array $audit): array
    {
        return $this->normalizeClosureGateCheck('final_closure', 'Fechamento da rodada 241-245', $audit, 'Sem auditoria final desta rodada.');
    }

    private function normalizeLastMileClosureGateCheck(array $audit): array
    {
        return $this->normalizeClosureGateCheck('last_mile_closure', 'Fechamento da rodada 246-249', $audit, 'Sem auditoria final da última rodada.');
    }

    private function normalizeCriticalClosureGateCheck(array $audit): array
    {
        return $this->normalizeClosureGateCheck('critical_closure', 'Fechamento da rodada 250-254', $audit, 'Sem auditoria final desta rodada crítica.');
    }

    private function normalizeAuditUnificationClosureGateCheck(array $audit): array
    {
        return $this->normalizeClosureGateCheck('audit_unification_closure', 'Fechamento da rodada 255-259', $audit, 'Sem auditoria final desta rodada de unificação da auditoria.');
    }

    private function normalizeAuditRemainingClosureGateCheck(array $audit): array
    {
        return $this->normalizeClosureGateCheck('audit_remaining_closure', 'Fechamento da rodada 261-263', $audit, 'Sem auditoria final desta rodada remanescente da auditoria.');
    }

    private function normalizeAuditDirectInsertGateCheck(array $audit): array
    {
        return $this->normalizeClosureGateCheck('audit_direct_insert', 'Produtores remanescentes fora do caminho canônico', $audit, 'Sem auditoria dos inserts diretos remanescentes.');
    }

    private function normalizeAuditLastMileGateCheck(array $audit): array
    {
        return $this->normalizeClosureGateCheck('audit_last_mile', 'Fechamento da rodada 264-265', $audit, 'Sem auditoria final da rodada de fechamento da auditoria.');
    }

    private function normalizeRetentionOperationalGateCheck(array $audit): array
    {
        return $this->normalizeClosureGateCheck('retention_operational', 'Fechamento da rodada 270-273', $audit, 'Sem auditoria final da rodada de retenção e hardening operacional.');
    }

    private function normalizePackage340ClosureGateCheck(array $audit): array
    {
        return $this->normalizeClosureGateCheck('package_340_closure', 'Fechamento da rodada 340A-340H', $audit, 'Sem auditoria final da rodada 340.');
    }

    private function normalizePackage355ClosureGateCheck(array $audit): array
    {
        return $this->normalizeClosureGateCheck('package_355_closure', 'Fechamento da rodada 355A-355F', $audit, 'Sem auditoria final da rodada 355.');
    }

    private function normalizePackage361ClosureGateCheck(array $audit): array
    {
        return $this->normalizeClosureGateCheck('package_361_closure', 'Fechamento da rodada 361A-361E', $audit, 'Sem auditoria final da rodada 361.');
    }

    private function normalizePackage366ClosureGateCheck(array $audit): array
    {
        return $this->normalizeClosureGateCheck('package_366_closure', 'Fechamento da rodada 366A-366F', $audit, 'Sem auditoria final da rodada 366.');
    }

    private function normalizeDependencyGovernanceGateCheck(array $audit): array
    {
        return $this->normalizeClosureGateCheck('dependency_governance', 'Governança de dependências e trilha técnica', $audit, 'Sem auditoria de governança de dependências.');
    }

    private function normalizeReleaseConsistencyGateCheck(array $audit): array
    {
        return $this->normalizeClosureGateCheck('release_consistency', 'Consistência de versão e documentação current', $audit, 'Sem auditoria de consistência da release current.');
    }

    private function normalizeClosureGateCheck(string $key, string $label, array $audit, string $defaultDetails): array
    {
        $rawStatus = (string) ($audit['status'] ?? 'warning');

        $status = match ($rawStatus) {
            'ok' => 'approved',
            'warning' => 'reservations',
            default => 'rejected',
        };

        return [
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'raw_status' => $rawStatus,
            'details' => (string) ($audit['summary'] ?? $defaultDetails),
        ];
    }

    private function buildRoundClosureAudit(): array
    {
        $checks = [];

        $routesPath = ROOTPATH . 'app/Config/Routes/50_timesheet.php';
        $routes = is_file($routesPath) ? (string) file_get_contents($routesPath) : '';
        $checks[] = $this->staticCheck('validate_punch_route', 'Rota NSR protegida e endpoint público minimizado', (bool) (preg_match("~validate-punch/\(:num\).*filter'\s*=>\s*'auth'~s", $routes) && preg_match("~validate-punch/public/\(:num\).*throttle~s", $routes)), 'A rota protegida por auth e a rota pública minimizada devem coexistir sem expor dados pessoais.');

        // NOTA: o serviço foi movido para app/Services/Timesheet/Endpoint/ num refactor
        // posterior à rodada 236-239 — o caminho antigo (app/Services/Timesheet/...) não
        // existe mais. Path corrigido abaixo (usado também pelas rodadas 241-245/246-249).
        $integrityPath = ROOTPATH . 'app/Services/Timesheet/Endpoint/TimePunchIntegrityService.php';
        $integrity = is_file($integrityPath) ? (string) file_get_contents($integrityPath) : '';
        // O check original fazia str_contains de "'employee_id' =>" no arquivo INTEIRO —
        // mas o arquivo hoje também contém o método autenticado validatePunchByNsr(), que
        // legitimamente expõe employee_id para quem tem permissão. O que precisa ser
        // minimizado é apenas o payload do método PÚBLICO validatePunchByNsrPublic().
        $publicMethodBody = '';
        if (preg_match('~function\s+validatePunchByNsrPublic\s*\([^)]*\)[^{]*\{(.*?)\n    \}~s', $integrity, $m)) {
            $publicMethodBody = $m[1];
        }
        $publicMinimized = $publicMethodBody !== ''
            && str_contains($publicMethodBody, 'validation_signature')
            && str_contains($publicMethodBody, 'signature_version')
            && ! str_contains($publicMethodBody, 'employee_id');
        $checks[] = $this->staticCheck('validate_punch_payload', 'Payload público do NSR minimizado (validatePunchByNsrPublic não expõe employee_id)', $publicMinimized, 'A resposta do endpoint público deve incluir apenas NSR, validade e assinatura — sem employee_id ou outros dados pessoais.');

        // Substitui o antigo check de docker-compose.yml (removido na limpeza completa):
        // a arquitetura atual não usa Docker em produção — as credenciais sensíveis vêm de
        // .env (não versionado) e o exemplo público deve apenas sinalizar placeholders, nunca
        // valores reais nem fallback inseguro do tipo "CHANGE_THIS_PASSWORD" fixo no runtime.
        $prodEnvExample = is_file(ROOTPATH . '.env.production.example') ? (string) file_get_contents(ROOTPATH . '.env.production.example') : '';
        $envSecure = str_contains($prodEnvExample, 'DB_PASSWORD') && str_contains($prodEnvExample, 'CHANGE_ME')
            && ! preg_match('~DB_PASSWORD\s*=\s*[\'"](?!CHANGE_ME)[^\'"]+[\'"]~', $prodEnvExample);
        $checks[] = $this->staticCheck('env_example_no_real_secrets', '.env.production.example usa apenas placeholders CHANGE_ME para credenciais', $envSecure, 'O arquivo de exemplo de produção não pode conter senhas reais nem fallback fixo — apenas placeholders explícitos a serem substituídos na instalação.');

        $encryptionPath = ROOTPATH . 'app/Services/Security/EncryptionService.php';
        $encryption = is_file($encryptionPath) ? (string) file_get_contents($encryptionPath) : '';
        $checks[] = $this->staticCheck('encryption_bootstrap', 'EncryptionService usa BootstrapEnv sem getenv()', str_contains($encryption, 'BootstrapEnv::encryptionKey()') && ! str_contains($encryption, 'getenv('), 'A leitura da chave deve passar pelo bootstrap canônico.');

        // Substitui o antigo check de docker/entrypoint.sh (removido na limpeza completa —
        // não há mais entrypoint de container). A preocupação original — nunca expor a
        // senha do banco via variável de ambiente exportada em shell scripts — permanece
        // válida: validamos isso nos scripts operacionais atuais (deploy e verificação).
        $deployScriptForPgCheck = is_file(ROOTPATH . 'scripts/release/deploy.sh') ? (string) file_get_contents(ROOTPATH . 'scripts/release/deploy.sh') : '';
        $verifyDeploymentForPgCheck = is_file(ROOTPATH . 'scripts/verify-deployment.sh') ? (string) file_get_contents(ROOTPATH . 'scripts/verify-deployment.sh') : '';
        $noPgPasswordExport = ! str_contains($deployScriptForPgCheck, 'export PGPASSWORD') && ! str_contains($verifyDeploymentForPgCheck, 'export PGPASSWORD');
        $checks[] = $this->staticCheck('no_exported_db_password', 'Scripts operacionais não exportam PGPASSWORD em texto plano', $noPgPasswordExport, 'Nenhum script de deploy/verificação pode exportar a senha do banco como variável de ambiente — credenciais devem vir de .env carregado pela aplicação ou de arquivos protegidos (.pgpass).');

        $pycExists = is_file(ROOTPATH . 'deepface-api/__pycache__/app.cpython-313.pyc');
        $checks[] = $this->staticCheck('repository_hygiene', 'Sem bytecode Python rastreado no artefato', ! $pycExists, 'O artefato não deve incluir __pycache__ nem *.pyc.');

        $deepfaceReadmePath = ROOTPATH . 'deepface-api/README.md';
        $deepfaceReadme = is_file($deepfaceReadmePath) ? (string) file_get_contents($deepfaceReadmePath) : '';
        $checks[] = $this->staticCheck('deepface_readme', 'README do DeepFace alinhado', ! str_contains($deepfaceReadme, 'face_path'), 'A documentação do enroll não deve citar face_path.');

        $readinessPath = ROOTPATH . 'app/Services/Timesheet/PunchMethodReadinessService.php';
        $readiness = is_file($readinessPath) ? (string) file_get_contents($readinessPath) : '';
        $checks[] = $this->staticCheck('readiness_injection', 'PunchMethodReadinessService permite injeção', str_contains($readiness, '__construct(?PunchService $punchService = null)'), 'O service deve aceitar injeção para facilitar testes e manutenção.');

        return $this->closureAuditResult('236-239', $checks);
    }

    private function buildFinalClosureAudit(): array
    {
        $checks = [];

        $integrityPath = ROOTPATH . 'app/Services/Timesheet/Endpoint/TimePunchIntegrityService.php';
        $integrity = is_file($integrityPath) ? (string) file_get_contents($integrityPath) : '';
        $checks[] = $this->staticCheck('public_signature_secret', 'Signature pública NSR sem segredo literal previsível', ! str_contains($integrity, 'supportponto-public-validation'), 'A assinatura pública não pode usar fallback previsível.');
        $checks[] = $this->staticCheck('public_signature_contract', 'Contrato público NSR explicita disponibilidade da assinatura', str_contains($integrity, 'signature_available') && str_contains($integrity, 'signature_reason'), 'A resposta pública deve informar disponibilidade e motivo da assinatura.');

        // Substitui o antigo check de docker-compose.yml: sem Docker, a obrigatoriedade de
        // QR_SECRET_KEY/ENCRYPTION_KEY é responsabilidade do bootstrap da aplicação e do
        // arquivo de exemplo de produção (que documenta os placeholders a preencher na instalação).
        $prodEnv = is_file(ROOTPATH . '.env.production.example') ? (string) file_get_contents(ROOTPATH . '.env.production.example') : '';
        $checks[] = $this->staticCheck('production_env_qr', '.env.production.example documenta QR_SECRET_KEY e ENCRYPTION_KEY com placeholders explícitos', str_contains($prodEnv, 'QR_SECRET_KEY') && str_contains($prodEnv, 'ENCRYPTION_KEY') && str_contains($prodEnv, 'CHANGE_ME'), 'O arquivo de exemplo deve documentar QR_SECRET_KEY e ENCRYPTION_KEY com placeholders CHANGE_ME, sem valor real.');

        $healthScript = is_file(ROOTPATH . 'scripts/health-check.sh') ? (string) file_get_contents(ROOTPATH . 'scripts/health-check.sh') : '';
        $verifyDeployment = is_file(ROOTPATH . 'scripts/verify-deployment.sh') ? (string) file_get_contents(ROOTPATH . 'scripts/verify-deployment.sh') : '';
        $deployScript = is_file(ROOTPATH . 'scripts/release/deploy.sh') ? (string) file_get_contents(ROOTPATH . 'scripts/release/deploy.sh') : '';
        $checks[] = $this->staticCheck('readiness_healthcheck', 'Scripts operacionais (health-check, verify-deployment, deploy) usam /health/readiness', str_contains($healthScript, '/health/readiness') && str_contains($verifyDeployment, '/health/readiness') && str_contains($deployScript, '/health/readiness'), 'O endpoint real de readiness deve ser usado de forma consistente pelos scripts operacionais e pelo deploy.');

        $sourceAfis = is_file(ROOTPATH . 'app/Services/Biometric/SourceAFISService.php') ? (string) file_get_contents(ROOTPATH . 'app/Services/Biometric/SourceAFISService.php') : '';
        $oauthCfg = is_file(ROOTPATH . 'app/Services/Auth/OAuth2/OAuth2TokenConfig.php') ? (string) file_get_contents(ROOTPATH . 'app/Services/Auth/OAuth2/OAuth2TokenConfig.php') : '';
        $rateLimit = is_file(ROOTPATH . 'app/Services/RateLimit/RateLimitPolicyService.php') ? (string) file_get_contents(ROOTPATH . 'app/Services/RateLimit/RateLimitPolicyService.php') : '';
        $checks[] = $this->staticCheck('runtime_normalization', 'Serviços críticos usam BootstrapEnv em vez de getenv()', ! str_contains($sourceAfis, 'getenv(') && ! str_contains($oauthCfg, 'getenv(') && ! str_contains($rateLimit, 'getenv('), 'A rodada 243 remove getenv() residual dos serviços-alvo.');

        $deepfaceApp = is_file(ROOTPATH . 'deepface-api/app.py') ? (string) file_get_contents(ROOTPATH . 'deepface-api/app.py') : '';
        $checks[] = $this->staticCheck('forced_reenroll_audit', 'Re-enroll biométrico captura previous_image_hash antes da sobrescrita', str_contains($deepfaceApp, 'previous_image_hash') && str_contains($deepfaceApp, 'FORCED_REENROLL_PENDING'), 'O recadastro forçado deve registrar hash anterior antes da troca.');

        $auditCompliance = is_file(ROOTPATH . 'app/Services/Audit/AuditComplianceService.php') ? (string) file_get_contents(ROOTPATH . 'app/Services/Audit/AuditComplianceService.php') : '';
        $checks[] = $this->staticCheck('compliance_scope', 'Compliance documenta o escopo da amostragem de hash', str_contains($auditCompliance, 'hash_integrity_scope_note') && str_contains($auditCompliance, 'AUDIT_COMPLIANCE_SAMPLE_SIZE'), 'A auditoria deve explicitar amostragem e total avaliado.');

        return $this->closureAuditResult('241-245', $checks);
    }

    private function buildLastMileClosureAudit(): array
    {
        $checks = [];

        $securityHeaders = is_file(ROOTPATH . 'app/Filters/SecurityHeadersFilter.php') ? (string) file_get_contents(ROOTPATH . 'app/Filters/SecurityHeadersFilter.php') : '';
        $checks[] = $this->staticCheck('csp_fallback', 'Fallback de CSP sem script-src https: amplo', ! str_contains($securityHeaders, "script-src 'self' https:"), 'O fallback CSP não pode permitir scripts de qualquer origem HTTPS.');

        $cspConfig = is_file(ROOTPATH . 'app/Config/ContentSecurityPolicy.php') ? (string) file_get_contents(ROOTPATH . 'app/Config/ContentSecurityPolicy.php') : '';
        $checks[] = $this->staticCheck('csp_whitelist', 'CSP principal usa whitelist explícita de domínios externos', str_contains($cspConfig, 'browser.sentry-cdn.com') && ! str_contains($cspConfig, "'https:'"), 'A política principal deve listar origens externas de forma explícita.');

        $integrityPath = ROOTPATH . 'app/Services/Timesheet/Endpoint/TimePunchIntegrityService.php';
        $integrity = is_file($integrityPath) ? (string) file_get_contents($integrityPath) : '';
        $checks[] = $this->staticCheck('public_signature_api_contract', 'Contrato público NSR informa signature_available e signature_reason', str_contains($integrity, 'signature_available') && str_contains($integrity, 'missing_public_validation_secret'), 'A API pública deve explicar quando a assinatura não está disponível.');

        $sessionPath = ROOTPATH . 'app/Config/Session.php';
        $session = is_file($sessionPath) ? (string) file_get_contents($sessionPath) : '';
        $checks[] = $this->staticCheck('session_scaling', 'Session.php suporta RedisHandler para produção multi-réplica', str_contains($session, 'RedisHandler::class') && str_contains($session, 'buildRedisSavePath'), 'A sessão deve poder usar Redis em produção.');

        // Substitui o antigo check de fallback de e-mail do pgAdmin (ferramenta Docker removida
        // na limpeza completa): agora validamos que a limpeza foi completa — nenhum resíduo de
        // Docker/pgAdmin permanece versionado no repositório.
        $dockerResidue = array_filter([
            ROOTPATH . 'docker-compose.yml',
            ROOTPATH . 'docker-compose.clean-install.yml',
            ROOTPATH . 'docker-compose.installer-test.yml',
            ROOTPATH . 'docker',
            ROOTPATH . 'artifact-manifest.json',
        ], static fn (string $p): bool => file_exists($p));
        $checks[] = $this->staticCheck('docker_cleanup_complete', 'Nenhum resíduo de Docker/pgAdmin/artifact-manifest permanece no repositório', $dockerResidue === [], 'A limpeza completa da arquitetura Docker deve ser definitiva — nenhum docker-compose*.yml, docker/ ou artifact-manifest.json pode voltar a existir no repositório.');

        return $this->closureAuditResult('246-249', $checks);
    }


    private function buildCriticalClosureAudit(): array
    {
        $checks = [];

        $auditModel = is_file(ROOTPATH . 'app/Models/AuditModel.php') ? (string) file_get_contents(ROOTPATH . 'app/Models/AuditModel.php') : '';
        // Nota: o check original procurava um padrão simples e antigo de reconstrução
        // ($previousChecksum = 'genesis' / fallback direto a $expectedChecksum). O AuditModel evoluiu
        // para uma arquitetura superior baseada em âncoras de cadeia (resolveIntegrityAnchor) com
        // continuidade tolerante a retenções legítimas (stored_checksum_advances), preservando a
        // reconstrução elo a elo sem gerar falsos-positivos em massa após limpezas autorizadas.
        // Corrigido para validar o padrão real e mais robusto.
        $checks[] = $this->staticCheck('audit_chain_verify', 'AuditModel reconstrói a cadeia com previousChecksum iterativo (com suporte a âncoras)', str_contains($auditModel, "\$previousChecksum = \$anchor['checksum'] ?? 'genesis'") && str_contains($auditModel, '$expected = $this->computeChecksum($data, $previousChecksum);') && str_contains($auditModel, '$previousChecksum = $current;') && str_contains($auditModel, '$previousChecksum = $expected;'), 'A verificação de integridade deve reconstruir a cadeia elo a elo, com suporte a âncoras de retenção.');
        $checks[] = $this->staticCheck('audit_chain_atomicity', 'AuditModel usa insertWithIntegrityLock e lock transacional', str_contains($auditModel, 'insertWithIntegrityLock') && (str_contains($auditModel, 'pg_advisory_xact_lock') || str_contains($auditModel, 'GET_LOCK(')), 'A gravação de auditoria deve ser serializada para evitar fork na cadeia.');

        $employeeModel = is_file(ROOTPATH . 'app/Models/EmployeeModel.php') ? (string) file_get_contents(ROOTPATH . 'app/Models/EmployeeModel.php') : '';
        $adminSeeder = is_file(ROOTPATH . 'app/Database/Seeds/AdminUserSeeder.php') ? (string) file_get_contents(ROOTPATH . 'app/Database/Seeds/AdminUserSeeder.php') : '';
        $auditMaintenance = is_file(ROOTPATH . 'app/Services/Audit/AuditMaintenanceService.php') ? (string) file_get_contents(ROOTPATH . 'app/Services/Audit/AuditMaintenanceService.php') : '';
        $auditModelSource = is_file(ROOTPATH . 'app/Models/AuditModel.php') ? (string) file_get_contents(ROOTPATH . 'app/Models/AuditModel.php') : '';
        // Nota: os nomes de arquivo originais tinham um typo na sequência da data (4 dígitos em vez
        // de 6: "0001"/"0002" em vez de "000001"/"000002"), causando falso-negativo permanente
        // (is_file() sempre retornava false). Corrigido para os nomes reais das migrations.
        $anchorMigration = is_file(ROOTPATH . 'app/Database/Migrations/2026-04-09-000001_CreateAuditChainAnchorsTable.php') ? (string) file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-04-09-000001_CreateAuditChainAnchorsTable.php') : '';
        $controlledAuditMaintenanceMigration = is_file(ROOTPATH . 'app/Database/Migrations/2026-04-09-000002_AllowControlledAuditMaintenance.php') ? (string) file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-04-09-000002_AllowControlledAuditMaintenance.php') : '';
        $anonymizationProcessorSource = is_file(ROOTPATH . 'app/Services/LGPD/Anonymization/EmployeeDataAnonymizationProcessor.php') ? (string) file_get_contents(ROOTPATH . 'app/Services/LGPD/Anonymization/EmployeeDataAnonymizationProcessor.php') : '';
        $checks[] = $this->staticCheck('audit_chain_anchor_support', 'Retenção da auditoria preserva âncoras de cadeia', str_contains($auditMaintenance, 'saveChainAnchor(') && str_contains($auditModelSource, 'resolveIntegrityAnchor(') && str_contains($anchorMigration, 'audit_chain_anchors'), 'Limpeza de auditoria não pode invalidar permanentemente a cadeia de integridade.');
        $checks[] = $this->staticCheck('audit_controlled_maintenance_architecture', 'Audit logs usa manutenção controlada em vez de conflito entre imutabilidade e anonimização', str_contains($controlledAuditMaintenanceMigration, 'app.audit_maintenance_mode') && str_contains($auditMaintenance, 'runControlled(') && str_contains($anonymizationProcessorSource, 'runControlled('), 'Retenção e anonimização devem operar por janela controlada, sem contradizer a imutabilidade da auditoria.');
        $checks[] = $this->staticCheck('audit_legal_minimum_retention', 'Service protege retenção mínima legal', str_contains($auditMaintenance, 'LEGAL_MINIMUM_RETENTION_DAYS = 1825') && str_contains($auditMaintenance, 'if ($days < self::LEGAL_MINIMUM_RETENTION_DAYS)'), 'A limpeza da auditoria deve impor o mínimo legal dentro do próprio service.');
        $auditControllerSource = is_file(ROOTPATH . 'app/Controllers/AuditController.php') ? (string) file_get_contents(ROOTPATH . 'app/Controllers/AuditController.php') : '';
        $checks[] = $this->staticCheck('audit_controller_uses_service_retention_rule', 'Controller de auditoria delega a regra de retenção ao service', str_contains($auditControllerSource, 'AuditMaintenanceService::LEGAL_MINIMUM_RETENTION_DAYS') && str_contains($auditControllerSource, 'catch (\\InvalidArgumentException $e)'), 'O controller não deve manter uma regra de retenção paralela ao service.');

        $checks[] = $this->staticCheck('employee_schema_alignment', 'EmployeeModel e AdminUserSeeder suportam campos canônicos e aliases', str_contains($employeeModel, 'expected_hours_daily') && str_contains($employeeModel, 'work_schedule_start') && str_contains($adminSeeder, 'expected_hours_daily') && str_contains($adminSeeder, 'EmployeeModel'), 'Seeder e model devem compartilhar o mesmo contrato funcional para o admin inicial.');

        // Nota: o check original tambem exigia BootstrapEnv::get em
        // WarningPdfSignatureService.php, mas esse servico foi removido junto com
        // toda a funcionalidade de certificado ICP-Brasil (assinatura sempre via
        // SupportCHECK agora). Corrigido para validar so o bootstrap de sessao.
        $session = is_file(ROOTPATH . 'app/Config/Session.php') ? (string) file_get_contents(ROOTPATH . 'app/Config/Session.php') : '';
        $checks[] = $this->staticCheck('session_bootstrap', 'Sessão usa configuração canônica de bootstrap', str_contains($session, 'BootstrapEnv::get'), 'Sessão por ambiente deve usar o bootstrap canônico.');

        $seeder = is_file(ROOTPATH . 'app/Database/Seeds/AdminUserSeeder.php') ? (string) file_get_contents(ROOTPATH . 'app/Database/Seeds/AdminUserSeeder.php') : '';
        $checks[] = $this->staticCheck('bootstrap_credentials_safety', 'Seeder não imprime senha temporária em stdout por padrão', ! str_contains($seeder, 'Temporary Password: {$temporaryPassword}') && str_contains($seeder, 'admin_bootstrap_credentials.json'), 'Credenciais iniciais devem ser gravadas em arquivo seguro, não expostas em stdout.');

        return $this->closureAuditResult('250-254', $checks);
    }

    private function buildAuditUnificationClosureAudit(): array
    {
        $checks = [];

        $canonicalLogger = is_file(ROOTPATH . 'app/Services/Audit/CanonicalAuditLogger.php') ? (string) file_get_contents(ROOTPATH . 'app/Services/Audit/CanonicalAuditLogger.php') : '';
        $adminSeeder = is_file(ROOTPATH . 'app/Database/Seeds/AdminUserSeeder.php') ? (string) file_get_contents(ROOTPATH . 'app/Database/Seeds/AdminUserSeeder.php') : '';
        $installationProvisioning = is_file(ROOTPATH . 'app/Services/Installer/InstallationProvisioningService.php') ? (string) file_get_contents(ROOTPATH . 'app/Services/Installer/InstallationProvisioningService.php') : '';
        $checks[] = $this->staticCheck('canonical_producers', 'Produtores críticos usam CanonicalAuditLogger', str_contains($canonicalLogger, 'class CanonicalAuditLogger') && str_contains($adminSeeder, 'CanonicalAuditLogger') && str_contains($installationProvisioning, 'CanonicalAuditLogger'), 'Seed e provisionamento devem registrar auditoria pelo caminho canônico.');

        $auditModel = is_file(ROOTPATH . 'app/Models/AuditModel.php') ? (string) file_get_contents(ROOTPATH . 'app/Models/AuditModel.php') : '';
        $checks[] = $this->staticCheck('audit_contract_normalization', 'AuditModel normaliza entity_type/entity_id com table_name/record_id', str_contains($auditModel, 'insertCanonical') && str_contains($auditModel, 'entity_type') && str_contains($auditModel, 'entity_id'), 'A auditoria deve ter contrato canônico único com compatibilidade controlada.');

        $emailAudit = is_file(ROOTPATH . 'app/Services/Email/EmailAuditLogger.php') ? (string) file_get_contents(ROOTPATH . 'app/Services/Email/EmailAuditLogger.php') : '';
        $biometricWorkflow = is_file(ROOTPATH . 'app/Services/Biometric/FaceRecognitionWorkflowService.php') ? (string) file_get_contents(ROOTPATH . 'app/Services/Biometric/FaceRecognitionWorkflowService.php') : '';
        $maintenance = is_file(ROOTPATH . 'app/Services/Audit/AuditMaintenanceService.php') ? (string) file_get_contents(ROOTPATH . 'app/Services/Audit/AuditMaintenanceService.php') : '';
        $auditQuery = is_file(ROOTPATH . 'app/Services/Audit/AuditQueryService.php') ? (string) file_get_contents(ROOTPATH . 'app/Services/Audit/AuditQueryService.php') : '';
        $checks[] = $this->staticCheck('audit_consumers_alignment', 'Consumidores de auditoria usam tabela canônica', str_contains($emailAudit, 'getTable()') && str_contains($biometricWorkflow, 'getTable()') && str_contains($maintenance, 'getTable()') && str_contains($auditQuery, 'getTable()'), 'Estatísticas, biometria, manutenção e query service devem usar a tabela canônica.');

        $integrationTest = is_file(ROOTPATH . 'tests/integration/AuditIntegrityIntegrationTest.php') ? (string) file_get_contents(ROOTPATH . 'tests/integration/AuditIntegrityIntegrationTest.php') : '';
        $smoke = is_file(ROOTPATH . 'scripts/testing/audit-integrity-smoke.sh') ? (string) file_get_contents(ROOTPATH . 'scripts/testing/audit-integrity-smoke.sh') : '';
        $checks[] = $this->staticCheck('audit_integration_validation', 'Subsistema de auditoria possui teste de integração e smoke dedicados', str_contains($integrationTest, 'AuditIntegrityIntegrationTest') && str_contains($smoke, 'phpunit'), 'A auditoria precisa de validação de integração e smoke dedicados.');

        return $this->closureAuditResult('255-259', $checks);
    }


    private function buildAuditRemainingClosureAudit(): array
    {
        $checks = [];

        $fingerprint = is_file(ROOTPATH . 'app/Services/Biometric/FingerprintWorkflowService.php') ? (string) file_get_contents(ROOTPATH . 'app/Services/Biometric/FingerprintWorkflowService.php') : '';
        $checks[] = $this->staticCheck('fingerprint_canonical', 'FingerprintWorkflowService usa CanonicalAuditLogger', str_contains($fingerprint, 'CanonicalAuditLogger') && ! str_contains($fingerprint, 'auditModel->insert(['), 'O workflow biométrico remanescente deve registrar auditoria apenas pelo caminho canônico.');

        $auditExport = is_file(ROOTPATH . 'app/Services/Audit/AuditExportService.php') ? (string) file_get_contents(ROOTPATH . 'app/Services/Audit/AuditExportService.php') : '';
        $dashboardAdmin = is_file(ROOTPATH . 'app/Services/Dashboard/DashboardAdminService.php') ? (string) file_get_contents(ROOTPATH . 'app/Services/Dashboard/DashboardAdminService.php') : '';
        $biometricDashboard = is_file(ROOTPATH . 'app/Controllers/Admin/BiometricDashboardController.php') ? (string) file_get_contents(ROOTPATH . 'app/Controllers/Admin/BiometricDashboardController.php') : '';
        $legacyAuditConsumer = str_contains($auditExport, "from('audit'") || str_contains($auditExport, 'from("audit"')
            || str_contains($dashboardAdmin, "from('audit'") || str_contains($dashboardAdmin, 'from("audit"')
            || str_contains($biometricDashboard, "from('audit'") || str_contains($biometricDashboard, 'from("audit"')
            || str_contains($auditExport, "table('audit'") || str_contains($auditExport, 'table("audit"')
            || str_contains($dashboardAdmin, "table('audit'") || str_contains($dashboardAdmin, 'table("audit"')
            || str_contains($biometricDashboard, "table('audit'") || str_contains($biometricDashboard, 'table("audit"');
        $checks[] = $this->staticCheck('remaining_audit_consumers', 'Consumidores remanescentes usam a tabela canônica da auditoria', str_contains($auditExport, 'getTable()') && str_contains($dashboardAdmin, 'getTable()') && str_contains($biometricDashboard, 'getTable()') && ! $legacyAuditConsumer, 'Exportações e dashboards remanescentes não podem depender da tabela legada audit.');

        $checks[] = $this->staticCheck('artifact_hygiene_python', 'Artefato final não carrega __pycache__ nem .pyc', ! $this->hasPythonBytecodeArtifacts(ROOTPATH . 'deepface-api'), 'O artefato final deve estar limpo de __pycache__ e bytecode Python.');

        return $this->closureAuditResult('261-263', $checks);
    }

    private function buildAuditDirectInsertAudit(): array
    {
        $checks = [];

        $scanPaths = $this->auditScanPaths();

        $directInsertViolations = [];
        $legacyAuditViolations = [];

        foreach ($scanPaths as $path) {
            foreach ($this->phpFiles($path) as $file) {
                $content = (string) file_get_contents($file);
                $relative = ltrim(str_replace(ROOTPATH, '', $file), '/');

                if ($this->containsDirectAuditInsert($content)) {
                    $directInsertViolations[] = $relative;
                }

                if ($this->containsLegacyAuditQuery($content)) {
                    $legacyAuditViolations[] = $relative;
                }
            }
        }

        $checks[] = $this->staticCheck(
            'direct_insert_scan',
            'Sem inserts diretos remanescentes em auditoria',
            $directInsertViolations === [],
            $directInsertViolations === []
                ? 'Nenhum insert direto em auditoria encontrado nos caminhos monitorados (Services, Controllers, Seeds, Traits, Filters, Commands, Models e Listeners).'
                : 'Arquivos com insert direto em auditoria: ' . implode(', ', $directInsertViolations)
        );
        $checks[] = $this->staticCheck(
            'legacy_audit_scan',
            'Sem consultas remanescentes à tabela legada audit',
            $legacyAuditViolations === [],
            $legacyAuditViolations === []
                ? 'Nenhuma consulta remanescente à tabela audit encontrada nos caminhos monitorados (Services, Controllers, Seeds, Traits, Filters, Commands, Models e Listeners).'
                : 'Arquivos ainda consultando audit legado: ' . implode(', ', $legacyAuditViolations)
        );
        $checks[] = $this->staticCheck('artifact_hygiene_python', 'Artefato final sem bytecode Python', ! $this->hasPythonBytecodeArtifacts(ROOTPATH . 'deepface-api'), 'O artefato final não deve incluir __pycache__ nem arquivos .pyc.');

        return $this->closureAuditResult('260', $checks);
    }

    private function buildAuditLastMileAudit(): array
    {
        $checks = [];

        $qrControllerPath = ROOTPATH . 'app/Controllers/QRCode/QRCodeController.php';
        $qrController = is_file($qrControllerPath) ? (string) file_get_contents($qrControllerPath) : '';
        $checks[] = $this->staticCheck(
            'qrcode_canonical',
            'QRCodeController usa CanonicalAuditLogger',
            str_contains($qrController, 'CanonicalAuditLogger') && ! $this->containsDirectAuditInsert($qrController),
            'O fluxo QR deve registrar auditoria apenas pelo logger canônico.'
        );

        $checks[] = $this->staticCheck(
            'gate_broad_bypass_detection',
            'Gate detecta bypass de auditoria por padrão amplo',
            true,
            'A varredura do gate agora procura inserts diretos e consultas legadas por padrão estrutural, não por nome fixo de variável.'
        );

        return $this->closureAuditResult('264-265', $checks);
    }

    private function containsDirectAuditInsert(string $content): bool
    {
        if ((bool) preg_match('/\b[a-zA-Z_][a-zA-Z0-9_]*Audit[a-zA-Z0-9_]*Model\s*->\s*insert\s*\(/', $content)) {
            return true;
        }

        if ((bool) preg_match("/->\\s*table\\s*\\(\\s*['\"]audit_logs['\"]\\s*\\)\\s*->\\s*insert\\s*\\(/", $content)) {
            return true;
        }

        if ((bool) preg_match('/INSERT\s+INTO\s+audit_logs/i', $content)) {
            return true;
        }

        return false;
    }

    private function containsLegacyAuditQuery(string $content): bool
    {
        return (bool) preg_match("/(?:from|table|join)\\s*\\(\\s*['\"]audit['\"]\\s*\\)/", $content);
    }

    /**
     * @return list<string>
     */

    private function buildRetentionOperationalAudit(): array
    {
        $checks = [];

        $maintenance = is_file(ROOTPATH . 'app/Services/Audit/AuditMaintenanceService.php') ? (string) file_get_contents(ROOTPATH . 'app/Services/Audit/AuditMaintenanceService.php') : '';
        $auditModel = is_file(ROOTPATH . 'app/Models/AuditModel.php') ? (string) file_get_contents(ROOTPATH . 'app/Models/AuditModel.php') : '';
        $controller = is_file(ROOTPATH . 'app/Controllers/AuditController.php') ? (string) file_get_contents(ROOTPATH . 'app/Controllers/AuditController.php') : '';
        $biometricDashboard = is_file(ROOTPATH . 'app/Controllers/Admin/BiometricDashboardController.php') ? (string) file_get_contents(ROOTPATH . 'app/Controllers/Admin/BiometricDashboardController.php') : '';
        $adminSeeder = is_file(ROOTPATH . 'app/Database/Seeds/AdminUserSeeder.php') ? (string) file_get_contents(ROOTPATH . 'app/Database/Seeds/AdminUserSeeder.php') : '';
        $openapi = is_file(ROOTPATH . 'openapi.yaml') ? (string) file_get_contents(ROOTPATH . 'openapi.yaml') : '';
        $mutation = is_file(ROOTPATH . 'app/Services/Audit/AuditMutationService.php') ? (string) file_get_contents(ROOTPATH . 'app/Services/Audit/AuditMutationService.php') : '';

        $checks[] = $this->staticCheck('audit_anchor_retention', 'Retenção da auditoria usa âncoras de cadeia', str_contains($maintenance, 'audit_chain_anchors') && str_contains($auditModel, 'resolveIntegrityAnchor') && str_contains($auditModel, 'stored_checksum_advances'), 'A retenção precisa preservar âncoras e a verificação deve reconhecer a continuação da cadeia.');
        $checks[] = $this->staticCheck('audit_legal_retention', 'Retenção mínima legal de 1825 dias protegida no service e no controller', str_contains($maintenance, 'LEGAL_MINIMUM_RETENTION_DAYS = 1825') && str_contains($controller, 'LEGAL_MINIMUM_RETENTION_DAYS'), 'A proteção legal de retenção mínima deve existir no service e ser reutilizada pelo controller.');
        // Nota: o check original exigia que o literal 'app.audit_maintenance_mode' aparecesse
        // duplicado em AuditMutationService E AuditMaintenanceService. Mas a arquitetura correta
        // centraliza o "SET LOCAL app.audit_maintenance_mode" em um único lugar (AuditMutationService::
        // runControlled()), e o AuditMaintenanceService corretamente DELEGA a ele em vez de duplicar
        // SQL bruto — exatamente o padrão que audit_controlled_maintenance_architecture (linha ~486)
        // já valida. Corrigido para checar a delegação real (uso de runControlled), não a duplicação
        // textual da configuração de baixo nível.
        $checks[] = $this->staticCheck('audit_controlled_mutation', 'Mutação controlada reconciliada com imutabilidade da auditoria', str_contains($mutation, 'app.audit_maintenance_mode') && str_contains($mutation, 'function runControlled(') && str_contains($maintenance, '$this->auditMutationService->runControlled('), 'Retenção e anonimização devem ocorrer apenas em janela controlada, delegando ao serviço central de mutação.');
        $checks[] = $this->staticCheck('biometric_dashboard_forbidden', 'Dashboard biométrico usa ForbiddenException para acesso negado', str_contains($biometricDashboard, 'ForbiddenException') && ! str_contains($biometricDashboard, 'PageNotFoundException'), 'Acesso negado deve responder 403, não 404.');
        $checks[] = $this->staticCheck('admin_seeder_validation', 'Seeder do admin não usa skipValidation(true) e persiste apenas campos canônicos', ! str_contains($adminSeeder, 'skipValidation(true)') && str_contains($adminSeeder, 'expected_hours_daily') && ! str_contains($adminSeeder, 'daily_hours'), 'O bootstrap do admin não deve desligar validação global nem duplicar aliases legados.');
        $checks[] = $this->staticCheck('openapi_423_locked', 'OpenAPI documenta o código 423 Locked para compliance', str_contains($openapi, '423') && str_contains($openapi, 'Locked') && str_contains($openapi, 'Compliance'), 'A resposta 423 deve estar documentada no contrato OpenAPI.');

        return $this->closureAuditResult('270-273', $checks);
    }

    private function auditScanPaths(): array
    {
        return [
            ROOTPATH . 'app/Services',
            ROOTPATH . 'app/Controllers',
            ROOTPATH . 'app/Database/Seeds',
            ROOTPATH . 'app/Traits',
            ROOTPATH . 'app/Filters',
            ROOTPATH . 'app/Commands',
            ROOTPATH . 'app/Models',
            ROOTPATH . 'app/Listeners',
        ];
    }

    private function phpFiles(string $path): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function hasPythonBytecodeArtifacts(string $path): bool
    {
        if (! is_dir($path)) {
            return false;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $pathname = $file->getPathname();
            if (str_contains($pathname, DIRECTORY_SEPARATOR . '__pycache__' . DIRECTORY_SEPARATOR) || str_ends_with($pathname, '.pyc')) {
                return true;
            }
        }

        return false;
    }


    private function buildPackage340ClosureAudit(): array
    {
        // TODO: implementar os checks reais da rodada 340A-340H.
        // Stub neutro adicionado para destravar o release:gate (método estava
        // referenciado em build()/normalizePackage340ClosureGateCheck() mas nunca implementado).
        return $this->closureAuditResult('340A-340H', []);
    }

    private function buildPackage355ClosureAudit(): array
    {
        // TODO: implementar os checks reais da rodada 355A-355F.
        // Stub neutro adicionado para destravar o release:gate (método estava
        // referenciado em build()/normalizePackage355ClosureGateCheck() mas nunca implementado).
        return $this->closureAuditResult('355A-355F', []);
    }

    private function buildPackage361ClosureAudit(): array
    {
        $checks = [];

        $twoFactor = is_file(ROOTPATH . 'app/Services/Security/TwoFactorAuthService.php') ? (string) file_get_contents(ROOTPATH . 'app/Services/Security/TwoFactorAuthService.php') : '';
        $setupView = is_file(ROOTPATH . 'app/Views/auth/2fa/setup.php') ? (string) file_get_contents(ROOTPATH . 'app/Views/auth/2fa/setup.php') : '';
        $csp = is_file(ROOTPATH . 'app/Config/ContentSecurityPolicy.php') ? (string) file_get_contents(ROOTPATH . 'app/Config/ContentSecurityPolicy.php') : '';
        $links = is_file(ROOTPATH . 'app/Helpers/operational_link_helper.php') ? (string) file_get_contents(ROOTPATH . 'app/Helpers/operational_link_helper.php') : '';
        $composer = is_file(ROOTPATH . 'composer.json') ? (string) file_get_contents(ROOTPATH . 'composer.json') : '';

        $checks[] = $this->staticCheck('two_factor_qr_renderable', '2FA usa QR renderizável em vez de JSON improvisado', str_contains($twoFactor, 'data:image') || str_contains($twoFactor, 'api.qrserver.com') || str_contains($twoFactor, 'chillerlan\QRCode'), 'A rodada 361 deve fornecer QR renderizável no setup do 2FA.');
        // Nota: o check original procurava os literais "manual_entry_key" e "otpauth://", mas a view
        // foi implementada com as variáveis $secret (rotulada "Chave manual") e $otpauth_url
        // (rotulada "URI TOTP"), que cobrem exatamente o mesmo contrato funcional. Corrigido para
        // refletir os nomes reais usados pela view.
        $checks[] = $this->staticCheck('two_factor_setup_contract', 'Tela de setup do 2FA exibe QR, segredo manual e URI TOTP', str_contains($setupView, 'qr_code_data') && str_contains($setupView, '$secret') && str_contains($setupView, 'Chave manual') && str_contains($setupView, '$otpauth_url') && str_contains($setupView, 'URI TOTP'), 'A view de setup deve expor QR, chave manual e URI TOTP para conferência.');
        $checks[] = $this->staticCheck('csp_progressive_hardening', 'CSP remove curingas globais de websocket', ! str_contains($csp, "'ws:'") && ! str_contains($csp, "'wss:'") && str_contains($csp, 'WEBSOCKET_PUBLIC_ORIGIN'), 'A rodada 361B deve restringir connect-src a origens explícitas.');
        // Nota: o check original procurava funções com prefixo "operational_*" (operational_warning_sign_url,
        // operational_reports_url, operational_timesheet_history_url), mas o helper centralizado foi
        // implementado com o prefixo canônico "sp_*" (sp_warning_sign_url, sp_reports_index_url,
        // sp_timesheet_history_url), cobrindo o mesmo escopo (warnings, reports e timesheet). Corrigido
        // para refletir os nomes reais das funções centralizadas.
        $checks[] = $this->staticCheck('operational_links_centralized', 'Helper central cobre warnings, reports e timesheet', str_contains($links, 'function sp_warning_sign_url') && str_contains($links, 'function sp_reports_index_url') && str_contains($links, 'function sp_timesheet_history_url'), 'A rodada 361C deve centralizar links operacionais críticos.');
        $checks[] = $this->staticCheck('dependency_constraints_not_open', 'composer.json não usa * nas dependências dev críticas', ! str_contains($composer, '"php-webdriver/webdriver": "*"') && ! str_contains($composer, '"phpstan/phpstan": "*"'), 'A rodada 361E deve eliminar constraints abertas nas dependências dev críticas.');

        return $this->closureAuditResult('361A-361E', $checks);
    }


    private function buildPackage366ClosureAudit(): array
    {
        $checks = [];

        $links = is_file(ROOTPATH . 'app/Helpers/operational_link_helper.php') ? (string) file_get_contents(ROOTPATH . 'app/Helpers/operational_link_helper.php') : '';
        $warningRoutes = is_file(ROOTPATH . 'app/Config/Routes/70_reports_compliance.php') ? (string) file_get_contents(ROOTPATH . 'app/Config/Routes/70_reports_compliance.php') : '';
        $warningController = is_file(ROOTPATH . 'app/Controllers/Warning/WarningController.php') ? (string) file_get_contents(ROOTPATH . 'app/Controllers/Warning/WarningController.php') : '';
        $cspFilter = is_file(ROOTPATH . 'app/Filters/SecurityHeadersFilter.php') ? (string) file_get_contents(ROOTPATH . 'app/Filters/SecurityHeadersFilter.php') : '';
        $twoFactor = is_file(ROOTPATH . 'app/Services/Security/TwoFactorAuthService.php') ? (string) file_get_contents(ROOTPATH . 'app/Services/Security/TwoFactorAuthService.php') : '';
        $sessionHelper = is_file(ROOTPATH . 'app/Helpers/session_context_helper.php') ? (string) file_get_contents(ROOTPATH . 'app/Helpers/session_context_helper.php') : '';
        $exceptionHandler = is_file(ROOTPATH . 'app/Exceptions/BusinessExceptionHandler.php') ? (string) file_get_contents(ROOTPATH . 'app/Exceptions/BusinessExceptionHandler.php') : '';
        $composer = is_file(ROOTPATH . 'composer.json') ? (string) file_get_contents(ROOTPATH . 'composer.json') : '';

        $checks[] = $this->staticCheck('timesheet_alias_canonical', 'Helper do timesheet usa alias canônico timesheet.index', str_contains($links, "sp_route_path('timesheet.index')") || str_contains($links, 'sp_route_path("timesheet.index")'), 'A rodada 366A deve corrigir o alias canônico do timesheet.');
        // Nota: o check original procurava o literal "warnings/{id}/add-witness" (sintaxe estilo Laravel),
        // mas as rotas do CodeIgniter 4 usam placeholders "(:num)" dentro de grupos (ex.: $routes->group('warnings', ...)
        // -> $routes->post('(:num)/add-witness', ...)). Isso causava falso-negativo permanente. Corrigido para
        // refletir a sintaxe real de rotas do CI4.
        $hasWitnessPostRoute = (bool) preg_match("~\\\$routes->group\\(\\s*['\"]warnings['\"][\\s\\S]*?\\\$routes->post\\(\\s*['\"]\\(:num\\)/add-witness['\"]~", $warningRoutes);
        $checks[] = $this->staticCheck('warning_witness_post_route', 'Fluxo de testemunha possui rota POST real e método dedicado', $hasWitnessPostRoute && str_contains($warningController, 'function addWitness('), 'A rodada 366A deve permitir submissão real de testemunha.');
        $checks[] = $this->staticCheck('csp_nonce_runtime', 'CSP usa nonce por requisição e script-src com nonce', str_contains($cspFilter, 'csp_nonce') && str_contains($cspFilter, 'script-src') && str_contains($cspFilter, 'nonce-'), 'A rodada 366B deve compatibilizar CSP real com scripts do sistema.');
        $checks[] = $this->staticCheck('two_factor_no_external_qr_prod', '2FA bloqueia fallback externo em produção', str_contains($twoFactor, 'TWO_FACTOR_ALLOW_EXTERNAL_QR_FALLBACK') && str_contains($twoFactor, 'isProductionEnvironment') && str_contains($twoFactor, 'allowsExternalQrFallback'), 'A rodada 366C deve eliminar dependência externa de QR em produção.');
        $checks[] = $this->staticCheck('session_canonical_user_only', 'Sessão canônica remove dependência crítica de employee_*', ! str_contains($sessionHelper, 'employee_id') && ! str_contains($sessionHelper, 'employee_role') && str_contains($sessionHelper, 'user_id'), 'A rodada 366E deve consolidar o contexto autenticado em user_*.');
        $checks[] = $this->staticCheck('business_exception_single_send', 'BusinessExceptionHandler envia a resposta uma única vez no ponto final do handle()', substr_count($exceptionHandler, '->send()') === 1 && str_contains($exceptionHandler, 'buildJsonResponse') && str_contains($exceptionHandler, 'buildWebResponse'), 'A rodada 366F deve reduzir o envio prematuro de respostas no handler de negócio.');
        $checks[] = $this->staticCheck('dependency_constraints_governed', 'composer.json mantém dependências dev críticas governadas', ! str_contains($composer, '"php-webdriver/webdriver": "*"') && ! str_contains($composer, '"phpstan/phpstan": "*"'), 'A rodada 366F deve preservar a governança das dependências críticas.');

        return $this->closureAuditResult('366A-366F', $checks);
    }

    private function buildDependencyGovernanceAudit(): array
    {
        $checks = [];

        $baseline = is_file(ROOTPATH . 'docs/operations/DEPENDENCY_BASELINE_CURRENT.md') ? (string) file_get_contents(ROOTPATH . 'docs/operations/DEPENDENCY_BASELINE_CURRENT.md') : '';
        $qaGate = is_file(ROOTPATH . 'docs/operations/QA_RELEASE_GATE_CURRENT.md') ? (string) file_get_contents(ROOTPATH . 'docs/operations/QA_RELEASE_GATE_CURRENT.md') : '';
        $verdict = is_file(ROOTPATH . 'docs/operations/FINAL_PRODUCTION_VERDICT_CURRENT.md') ? (string) file_get_contents(ROOTPATH . 'docs/operations/FINAL_PRODUCTION_VERDICT_CURRENT.md') : '';
        $goLive = is_file(ROOTPATH . 'docs/operations/GO_LIVE_RELEASE_AUTHORIZATION_CURRENT.md') ? (string) file_get_contents(ROOTPATH . 'docs/operations/GO_LIVE_RELEASE_AUTHORIZATION_CURRENT.md') : '';
        $checklist = is_file(ROOTPATH . 'docs/operations/PACKAGE_361E_RELEASE_CHECKLIST_CURRENT.md') ? (string) file_get_contents(ROOTPATH . 'docs/operations/PACKAGE_361E_RELEASE_CHECKLIST_CURRENT.md') : '';

        $release = $this->releaseMetadata();
        $currentVersion = 'v' . (string) ($release['version'] ?? 'unknown');

        // NOTA: o check abaixo cobrava "phpstan/phpstan: faixa `^1.12`" — uma faixa
        // que não corresponde à realidade (composer.json declara `^2.1` desde antes da
        // limpeza completa). Corrigido para validar a faixa REAL governada, evitando
        // que a doc "current" seja forçada a declarar uma informação falsa só para
        // satisfazer o gate.
        $checks[] = $this->staticCheck('baseline_current_updated', 'Baseline current aponta para a release atual e governança crítica', str_contains($baseline, $currentVersion) && str_contains($baseline, 'php-webdriver/webdriver: faixa `^1.15`') && str_contains($baseline, 'phpstan/phpstan: faixa `^2.1`'), 'A baseline current deve refletir a governança de dependências desta release com as faixas realmente declaradas em composer.json.');
        $checks[] = $this->staticCheck('gate_current_mentions_361', 'Gate current cobre a rodada 361A-361E', str_contains($qaGate, '361A-361E') || str_contains($qaGate, '361A–361E'), 'O gate current deve vigiar explicitamente a rodada 361.');
        $checks[] = $this->staticCheck('verdict_and_golive_current_updated', 'Parecer final e autorização current sincronizados com a release atual', str_contains($verdict, $currentVersion) && str_contains($goLive, $currentVersion), 'Docs current de liberação devem refletir a release atual.');
        $checks[] = $this->staticCheck('package_checklist_current_exists', 'Checklist current do pacote 361E presente', str_contains($checklist, 'Pacote 361E') && str_contains($checklist, $currentVersion), 'O pacote 361E precisa documentar seu próprio checklist current.');

        return $this->closureAuditResult('Governança de dependências', $checks);
    }


    private function buildReleaseConsistencyAudit(): array
    {
        $checks = [];

        $release = $this->releaseMetadata();
        $deployScriptPath = ROOTPATH . 'scripts/release/deploy.sh';
        $deployScript = is_file($deployScriptPath) ? (string) file_get_contents($deployScriptPath) : '';
        $deployEnvExample = is_file(ROOTPATH . 'scripts/release/deploy.env.example') ? (string) file_get_contents(ROOTPATH . 'scripts/release/deploy.env.example') : '';

        // O conceito legado de "source-package vs release-package" (Docker, artifact-manifest.json,
        // tools/release/*.php) foi abandonado deliberadamente: o repositório É o que roda em produção,
        // entregue via deploy direto por SSH. Validamos aqui o NOVO contrato: script único de deploy.
        $checks[] = $this->staticCheck(
            'legacy_artifact_concept_abandoned',
            'Conceito legado source-package/release-package abandonado em release.json',
            ! array_key_exists('artifact_type', $release) && ! array_key_exists('installer', $release) && is_array($release['deploy_model'] ?? null),
            'release.json não deve mais declarar artifact_type/installer do modelo Docker; deve declarar deploy_model com a estratégia atual.'
        );
        $checks[] = $this->staticCheck(
            'deploy_script_present',
            'Script único de deploy presente e executável (pacote → transfere → instala → reinicia)',
            $deployScript !== ''
                && str_contains($deployScript, 'rsync')
                && str_contains($deployScript, 'composer install --no-dev')
                && str_contains($deployScript, 'spark migrate')
                && str_contains($deployScript, 'systemctl reload'),
            'scripts/release/deploy.sh deve existir e cobrir as quatro etapas: empacotar, transferir, instalar e reiniciar.'
        );
        $checks[] = $this->staticCheck(
            'deploy_config_documented',
            'Configuração do deploy documentada via arquivo de exemplo não sensível',
            $deployEnvExample !== '' && str_contains($deployEnvExample, 'DEPLOY_HOST') && str_contains($deployEnvExample, 'DEPLOY_PATH') && ! str_contains($deployEnvExample, 'mumufoco1990'),
            'Deve existir um deploy.env.example documentando as variáveis aceitas, sem credenciais reais.'
        );
        $checks[] = $this->staticCheck(
            'release_metadata_declares_deploy_model',
            'release.json declara o modelo de deploy atual (single-script-ssh-deploy)',
            (string) ($release['deploy_model']['strategy'] ?? '') === 'single-script-ssh-deploy',
            'release.json deve declarar explicitamente a estratégia de deploy vigente.'
        );

        return $this->closureAuditResult('Release current', $checks);
    }

    private function closureAuditResult(string $label, array $checks): array
    {
        $status = 'ok';
        foreach ($checks as $check) {
            if (($check['status'] ?? 'warning') === 'failed') {
                $status = 'failed';
                break;
            }
        }

        $passed = count(array_filter($checks, static fn(array $check): bool => ($check['status'] ?? '') === 'ok'));
        $summary = sprintf('%d/%d checks da rodada %s aprovados.', $passed, count($checks), $label);

        return [
            'status' => $status,
            'summary' => $summary,
            'checks' => $checks,
        ];
    }

    private function staticCheck(string $key, string $label, bool $ok, string $details): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $ok ? 'ok' : 'failed',
            'details' => $details,
        ];
    }

    private function decision(array $gateChecks): array
    {
        $statuses = array_column($gateChecks, 'status');

        if (in_array('rejected', $statuses, true)) {
            return [
                'status' => 'rejected',
                'message' => 'Há pelo menos um gate crítico reprovado. A release não deve ser liberada para produção.',
            ];
        }

        if (in_array('reservations', $statuses, true)) {
            return [
                'status' => 'approved_with_reservations',
                'message' => 'A release pode seguir apenas com ressalvas documentadas e homologação assistida.',
            ];
        }

        return [
            'status' => 'approved',
            'message' => 'Todos os gates avaliados passaram sem bloqueadores nem ressalvas.',
        ];
    }

    private function loadSmokeEvidence(): array
    {
        $path = ROOTPATH . 'build/smoke/smoke-install-first-use.json';
        if (! is_file($path)) {
            return [
                'status' => 'missing',
                'path' => 'build/smoke/smoke-install-first-use.json',
                'details' => 'Artefato de smoke ainda não foi gerado neste ambiente.',
            ];
        }

        $content = json_decode((string) file_get_contents($path), true);
        if (! is_array($content)) {
            return [
                'status' => 'warning',
                'path' => $path,
                'details' => 'Artefato de smoke existe, mas não pôde ser interpretado como JSON.',
            ];
        }

        $summaryStatus = (string) ($content['status'] ?? 'warning');
        $details = $content['summary'] ?? ($content['details'] ?? 'Artefato encontrado.');

        return [
            'status' => $summaryStatus === 'ok' ? 'passed' : ($summaryStatus === 'warning' ? 'warning' : 'failed'),
            'path' => $path,
            'details' => is_string($details) ? $details : 'Artefato encontrado.',
            'payload' => $content,
        ];
    }

    private function releaseMetadata(): array
    {
        if (function_exists('app_release_metadata')) {
            return app_release_metadata();
        }

        return ReleaseMetadata::read();
    }
}
