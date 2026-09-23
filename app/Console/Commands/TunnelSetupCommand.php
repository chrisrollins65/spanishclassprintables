<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Sets this machine up to run the development tunnel: once, per machine.
 *
 * Three steps, all Cloudflare's: authorize (a browser, once), create the
 * tunnel, and point a hostname at it. The credentials land beside the
 * generated config rather than in the repository — they belong to this
 * machine, and a new one runs this again.
 */
class TunnelSetupCommand extends Command
{
    protected $signature = 'tunnel:setup {domain? : The hostname to answer on, e.g. dev.spanishclassprintables.com}';

    protected $description = 'Set this machine up to run the development tunnel';

    public function handle(): int
    {
        if ($this->getLaravel()->isProduction()) {
            $this->error('Run this from a development machine.');

            return self::FAILURE;
        }

        $binary = (string) config('tunnel.binary');
        if (! is_file($binary)) {
            $this->error("cloudflared is not at {$binary}.");
            $this->line('  <fg=gray>winget install --id Cloudflare.cloudflared</>');

            return self::FAILURE;
        }

        $domain = (string) ($this->argument('domain') ?: config('tunnel.domain'));
        if ($domain === '') {
            $this->error('Which hostname should it answer on? e.g. php artisan tunnel:setup dev.spanishclassprintables.com');

            return self::FAILURE;
        }

        $name = (string) config('tunnel.name');
        $directory = TunnelCommand::ensureDirectory();
        $credentials = $directory.DIRECTORY_SEPARATOR.$name.'.json';

        // Authorizing writes a certificate to the user's own cloudflared
        // folder; it is only needed to create tunnels and routes, never to run
        // one, so a machine that has the credentials below needs none of this.
        if (! is_file($this->certificate())) {
            $this->line('  A browser will open. Choose the zone this hostname belongs to.');
            $this->newLine();

            if ($this->cloudflared([$binary, 'tunnel', 'login'], 300) !== self::SUCCESS) {
                $this->error('Authorization did not finish.');

                return self::FAILURE;
            }
        }

        if (! is_file($credentials)) {
            $this->line("  Creating the tunnel <fg=cyan>{$name}</>…");
            if ($this->cloudflared([$binary, 'tunnel', 'create', '--credentials-file', $credentials, $name]) !== self::SUCCESS) {
                $this->error('Could not create the tunnel. If one of that name exists, delete it or change TUNNEL_NAME.');

                return self::FAILURE;
            }
        }

        $this->line("  Pointing <fg=cyan>{$domain}</> at it…");
        if ($this->cloudflared([$binary, 'tunnel', 'route', 'dns', '--overwrite-dns', $name, $domain]) !== self::SUCCESS) {
            $this->error('Could not add the DNS record.');

            return self::FAILURE;
        }

        TunnelModeCommand::writeConfig();

        $this->newLine();
        $this->info('  Ready.');
        $this->line('  <fg=gray>Put this in .env, then run `php artisan tunnel`:</>');
        $this->line("  <fg=cyan>TUNNEL_DOMAIN={$domain}</>");
        $this->newLine();
        $this->line('  <fg=gray>The credentials are at '.$credentials.'.</>');
        $this->line('  <fg=gray>Keep a copy somewhere safe and a new machine can skip straight to `php artisan tunnel`.</>');

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $command
     */
    private function cloudflared(array $command, int $timeout = 60): int
    {
        $process = new Process($command, timeout: $timeout);
        $process->setTty(Process::isTtySupported());
        $process->run(function (string $type, string $output): void {
            $this->output->write($output);
        });

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }

    /** Cloudflare's own certificate, written by `cloudflared tunnel login`. */
    private function certificate(): string
    {
        $home = getenv('USERPROFILE') ?: getenv('HOME') ?: '';

        return $home.DIRECTORY_SEPARATOR.'.cloudflared'.DIRECTORY_SEPARATOR.'cert.pem';
    }
}
