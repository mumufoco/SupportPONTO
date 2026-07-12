<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Report404s Command
 *
 * Aggregates and reports the top missing URIs from 404 request logs.
 * Helps prioritize fixes for frequently accessed missing pages.
 *
 * Usage:
 *   php spark report:404                      - Show top 50 404s
 *   php spark report:404 --top=100            - Show top 100 404s
 *   php spark report:404 --since="2024-01-01" - Show 404s since date
 *
 * @package App\Commands
 */
class Report404s extends BaseCommand
{
    /**
     * The Command's Group
     *
     * @var string
     */
    protected $group = 'Reporting';

    /**
     * The Command's Name
     *
     * @var string
     */
    protected $name = 'report:404';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Aggregates and reports top missing URIs from 404 logs';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'report:404 [options]';

    /**
     * The Command's Arguments
     *
     * @var array
     */
    protected $arguments = [];

    /**
     * The Command's Options
     *
     * @var array
     */
    protected $options = [
        '--top'   => 'Number of top results to show (default: 50)',
        '--since' => 'Filter 404s since date (format: YYYY-MM-DD)',
    ];

    /**
     * Run the command
     *
     * @param array $params
     * @return void
     */
    public function run(array $params)
    {
        CLI::write('===========================================', 'blue');
        CLI::write('  404 Not Found - Analysis Report', 'blue');
        CLI::write('===========================================', 'blue');
        CLI::newLine();

        $logFile = WRITEPATH . 'logs/404_requests.log';

        // Check if log file exists
        if (!file_exists($logFile)) {
            CLI::write('No 404 log file found at: ' . $logFile, 'yellow');
            CLI::write('The log will be created when the first 404 occurs.', 'yellow');
            return;
        }

        // Get options
        $topCount = (int) ($params['top'] ?? CLI::getOption('top') ?? 50);
        $sinceDate = $params['since'] ?? CLI::getOption('since') ?? null;

        // Validate since date if provided
        if ($sinceDate && !$this->isValidDate($sinceDate)) {
            CLI::write('Invalid date format. Please use YYYY-MM-DD', 'red');
            return;
        }

        CLI::write('Reading log file: ' . $logFile, 'cyan');
        CLI::write('Filter: ' . ($sinceDate ? "Since {$sinceDate}" : 'All time'), 'cyan');
        CLI::write('Limit: Top ' . $topCount . ' results', 'cyan');
        CLI::newLine();

        // Process log file
        $stats = $this->processLogFile($logFile, $sinceDate);

        if (empty($stats)) {
            CLI::write('No 404 requests found.', 'yellow');
            return;
        }

        // Sort by count descending
        uasort($stats, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        // Limit results
        $stats = array_slice($stats, 0, $topCount, true);

        // Display results
        $this->displayResults($stats, $topCount);
    }

    /**
     * Process log file and aggregate statistics
     *
     * @param string $logFile
     * @param string|null $sinceDate
     * @return array
     */
    protected function processLogFile(string $logFile, ?string $sinceDate): array
    {
        $stats = [];
        $totalLines = 0;
        $parsedLines = 0;
        $filteredLines = 0;

        // Open file for reading
        $handle = @fopen($logFile, 'r');
        if (!$handle) {
            $error = error_get_last();
            CLI::write('Error opening log file: ' . $logFile, 'red');
            if ($error) {
                CLI::write('Reason: ' . $error['message'], 'red');
            }
            return [];
        }

        // Process line by line for memory efficiency
        while (($line = fgets($handle)) !== false) {
            $totalLines++;

            // Parse JSON line
            $entry = json_decode(trim($line), true);
            if (!$entry) {
                // Log invalid JSON for debugging
                if (json_last_error() !== JSON_ERROR_NONE) {
                    log_message('debug', "Invalid JSON at line {$totalLines} in 404_requests.log: " . json_last_error_msg());
                }
                continue; // Skip invalid JSON
            }

            $parsedLines++;

            // Filter by date if requested
            if ($sinceDate) {
                $entryDate = substr($entry['timestamp'] ?? '', 0, 10);
                if ($entryDate < $sinceDate) {
                    $filteredLines++;
                    continue;
                }
            }

            // Extract path (without query string for better aggregation)
            $path = $entry['path'] ?? $entry['uri'] ?? 'Unknown';

            // Initialize or increment stats
            if (!isset($stats[$path])) {
                $stats[$path] = [
                    'count' => 0,
                    'last_seen' => null,
                    'methods' => [],
                    'sample_referer' => null,
                ];
            }

            $stats[$path]['count']++;
            $stats[$path]['last_seen'] = $entry['timestamp'] ?? null;
            
            // Track HTTP methods
            $method = $entry['method'] ?? 'Unknown';
            if (!isset($stats[$path]['methods'][$method])) {
                $stats[$path]['methods'][$method] = 0;
            }
            $stats[$path]['methods'][$method]++;

            // Save sample referer
            if (!$stats[$path]['sample_referer'] && !empty($entry['referer'])) {
                $stats[$path]['sample_referer'] = $entry['referer'];
            }
        }

        fclose($handle);

        // Show processing stats
        CLI::write("Total lines: {$totalLines}", 'white');
        CLI::write("Valid JSON entries: {$parsedLines}", 'white');
        if ($sinceDate) {
            CLI::write("Filtered out (before {$sinceDate}): {$filteredLines}", 'white');
        }
        CLI::write("Unique paths: " . count($stats), 'white');
        CLI::newLine();

        return $stats;
    }

    /**
     * Display aggregated results
     *
     * @param array $stats
     * @param int $topCount
     * @return void
     */
    protected function displayResults(array $stats, int $topCount): void
    {
        $actualCount = count($stats);
        $displayCount = min($actualCount, $topCount);
        
        CLI::write("Top {$displayCount} Missing URIs:", 'green');
        CLI::write('─────────────────────────────────────────────────────────────────────', 'cyan');
        CLI::newLine();

        $rank = 1;
        foreach ($stats as $path => $data) {
            // Display rank and count
            CLI::write("#{$rank} - {$data['count']} requests", 'yellow');
            
            // Display path
            CLI::write("  Path: {$path}", 'white');
            
            // Display methods
            $methods = [];
            foreach ($data['methods'] as $method => $count) {
                $methods[] = "{$method} ({$count})";
            }
            CLI::write("  Methods: " . implode(', ', $methods), 'white');
            
            // Display last seen
            if ($data['last_seen']) {
                CLI::write("  Last seen: {$data['last_seen']}", 'white');
            }
            
            // Display sample referer if available
            if ($data['sample_referer']) {
                CLI::write("  Sample referer: {$data['sample_referer']}", 'white');
            }
            
            CLI::newLine();
            $rank++;
        }

        CLI::write('─────────────────────────────────────────────────────────────────────', 'cyan');
        CLI::write('Use this data to prioritize fixing the most accessed missing pages.', 'green');
        CLI::newLine();
    }

    /**
     * Validate date format
     *
     * @param string $date
     * @return bool
     */
    protected function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
