<?php

namespace App\Ai;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Asks a model to write a game (docs/teacher-games.md, Phase 2).
 *
 * Which model was measured rather than argued about — see the builder's
 * scripts/model-bakeoff.js, which scores candidates with this product's own
 * validators and audits. Gemini writes; GPT catches it when Google is down.
 *
 * The fallback is for a hard failure only — an error, a timeout, nothing
 * usable back. A pack that arrives but reads poorly is the teacher's to edit;
 * quietly paying a second model to rewrite it would spend their budget on our
 * opinion.
 *
 * Every call reports what it cost, because a credit's AI budget is spent in
 * cents and a budget nobody measures is a wish.
 */
class Writer
{
    /** A model that has not answered in this long has hung. */
    private const TIMEOUT_SECONDS = 180;

    public function write(string $prompt): Written
    {
        $failures = [];

        foreach ([config('ai.primary'), config('ai.fallback')] as $attempt) {
            $key = (string) config('ai.keys.'.$attempt['provider']);

            if ($key === '') {
                $failures[] = $attempt['provider'].': no key configured';

                continue;
            }

            try {
                return $this->ask($attempt, $key, $prompt);
            } catch (Throwable $e) {
                // Worth a log line: the fallback hides an outage from teachers,
                // which also hides it from us.
                Log::warning('AI writer failed over', [
                    'provider' => $attempt['provider'],
                    'model' => $attempt['model'],
                    'error' => substr($e->getMessage(), 0, 200),
                ]);
                $failures[] = $attempt['provider'].': '.substr($e->getMessage(), 0, 120);
            }
        }

        throw new RuntimeException('No model could write this. '.implode(' | ', $failures));
    }

    /**
     * @param  array<string, mixed>  $model
     */
    private function ask(array $model, string $key, string $prompt): Written
    {
        [$text, $inputTokens, $outputTokens] = match ($model['provider']) {
            'gemini' => $this->gemini($key, (string) $model['model'], $prompt),
            'chatgpt' => $this->openAi($key, (string) $model['model'], $prompt),
            default => throw new RuntimeException("No writer for {$model['provider']}."),
        };

        $data = Json::decode($text);

        $cents = (int) ceil(
            ($inputTokens / 1_000_000 * (float) $model['input_per_m']
                + $outputTokens / 1_000_000 * (float) $model['output_per_m']) * 100
        );

        self::recordDailySpend($cents);

        return new Written($data, $model['provider'].':'.$model['model'], $cents);
    }

    /**
     * @return array{0: string, 1: int, 2: int}
     */
    private function gemini(string $key, string $model, string $prompt): array
    {
        $response = $this->http()
            ->withHeaders(['x-goog-api-key' => $key])
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                'contents' => [['parts' => [['text' => $prompt]]]],
                // The whole reply is one JSON object; asking for JSON keeps
                // code fences and apologies out of it.
                'generationConfig' => ['responseMimeType' => 'application/json'],
            ]);

        $this->assertOk($response, 'Gemini');

        $usage = (array) $response->json('usageMetadata', []);

        return [
            (string) $response->json('candidates.0.content.parts.0.text', ''),
            (int) ($usage['promptTokenCount'] ?? 0),
            // Thinking is billed as output and can dwarf the writing, so a
            // budget that ignored it would be wrong by multiples.
            (int) ($usage['candidatesTokenCount'] ?? 0) + (int) ($usage['thoughtsTokenCount'] ?? 0),
        ];
    }

    /**
     * @return array{0: string, 1: int, 2: int}
     */
    private function openAi(string $key, string $model, string $prompt): array
    {
        $response = $this->http()
            ->withToken($key)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => 'You write valid JSON and nothing else.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        $this->assertOk($response, 'OpenAI');

        $usage = (array) $response->json('usage', []);

        return [
            (string) $response->json('choices.0.message.content', ''),
            (int) ($usage['prompt_tokens'] ?? 0),
            (int) ($usage['completion_tokens'] ?? 0),
        ];
    }

    private function http(): PendingRequest
    {
        return Http::timeout(self::TIMEOUT_SECONDS)->acceptJson()->asJson();
    }

    private function assertOk(Response $response, string $who): void
    {
        if ($response->failed()) {
            throw new RuntimeException("{$who} said {$response->status()}: ".substr($response->body(), 0, 200));
        }
    }

    /* ---------- the day's fuse ---------- */

    /** What the whole site has spent on writing today, in cents. */
    public static function spentToday(): int
    {
        return (int) Cache::get(self::dailyKey(), 0);
    }

    /**
     * Whether writing is switched on at all right now.
     *
     * A stop costs a teacher nothing — no credit, no budget, and writing by
     * hand still works — because it is a fuse against our own bugs, not a
     * judgement about them.
     */
    public static function withinDailyLimit(): bool
    {
        return self::spentToday() < (int) config('ai.daily_stop_cents');
    }

    private static function recordDailySpend(int $cents): void
    {
        $key = self::dailyKey();

        if (Cache::get($key) === null) {
            Cache::put($key, 0, now()->endOfDay());
        }

        $total = (int) Cache::increment($key, $cents);
        $warn = (int) config('ai.daily_warn_cents');

        // Once, as it crosses: a surge of real buyers looks like this too.
        if ($total >= $warn && $total - $cents < $warn) {
            Log::warning('AI spend passed the daily warning level', [
                'spent_cents' => $total,
                'stop_cents' => config('ai.daily_stop_cents'),
            ]);
        }
    }

    private static function dailyKey(): string
    {
        return 'ai-spend:'.now()->toDateString();
    }
}
