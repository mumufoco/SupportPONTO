<?php

namespace App\Commands;

use App\Services\Security\EncryptionService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class EncryptionGenerateKey extends BaseCommand
{
    protected $group = 'Security';
    protected $name = 'encryption:generate-key';
    protected $description = 'Generate a secure encryption key for settings encryption';
    protected $usage = 'encryption:generate-key [options]';
    protected $options = [
        '--show'  => 'Display the key instead of modifying files',
        '--force' => 'Overwrite existing key in .env file',
    ];

    public function run(array $params)
    {
        CLI::newLine();
        CLI::write('Generating encryption key...', 'yellow');
        CLI::newLine();

        try {
            $key = 'base64:' . EncryptionService::generateKey();

            CLI::write('✓ Key generated successfully!', 'green');
            CLI::newLine();

            if (CLI::getOption('show')) {
                CLI::write('Encryption Key:', 'cyan');
                CLI::write($key, 'yellow');
                CLI::newLine();
                CLI::write('Add this to your .env file:', 'cyan');
                CLI::write('encryption.key = ' . $key, 'white');
                CLI::newLine();
                return;
            }

            $envPath = ROOTPATH . '.env';
            if (! file_exists($envPath)) {
                CLI::error('❌ .env file not found at: ' . $envPath);
                CLI::write('Please create one from .env.example', 'yellow');
                CLI::newLine();
                CLI::write('Then add this line:', 'cyan');
                CLI::write('encryption.key = ' . $key, 'white');
                CLI::newLine();
                return;
            }

            $envContent = (string) file_get_contents($envPath);
            $hasLowercase = preg_match('/^encryption\.key\s*=.*$/mi', $envContent) === 1;
            $hasUppercase = preg_match('/^ENCRYPTION_KEY\s*=.*$/m', $envContent) === 1;

            if (($hasLowercase || $hasUppercase) && ! CLI::getOption('force')) {
                CLI::error('❌ encryption.key / ENCRYPTION_KEY already exists in .env');
                CLI::write('Use --force to overwrite, or --show to display the new key', 'yellow');
                CLI::newLine();
                CLI::write('⚠️  WARNING: Changing the encryption key will make existing encrypted data unreadable!', 'red');
                CLI::newLine();
                return;
            }

            if (CLI::getOption('force')) {
                CLI::write('⚠️  Overwriting existing encryption key (--force used)', 'yellow');
                $envContent = (string) preg_replace('/^encryption\.key\s*=.*$/mi', 'encryption.key = ' . $key, $envContent);
                $envContent = (string) preg_replace('/^ENCRYPTION_KEY\s*=.*$/m', 'ENCRYPTION_KEY = ' . EncryptionService::generateKey(), $envContent);
            }

            if (! $hasLowercase) {
                $envContent .= "\n#--------------------------------------------------------------------\n";
                $envContent .= "# ENCRYPTION\n";
                $envContent .= "#--------------------------------------------------------------------\n";
                $envContent .= 'encryption.key = ' . $key . "\n";
            }

            if (! $hasUppercase) {
                $envContent .= 'ENCRYPTION_KEY = ' . EncryptionService::generateKey() . "\n";
                $envContent .= "ENCRYPTION_KEY_VERSION = 1\n";
            }

            if (file_put_contents($envPath, $envContent) === false) {
                CLI::error('❌ Failed to write to .env file');
                CLI::newLine();
                CLI::write('Manually add this line to your .env:', 'cyan');
                CLI::write('encryption.key = ' . $key, 'white');
                CLI::newLine();
                return;
            }

            CLI::write('✓ Encryption key added to .env file!', 'green');
            CLI::newLine();
            CLI::write('Key: ' . $key, 'white');
            CLI::newLine();
            CLI::write('⚠️  Keep this key secure! Loss of this key means loss of encrypted data.', 'yellow');
            CLI::write('⚠️  Add .env to .gitignore to prevent committing the key.', 'yellow');
            CLI::newLine();
        } catch (\Exception $e) {
            CLI::error('❌ Error: ' . $e->getMessage());
            CLI::newLine();
        }
    }
}
