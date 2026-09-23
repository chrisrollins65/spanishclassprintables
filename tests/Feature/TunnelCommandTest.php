<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The development tunnel's commands (see config/tunnel.php). The tunnel itself
 * needs a server, so these check the things that can be wrong without one.
 */
class TunnelCommandTest extends TestCase
{
    public function test_a_mode_nobody_has_heard_of_is_refused(): void
    {
        $this->artisan('tunnel:mode', ['mode' => 'wide-open'])
            ->expectsOutputToContain('Unknown mode')
            ->assertFailed();
    }

    public function test_neither_command_runs_on_the_live_site(): void
    {
        // The shop has a public address already; a tunnel there would be a
        // hole in it.
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->artisan('tunnel')->assertFailed();
        $this->artisan('tunnel:setup', ['domain' => 'dev.example.com'])->assertFailed();
        $this->artisan('tunnel:mode', ['mode' => 'hooks'])->assertFailed();
    }

    public function test_hooks_mode_lets_through_the_webhook_and_nothing_else(): void
    {
        config([
            'tunnel.config_path' => storage_path('framework/testing/tunnel/config.yml'),
            'tunnel.domain' => 'dev.example.com',
            'tunnel.webhook_paths' => ['/api/paddle/webhook'],
        ]);

        $this->artisan('tunnel:mode', ['mode' => 'hooks'])->assertSuccessful();

        $written = file_get_contents(config('tunnel.config_path'));
        $this->assertStringContainsString('path: ^/api/paddle/webhook$', $written);
        // Everything else is refused by Cloudflare and never reaches here.
        $this->assertStringContainsString('service: http_status:404', $written);
        $this->assertSame(1, substr_count($written, 'hostname: dev.example.com'));
    }

    public function test_site_mode_opens_the_rest_up_and_off_closes_everything(): void
    {
        config([
            'tunnel.config_path' => storage_path('framework/testing/tunnel/config.yml'),
            'tunnel.domain' => 'dev.example.com',
            'tunnel.webhook_paths' => ['/api/paddle/webhook'],
        ]);

        $this->artisan('tunnel:mode', ['mode' => 'site'])->assertSuccessful();
        $written = file_get_contents(config('tunnel.config_path'));
        $this->assertSame(2, substr_count($written, 'hostname: dev.example.com'));

        $this->artisan('tunnel:mode', ['mode' => 'off'])->assertSuccessful();
        $written = file_get_contents(config('tunnel.config_path'));
        $this->assertStringNotContainsString('hostname:', $written);
        $this->assertStringContainsString('service: http_status:404', $written);
    }

    public function test_the_resting_state_is_webhooks_only(): void
    {
        config(['tunnel.config_path' => storage_path('framework/testing/tunnel-fresh/config.yml')]);
        @unlink(storage_path('framework/testing/tunnel-fresh/mode'));

        $this->artisan('tunnel:mode')->expectsOutputToContain('hooks')->assertSuccessful();
    }
}
