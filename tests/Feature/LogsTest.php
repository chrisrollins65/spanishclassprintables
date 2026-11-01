<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The admin's Logs page.
 */
class LogsTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-25 09:00:00');

        $this->directory = sys_get_temp_dir().'/scp-logs-'.uniqid();
        File::ensureDirectoryExists($this->directory);
        config(['logging.channels.daily.path' => $this->directory.'/laravel.log']);

        File::put($this->directory.'/laravel-2026-10-24.log', implode("\n", [
            '[2026-10-24 08:00:00] production.INFO: Paddle synced  ',
            '[2026-10-24 09:15:02] production.ERROR: Gemini timed out {"userId":12,"exception":"[object] (RuntimeException(code: 0): Gemini timed out at /app/Gemini.php:10)',
            '[stacktrace]',
            '#0 /app/Job.php(20): call()',
            '#1 /app/vendor/laravel/framework/Pipeline.php(1): handle()',
            '#2 /app/vendor/laravel/framework/Pipeline.php(2): handle()',
            '"} ',
            '[2026-10-24 11:00:00] production.CRITICAL: Disk full []',
            '[2026-10-24 12:00:00] production.WARNING: Slow request  ',
            '',
        ]));
        File::put($this->directory.'/laravel-2026-10-25.log', "[2026-10-25 07:00:00] production.INFO: Morning\n");
        File::put($this->directory.'/laravel-2026-10-01.log', "[2026-10-01 07:00:00] production.ERROR: Long ago\n");
        File::put($this->directory.'/secret.txt', 'A file that is not a log');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function admin(): User
    {
        config(['site.admins' => ['shop@example.com']]);

        return User::factory()->create(['email' => 'shop@example.com']);
    }

    public function test_only_an_admin_gets_in(): void
    {
        $this->admin();

        $this->get('/admin/logs')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin/logs')->assertForbidden();
    }

    public function test_an_empty_log_still_draws(): void
    {
        File::cleanDirectory($this->directory);

        $this->actingAs($this->admin())->get('/admin/logs')->assertOk()->assertSee('Nothing logged.');
    }

    public function test_it_opens_on_today_and_lists_the_files_newest_first(): void
    {
        $this->actingAs($this->admin())->get('/admin/logs')
            ->assertOk()
            ->assertSee('Morning')
            ->assertDontSee('Disk full')
            ->assertSeeInOrder(['laravel-2026-10-25.log', 'laravel-2026-10-24.log', 'laravel-2026-10-01.log']);
    }

    public function test_a_period_reaches_back_across_the_daily_files(): void
    {
        $this->actingAs($this->admin())->get('/admin/logs?show=3')
            ->assertSeeInOrder(['Morning', 'Slow request', 'Disk full', 'Gemini timed out', 'Paddle synced'])
            ->assertDontSee('Long ago');
    }

    public function test_one_file_can_be_read_with_its_trace_and_its_user(): void
    {
        $this->actingAs($this->admin())->get('/admin/logs?show=laravel-2026-10-24.log')
            ->assertSee('#0 /app/Job.php(20): call()')
            ->assertSee('… 2 vendor frames')
            ->assertDontSee('Pipeline.php')
            ->assertSee(route('admin.teacher', 12), false)
            ->assertDontSee('Morning');
    }

    public function test_the_undated_local_log_counts_towards_a_period(): void
    {
        File::put($this->directory.'/laravel.log', "[2026-10-20 07:00:00] local.INFO: Too old\n[2026-10-25 08:00:00] local.ERROR: Local error\n");

        $this->actingAs($this->admin())->get('/admin/logs')
            ->assertSeeInOrder(['Local error', 'Morning'])
            ->assertDontSee('Too old');
    }

    public function test_entries_within_one_second_stay_newest_first(): void
    {
        File::put($this->directory.'/laravel-2026-10-25.log', "[2026-10-25 07:00:00] production.INFO: First line\n[2026-10-25 07:00:00] production.INFO: Second line\n");

        $this->actingAs($this->admin())->get('/admin/logs')->assertSeeInOrder(['Second line', 'First line']);
    }

    public function test_it_filters_by_level_and_search(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/logs?show=laravel-2026-10-24.log&level=errors')
            ->assertSee('Gemini timed out')
            ->assertSee('Disk full')
            ->assertDontSee('Slow request')
            ->assertDontSee('Paddle synced');

        // The search reaches into the stack trace, whatever its case
        $this->actingAs($admin)->get('/admin/logs?show=laravel-2026-10-24.log&q=JOB.PHP')
            ->assertSee('Gemini timed out')
            ->assertDontSee('Disk full');

        $this->actingAs($admin)->get('/admin/logs?show=laravel-2026-10-24.log&q=nowhere')
            ->assertSee('Nothing matches.');
    }

    public function test_only_listed_log_files_can_be_read(): void
    {
        $admin = $this->admin();

        foreach (['secret.txt', '../.env', '../../.env', '/etc/passwd'] as $file) {
            $this->actingAs($admin)->get('/admin/logs?show='.urlencode($file))
                ->assertOk()
                ->assertSee('Nothing logged.')
                ->assertDontSee('A file that is not a log');
        }
    }

    public function test_what_a_log_line_says_is_escaped(): void
    {
        File::put($this->directory.'/laravel-2026-10-25.log', "[2026-10-25 07:00:00] production.ERROR: <script>alert(1)</script>\n");

        $this->actingAs($this->admin())->get('/admin/logs')->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_long_logs_are_paged(): void
    {
        File::put($this->directory.'/laravel-2026-10-25.log', collect(range(1, 101))
            ->map(fn (int $number): string => sprintf("[2026-10-25 07:%02d:%02d] production.INFO: Entry number %d.\n", intdiv($number, 60), $number % 60, $number))
            ->implode(''));

        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/logs?level=info')
            ->assertSee('Entry number 101.')
            ->assertDontSee('Entry number 1.')
            ->assertSee('level=info&amp;page=2', false);

        $this->actingAs($admin)->get('/admin/logs?level=info&page=2')->assertSee('Entry number 1.');
    }
}
