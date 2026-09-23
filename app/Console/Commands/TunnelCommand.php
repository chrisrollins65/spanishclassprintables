<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Opens the development tunnel, so webhooks can reach this machine.
 *
 * Paddle needs a public https address and a laptop behind a router has none.
 * Cloudflare provides one; this writes the ingress rules for it and runs it.
 * What those rules allow is `php artisan tunnel:mode`, and it is webhooks-only
 * until somebody says otherwise.
 */
class TunnelCommand extends Command
{
    protected $signature = 'tunnel {--quick : A throwaway address, with no Cloudflare account and no setup}';

    protected $description = 'Open the development tunnel so webhooks can reach this machine';

    public function handle(): int
    {
        // The live site has a public address of its own; a tunnel there would
        // be a hole in it.
        if ($this->getLaravel()->isProduction()) {
            $this->error('The tunnel is for development machines.');

            return self::FAILURE;
        }

        $binary = (string) config('tunnel.binary');
        if (! is_file($binary)) {
            $this->error("cloudflared is not at {$binary}.");
            $this->line('  <fg=gray>winget install --id Cloudflare.cloudflared, or set TUNNEL_CLOUDFLARED.</>');

            return self::FAILURE;
        }

        return $this->option('quick') ? $this->quick($binary) : $this->named($binary);
    }

    /**
     * The tunnel proper: a name, a hostname, and rules about what it answers.
     */
    private function named(string $binary): int
    {
        $domain = (string) config('tunnel.domain');
        if ($domain === '') {
            $this->error('No tunnel is set up yet.');
            $this->line('  <fg=gray>Run `php artisan tunnel:setup` first, or `php artisan tunnel --quick` for a throwaway address.</>');

            return self::FAILURE;
        }

        // Rewritten every run from config/tunnel.php and the saved mode, so
        // the file on disk can never quietly disagree with the settings.
        TunnelModeCommand::writeConfig();

        $this->banner("https://{$domain}");

        return $this->runCloudflared([$binary, '--config', (string) config('tunnel.config_path'), 'tunnel', 'run', (string) config('tunnel.name')]);
    }

    /**
     * A throwaway tunnel, for a machine that has never been set up — or when
     * Cloudflare's account side is not worth it for one afternoon.
     *
     * Its address changes every run, so whatever is calling back has to be
     * told the new one each time. It also has no ingress rules of its own,
     * which is why it is not the usual way in: the whole site is exposed.
     */
    private function quick(string $binary): int
    {
        $this->warn('  A throwaway address, and the WHOLE site answers on it.');
        $this->line('  <fg=gray>Watch for the trycloudflare.com address below, and point the webhook at it.</>');
        $this->newLine();

        return $this->runCloudflared([
            $binary, 'tunnel',
            '--url', (string) config('tunnel.local_url'),
            '--http-host-header', (string) config('tunnel.local_host'),
        ]);
    }

    private function banner(string $address): void
    {
        $mode = TunnelModeCommand::currentMode();

        $this->newLine();
        $this->line("  <fg=cyan>{$address}</> → ".config('tunnel.local_url'));
        $this->line("  <fg=gray>mode: {$mode}".($mode === 'hooks' ? ' (webhooks only — php artisan tunnel:mode site opens it up)' : '').'</>');
        foreach ((array) config('tunnel.webhook_paths') as $path) {
            $this->line("  <fg=gray>{$address}{$path}</>");
        }
        $this->line('  <fg=gray>Ctrl-C to close it.</>');
        $this->newLine();
    }

    /**
     * @param  list<string>  $command
     */
    private function runCloudflared(array $command): int
    {
        // cloudflared reconnects on its own, so there is no loop here to get
        // in its way; it exits when the person running it says so.
        $process = new Process($command, timeout: null);
        $process->run(function (string $type, string $output): void {
            foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
                if ($line === '') {
                    continue;
                }
                // Its own logs are verbose and mostly about itself; the
                // address and anything that went wrong are what matter here.
                if (str_contains($line, 'trycloudflare.com') || str_contains(strtolower($line), 'err')) {
                    $this->line('  '.trim($line, "| \t"));
                }
            }
        });

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }

    /** Somewhere for the generated config and its credentials to live. */
    public static function ensureDirectory(): string
    {
        $directory = dirname((string) config('tunnel.config_path'));
        File::ensureDirectoryExists($directory);

        return $directory;
    }
}
