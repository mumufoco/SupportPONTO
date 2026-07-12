<?php

namespace App\Services\Monitoring;

use App\Support\SensitiveDataSanitizer;
use CodeIgniter\I18n\Time;

/**
 * Log Monitor Service
 *
 * Monitora logs do sistema e envia alertas para eventos críticos
 *
 * Alertas configurados:
 * - Múltiplas falhas de login (>5 em 5 minutos)
 * - Erros críticos do sistema
 * - Acesso não autorizado
 * - Rate limit atingido (>10 em 1 minuto)
 * - Erros de banco de dados
 */
class LogMonitorService
{
    protected string $logPath;
    protected array $alertEmails;
    protected int $checkInterval = 300; // 5 minutos

    /**
     * Padrões de log para monitorar
     */
    protected array $criticalPatterns = [
        'login_failure' => [
            'pattern' => '/LOGIN_FAILED/',
            'threshold' => 5,
            'timeWindow' => 300, // 5 minutos
            'severity' => 'high',
            'message' => 'Múltiplas tentativas de login falhadas detectadas',
        ],
        'critical_error' => [
            'pattern' => '/CRITICAL/',
            'threshold' => 1,
            'timeWindow' => 60,
            'severity' => 'critical',
            'message' => 'Erro crítico do sistema detectado',
        ],
        'unauthorized_access' => [
            'pattern' => '/UNAUTHORIZED|403|401/',
            'threshold' => 10,
            'timeWindow' => 300,
            'severity' => 'high',
            'message' => 'Múltiplas tentativas de acesso não autorizado',
        ],
        'rate_limit' => [
            'pattern' => '/RATE_LIMIT|429/',
            'threshold' => 10,
            'timeWindow' => 60,
            'severity' => 'medium',
            'message' => 'Rate limit atingido frequentemente',
        ],
        'database_error' => [
            'pattern' => '/Database error|SQL|MySQL|PostgreSQL|pg_/i',
            'threshold' => 3,
            'timeWindow' => 300,
            'severity' => 'high',
            'message' => 'Erros de banco de dados detectados',
        ],
    ];

    public function __construct()
    {
        $this->logPath = WRITEPATH . 'logs/';
        $this->alertEmails = explode(',', env('ALERT_EMAILS', 'admin@supportsondagens.com.br'));
    }

    /**
     * Monitora logs e detecta padrões críticos
     */
    public function monitorLogs(): array
    {
        $alerts = [];
        $today = date('Y-m-d');
        $logFile = $this->logPath . "log-{$today}.log";

        if (!file_exists($logFile)) {
            return $alerts;
        }

        $logContent = file_get_contents($logFile);
        $lines = explode("\n", $logContent);

        // Últimas N linhas para análise
        $recentLines = array_slice($lines, -1000);

        foreach ($this->criticalPatterns as $key => $pattern) {
            $matches = $this->findMatches($recentLines, $pattern);

            if ($matches['count'] >= $pattern['threshold']) {
                $alert = [
                    'type' => $key,
                    'severity' => $pattern['severity'],
                    'message' => $pattern['message'],
                    'count' => $matches['count'],
                    'first_occurrence' => $matches['first_time'],
                    'last_occurrence' => $matches['last_time'],
                    'details' => $matches['samples'],
                ];

                $alerts[] = $alert;

                // Enviar alerta
                $this->sendAlert($alert);
            }
        }

        return $alerts;
    }

    /**
     * Encontra matches de padrão nos logs
     */
    protected function findMatches(array $lines, array $pattern): array
    {
        $matches = [];
        $count = 0;
        $firstTime = null;
        $lastTime = null;

        foreach ($lines as $line) {
            if (preg_match($pattern['pattern'], $line)) {
                $count++;

                // Extrair timestamp
                if (preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $line, $timeMatch)) {
                    $time = $timeMatch[1];
                    if (!$firstTime) $firstTime = $time;
                    $lastTime = $time;
                }

                // Guardar amostra (primeiros 5)
                if (count($matches) < 5) {
                    $matches[] = SensitiveDataSanitizer::sanitizeForLogs(substr($line, 0, 200));
                }
            }
        }

        return [
            'count' => $count,
            'first_time' => $firstTime,
            'last_time' => $lastTime,
            'samples' => $matches,
        ];
    }

    /**
     * Envia alerta por email
     */
    protected function sendAlert(array $alert): void
    {
        $email = \Config\Services::email();

        $subject = "[ALERTA {$alert['severity']}] {$alert['message']}";

        $sanitizedDetails = array_map(static fn ($line) => SensitiveDataSanitizer::sanitizeForLogs((string) $line), $alert['details']);

        $body = "
        <h2>Alerta de Segurança - Sistema de Ponto</h2>
        <p><strong>Tipo:</strong> {$alert['type']}</p>
        <p><strong>Severidade:</strong> " . strtoupper($alert['severity']) . "</p>
        <p><strong>Mensagem:</strong> {$alert['message']}</p>
        <p><strong>Ocorrências:</strong> {$alert['count']}</p>
        <p><strong>Primeira ocorrência:</strong> {$alert['first_occurrence']}</p>
        <p><strong>Última ocorrência:</strong> {$alert['last_occurrence']}</p>

        <h3>Amostras de Log:</h3>
        <pre>" . implode("\n", $sanitizedDetails) . "</pre>

        <hr>
        <p><em>Este é um alerta automático do sistema de monitoramento.</em></p>
        ";

        foreach ($this->alertEmails as $recipient) {
            $email->setTo(trim($recipient));
            $email->setSubject($subject);
            $email->setMessage($body);

            try {
                $email->send();
            } catch (\Exception $e) {
                log_message('error', 'Falha ao enviar alerta: ' . $e->getMessage());
            }
        }
    }

    public function buildOperationalSnapshot(int $days = 7): array
    {
        return [
            'generated_at' => date(DATE_ATOM),
            'report' => $this->generateReport($days),
            'alerts' => $this->monitorLogs(),
            'log_file' => $this->logPath . 'log-' . date('Y-m-d') . '.log',
            '404_log_file' => $this->logPath . '404_requests.log',
        ];
    }

    /**
     * Limpa logs antigos (>30 dias)
     */
    public function cleanOldLogs(int $daysToKeep = 30): int
    {
        $deleted = 0;
        $cutoffDate = Time::now()->subDays($daysToKeep);

        $files = glob($this->logPath . 'log-*.log');

        foreach ($files as $file) {
            $fileDate = filemtime($file);

            if ($fileDate < $cutoffDate->getTimestamp()) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * Gera relatório de logs
     */
    public function generateReport(int $days = 7): array
    {
        $report = [
            'period' => $days . ' dias',
            'total_errors' => 0,
            'critical_errors' => 0,
            'warnings' => 0,
            'login_failures' => 0,
            'unauthorized_attempts' => 0,
            'database_errors' => 0,
        ];

        $startDate = Time::now()->subDays($days);

        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->addDays($i)->format('Y-m-d');
            $logFile = $this->logPath . "log-{$date}.log";

            if (file_exists($logFile)) {
                $content = file_get_contents($logFile);

                $report['total_errors'] += substr_count($content, 'ERROR');
                $report['critical_errors'] += substr_count($content, 'CRITICAL');
                $report['warnings'] += substr_count($content, 'WARNING');
                $report['login_failures'] += substr_count($content, 'LOGIN_FAILED');
                $report['unauthorized_attempts'] += substr_count($content, 'UNAUTHORIZED');
                $report['database_errors'] += substr_count($content, 'Database error');
            }
        }

        return $report;
    }
}
