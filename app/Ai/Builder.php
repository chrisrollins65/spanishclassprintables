<?php

namespace App\Ai;

use App\Models\TeacherGame;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * The packet builder's prompt machinery, reached through node.
 *
 * Every prompt a teacher's game is written to is written in the builder
 * (resources/scripts/builder/ai/prompts.js, copied there by its
 * scripts/sync-site-shared.js), and so is the merge that folds an AI edit back
 * into a game. This class is the only thing here that knows how to call them.
 *
 * Through node rather than ported to PHP for the same reason the bingo cards
 * are: a second implementation of a rule is a rule that drifts. The prompts in
 * particular are product behaviour — change them in the builder, run its sync
 * script, and the site follows.
 */
class Builder
{
    /** A model call is slow; building the text to send it is not. */
    private const TIMEOUT = 30;

    /** The prompt that writes a whole game from nothing but its topic. */
    public function generatePrompt(TeacherGame $game): string
    {
        return $this->run([
            'mode' => 'generate',
            'kind' => (string) $game->kind,
            'topic' => trim((string) $game->theme),
        ])['prompt'];
    }

    /**
     * The prompt for a change to a game that already exists.
     *
     * Carries the game as it stands — the bank numbered, or the board category
     * by category — so the model answers about the words actually on the
     * teacher's screen, hand edits included.
     *
     * @param  list<array{request: string, note: string}>  $history
     */
    public function editPrompt(TeacherGame $game, string $request, array $history = []): string
    {
        return $this->run([
            'mode' => 'edit',
            'kind' => (string) $game->kind,
            'topic' => trim((string) $game->theme),
            'payload' => $game->data(),
            'request' => $request,
            'history' => array_values($history),
        ])['prompt'];
    }

    /**
     * Fold a model's reply into the game.
     *
     * Only what was asked about comes back, so everything else is the
     * teacher's own copy carried over rather than retyped by a model.
     *
     * @param  array<string, mixed>  $reply
     * @return array{payload: array<string, mixed>, changed: array<int, mixed>, note: string, removed?: int, renamed?: array<int, int>, finalChanged?: bool}
     */
    public function merge(TeacherGame $game, array $reply): array
    {
        return $this->run([
            'mode' => 'merge',
            'kind' => (string) $game->kind,
            'payload' => $game->data(),
            'reply' => $reply,
        ]);
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    private function run(array $request): array
    {
        $node = Process::timeout(self::TIMEOUT)
            ->input((string) json_encode($request, JSON_UNESCAPED_UNICODE))
            ->run([config('cards.node', 'node'), resource_path('scripts/game-ai.cjs')]);

        if ($node->failed()) {
            // Ours: a missing sync, a bad kind. Never shown to a teacher.
            throw new RuntimeException('game-ai.cjs failed: '.substr($node->errorOutput(), 0, 300));
        }

        $out = json_decode($node->output(), true);

        if (! is_array($out)) {
            throw new RuntimeException('game-ai.cjs returned nothing usable.');
        }

        return $out;
    }
}
