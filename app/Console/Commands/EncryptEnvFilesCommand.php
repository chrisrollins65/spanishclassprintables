<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class EncryptEnvFilesCommand extends Command
{
    protected $signature = 'env:encrypt-all {--envs=local,testing,development,production : Comma-separated list of environments to encrypt}';

    protected $description = 'Encrypt all .env files (local, testing, development, production)';

    public function handle(): int
    {
        $key = config('app.env_encryption_key');

        if (! $key) {
            $this->error('ENCRYPTION_KEY is not set in your configuration.');

            return self::FAILURE;
        }

        $envs = array_map('trim', explode(',', $this->option('envs')));
        $failed = false;

        foreach ($envs as $env) {
            $envPath = base_path($env === 'local' ? '.env' : ".env.$env");

            if (! file_exists($envPath)) {
                $this->warn("Skipping {$env}: file not found at {$envPath}");

                continue;
            }

            if ($this->encryptEnvFile($env, $key)) {
                $this->info("Encrypted {$envPath}");
            } else {
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function encryptEnvFile(string $env, string $key): bool
    {
        $args = [
            PHP_BINARY,
            base_path('artisan'),
            'env:encrypt',
            '--force',
            '--key='.$key,
        ];

        if ($env !== 'local') {
            $args[] = "--env=$env";
        }

        $process = new Process($args);
        $process->setTimeout(60);
        $process->setWorkingDirectory(base_path());
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error("Failed to encrypt {$env}: ".$process->getErrorOutput());

            return false;
        }

        return true;
    }
}
