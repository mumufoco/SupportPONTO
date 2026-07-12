<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Cleanup Expired QR Codes Command
 *
 * Deletes expired QR code files from storage
 * Run via CRON: php spark cleanup:qrcodes
 *
 * Recommended: Daily at 3 AM
 * Cron: 0 3 * * * cd /path/to/project && php spark cleanup:qrcodes
 */
class CleanupQRCodes extends BaseCommand
{
    /**
     * Command group
     *
     * @var string
     */
    protected $group = 'Maintenance';

    /**
     * Command name
     *
     * @var string
     */
    protected $name = 'cleanup:qrcodes';

    /**
     * Command description
     *
     * @var string
     */
    protected $description = 'Remove expired QR code files from storage';

    /**
     * Command usage
     *
     * @var string
     */
    protected $usage = 'cleanup:qrcodes [options]';

    /**
     * Command arguments
     *
     * @var array
     */
    protected $arguments = [];

    /**
     * Command options
     *
     * @var array
     */
    protected $options = [
        '--days'   => 'Delete QR codes older than N days (default: 7)',
        '--dry-run' => 'Show what would be deleted without actually deleting',
    ];

    /**
     * Run cleanup
     *
     * @param array $params
     * @return void
     */
    public function run(array $params)
    {
        $days = $params['days'] ?? CLI::getOption('days') ?? 7;
        $dryRun = CLI::getOption('dry-run') !== null;

        CLI::write('QR Code Cleanup Started', 'green');
        CLI::write('================================================');
        CLI::write("Configuration:");
        CLI::write("  - Delete files older than: {$days} days");
        CLI::write("  - Dry run: " . ($dryRun ? 'Yes (no files will be deleted)' : 'No (files will be deleted)'));
        CLI::newLine();

        // QR code storage directory
        $qrCodeDir = ROOTPATH . 'storage/qrcodes/';

        if (!is_dir($qrCodeDir)) {
            CLI::error("QR code directory does not exist: {$qrCodeDir}");
            return;
        }

        // Calculate cutoff timestamp
        $cutoffTime = time() - ($days * 86400); // 86400 seconds = 1 day

        CLI::write("Scanning directory: {$qrCodeDir}", 'yellow');
        CLI::newLine();

        // Scan directory for QR code files
        $files = glob($qrCodeDir . '*.png');
        $deletedCount = 0;
        $skippedCount = 0;
        $totalSize = 0;

        if (empty($files)) {
            CLI::write('No QR code files found.', 'yellow');
            return;
        }

        CLI::write("Found " . count($files) . " QR code file(s)", 'cyan');
        CLI::newLine();

        // Process each file
        foreach ($files as $file) {
            $filename = basename($file);

            // Skip .gitkeep
            if ($filename === '.gitkeep') {
                continue;
            }

            // Get file modification time
            $fileTime = filemtime($file);
            $fileSize = filesize($file);
            $fileAge = time() - $fileTime;
            $ageDays = floor($fileAge / 86400);

            // Check if file is older than cutoff
            if ($fileTime < $cutoffTime) {
                $totalSize += $fileSize;

                if ($dryRun) {
                    CLI::write("  [DRY RUN] Would delete: {$filename} (age: {$ageDays} days, size: " . $this->formatBytes($fileSize) . ")", 'yellow');
                } else {
                    if (unlink($file)) {
                        CLI::write("  ✓ Deleted: {$filename} (age: {$ageDays} days, size: " . $this->formatBytes($fileSize) . ")", 'green');
                        $deletedCount++;
                    } else {
                        CLI::write("  ✗ Failed to delete: {$filename}", 'red');
                    }
                }
            } else {
                $skippedCount++;
                if (CLI::getOption('verbose')) {
                    CLI::write("  - Kept: {$filename} (age: {$ageDays} days)", 'blue');
                }
            }
        }

        CLI::newLine();
        CLI::write('================================================');
        CLI::write('Cleanup Summary:', 'green');
        CLI::write("  - Files scanned: " . count($files));
        CLI::write("  - Files deleted: {$deletedCount}");
        CLI::write("  - Files kept: {$skippedCount}");
        CLI::write("  - Space freed: " . $this->formatBytes($totalSize));

        if ($dryRun) {
            CLI::newLine();
            CLI::write('DRY RUN MODE - No files were actually deleted.', 'yellow');
            CLI::write('Run without --dry-run to perform actual cleanup.', 'yellow');
        }

        CLI::newLine();
        CLI::write('Cleanup completed!', 'green');
    }

    /**
     * Format bytes to human-readable format
     *
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
