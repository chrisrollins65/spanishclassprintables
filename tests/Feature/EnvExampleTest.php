<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * That .env.example documents every setting this app invented.
 *
 * A production .env is built from this file. A key that lives only in a
 * config's `env()` default is a key nobody sets on the server — and the
 * failure is silent, because the default is usually the development one. That
 * is how the whole AI section came to be missing from here: GEMINI_API_KEY,
 * the per-credit budget and the daily fuse were all read by config/ai.php and
 * mentioned nowhere a deploy would look.
 *
 * Only this app's own config files. Laravel's stock ones reach for dozens of
 * keys for services we do not use, and listing those would be noise that
 * teaches the next person to ignore this test.
 */
class EnvExampleTest extends TestCase
{
    /** The configs this project wrote, as opposed to the framework's. */
    private const OURS = ['ai', 'browsershot', 'cards', 'paddle', 'rooms', 'site', 'tunnel'];

    /**
     * Keys deliberately left out, with the reason.
     *
     * @var array<string, string>
     */
    private const UNLISTED = [
        // Set by `php artisan tunnel:setup`, which writes it per machine —
        // having it here invites someone to paste another machine's name in.
        'TUNNEL_NAME' => 'written per machine by tunnel:setup',
        'TUNNEL_CONFIG' => 'a local path tunnel:setup writes; never set on the server',
    ];

    public function test_every_setting_we_invented_is_in_env_example(): void
    {
        $example = file_get_contents(base_path('.env.example'));
        $missing = [];

        foreach (self::OURS as $config) {
            $path = config_path($config.'.php');
            if (! is_file($path)) {
                continue;
            }

            preg_match_all("/env\(\s*'([A-Z0-9_]+)'/", file_get_contents($path), $found);

            foreach (array_unique($found[1]) as $key) {
                if (isset(self::UNLISTED[$key])) {
                    continue;
                }

                if (! preg_match('/^'.preg_quote($key, '/').'=/m', $example)) {
                    $missing[] = $key.' (config/'.$config.'.php)';
                }
            }
        }

        $this->assertSame([], $missing, "Not in .env.example, so nobody will set it on the server:\n".implode("\n", $missing));
    }
}
