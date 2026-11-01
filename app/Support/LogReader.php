<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Reads Laravel's log files for the admin's Logs page.
 *
 * Production logs to one file a day (laravel-YYYY-MM-DD.log, kept
 * LOG_DAILY_DAYS); locally it is one laravel.log, which is listed too, undated.
 * Only files in the listing can be read, so a request can never name a path.
 *
 * @phpstan-type LogEntry array{time: string, level: string, summary: string, message: string, details: string, user_id: ?int}
 */
class LogReader
{
    /** The page's time ranges, keyed by days. The first is the default. */
    public const PERIODS = [
        1 => 'Today',
        3 => 'Last 3 days',
        7 => 'Last 7 days',
        14 => 'Last 14 days',
    ];

    /** The page's level filters, keyed by the value in the URL. */
    public const LEVELS = [
        'errors' => 'Errors',
        'warning' => 'Warnings',
        'notice' => 'Notices',
        'info' => 'Info',
        'debug' => 'Debug',
    ];

    /** Levels that count as errors. */
    public const ERROR_LEVELS = ['error', 'critical', 'alert', 'emergency'];

    /** Newest entries kept per request: a runaway loop can log tens of thousands, and the page only needs the latest. */
    public const MAX_ENTRIES = 3000;

    private const ENTRY_START = '/^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2})[^\]]*\] (\w+)\.(\w+): ?(.*)$/';

    /**
     * Newest first.
     *
     * @return list<array{file: string, date: ?string, size: int}>
     */
    public function files(): array
    {
        $files = [];
        foreach (glob($this->path().'/laravel*.log') ?: [] as $path) {
            $name = basename($path);
            preg_match('/(\d{4}-\d{2}-\d{2})\.log$/', $name, $matches);
            $files[] = ['file' => $name, 'date' => $matches[1] ?? null, 'size' => filesize($path) ?: 0];
        }
        usort($files, fn (array $a, array $b): int => ($b['date'] ?? '9999') <=> ($a['date'] ?? '9999'));

        return $files;
    }

    /**
     * Entries of one listed file, newest first.
     *
     * @return array{entries: list<LogEntry>, truncated: bool}
     */
    public function file(string $file): array
    {
        $listed = array_column($this->files(), 'file');

        return in_array($file, $listed, true) ? $this->read([$file]) : ['entries' => [], 'truncated' => false];
    }

    /**
     * Entries of the last $days days (today included), newest first: the daily
     * files of those days, plus what an undated file (laravel.log, locally)
     * logged in them.
     *
     * @return array{entries: list<LogEntry>, truncated: bool}
     */
    public function lastDays(int $days): array
    {
        $from = Carbon::today()->subDays($days - 1)->toDateString();
        $files = array_filter($this->files(), fn (array $file): bool => $file['date'] === null || $file['date'] >= $from);

        return $this->read(array_column($files, 'file'), $from);
    }

    /**
     * The entries at a level from LEVELS (any, if null) whose message or stack
     * trace contains $search.
     *
     * @param  list<LogEntry>  $entries
     * @return list<LogEntry>
     */
    public function matching(array $entries, ?string $level, string $search): array
    {
        return array_values(array_filter($entries, fn (array $entry): bool => ($level === null || $this->filterFor($entry['level']) === $level)
            && ($search === '' || Str::contains($entry['message']."\n".$entry['details'], $search, ignoreCase: true))));
    }

    /**
     * How many entries each level filter would show, leaving out the empty ones.
     *
     * @param  list<LogEntry>  $entries
     * @return array<string, int>
     */
    public function levelCounts(array $entries): array
    {
        $counts = array_count_values(array_map(fn (array $entry): string => $this->filterFor($entry['level']), $entries));

        return array_intersect_key(array_replace(self::LEVELS, $counts), $counts);
    }

    private function filterFor(string $level): string
    {
        return in_array($level, self::ERROR_LEVELS, true) ? 'errors' : $level;
    }

    /**
     * @param  list<string>  $files
     * @param  ?string  $from  only entries logged from this date on
     * @return array{entries: list<LogEntry>, truncated: bool}
     */
    private function read(array $files, ?string $from = null): array
    {
        $entries = [];
        foreach ($files as $file) {
            foreach ($this->parse($this->path().'/'.$file) as $entry) {
                if ($from === null || $entry['time'] >= $from) {
                    $entries[] = $entry;
                }
            }
        }

        // Reversed first because the sort keeps ties in order, and a log's
        // lines within one second are oldest first.
        $entries = array_reverse($entries);
        usort($entries, fn (array $a, array $b): int => $b['time'] <=> $a['time']);

        return ['entries' => array_slice($entries, 0, self::MAX_ENTRIES), 'truncated' => count($entries) > self::MAX_ENTRIES];
    }

    /**
     * One entry per "[date] env.LEVEL: message" line, with the lines after it
     * (stack traces) as details.
     *
     * @return list<LogEntry>
     */
    private function parse(string $path): array
    {
        $entries = [];
        $current = null;
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }
        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");
            if (preg_match(self::ENTRY_START, $line, $matches)) {
                if ($current !== null) {
                    $entries[] = $this->finish($current);
                }
                $current = ['time' => str_replace('T', ' ', $matches[1]), 'level' => strtolower($matches[3]), 'message' => $matches[4], 'details' => []];
            } elseif ($current !== null) {
                $current['details'][] = $line;
            }
        }
        fclose($handle);
        if ($current !== null) {
            $entries[] = $this->finish($current);
        }

        return $entries;
    }

    /**
     * @param  array{time: string, level: string, message: string, details: list<string>}  $entry
     * @return LogEntry
     */
    private function finish(array $entry): array
    {
        $entry['details'] = rtrim(implode("\n", $this->foldVendorFrames($entry['details'])));
        $entry['summary'] = $this->summary($entry['message']);
        $entry['user_id'] = $this->userId($entry['message']);

        return $entry;
    }

    /**
     * Stack trace frames inside vendor/ folded into one line per run: they are
     * most of a trace's size and rarely what matters. Frame #0 is always kept,
     * since it is where the exception was thrown.
     *
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function foldVendorFrames(array $lines): array
    {
        $folded = [];
        $run = 0;
        foreach ($lines as $line) {
            if (preg_match('~^#[1-9]\d* .*[/\\\\]vendor[/\\\\]~', $line)) {
                $run++;

                continue;
            }
            if ($run > 0) {
                $folded[] = "   … $run vendor frame".($run === 1 ? '' : 's');
                $run = 0;
            }
            $folded[] = $line;
        }
        if ($run > 0) {
            $folded[] = "   … $run vendor frame".($run === 1 ? '' : 's');
        }

        return $folded;
    }

    /** The message without the context Laravel appends to it (` {"exception":"[object] ..."}`, ` []`), cut short. */
    private function summary(string $message): string
    {
        $summary = trim(preg_replace('/\s+(\{".*|\[\]|\{\})\s*$/s', '', $message));

        return Str::limit($summary, 300);
    }

    /**
     * Who the entry happened to: the `userId` Laravel adds to exceptions. On the
     * entry's first line only, so an id quoted in a stack trace isn't taken for
     * the user.
     */
    private function userId(string $message): ?int
    {
        return preg_match('/"user_?id":(\d+)/i', $message, $matches) ? (int) $matches[1] : null;
    }

    private function path(): string
    {
        return dirname(config('logging.channels.daily.path'));
    }
}
