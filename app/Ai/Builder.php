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

    /**
     * The model calls this game needs, in order.
     *
     * Asked rather than worked out here, because which deck types can write a
     * quiz in one reply is a fact about the prompts, and the prompts live in the
     * builder. A vocabulary quiz comes back as ['all']; a grammar one as
     * ['bank', 'board'], since its words carry a formula that the one-reply
     * prompt has no room for. Nothing in PHP has to learn what a deck type is.
     *
     * @return list<string>
     */
    public function plan(TeacherGame $game): array
    {
        $steps = $this->run([
            'mode' => 'plan',
            'kind' => (string) $game->kind,
            'payload' => $game->data() ?: [],
        ])['steps'] ?? [];

        return is_array($steps) && $steps !== [] ? array_values($steps) : ['all'];
    }

    /**
     * The prompt for one step of writing a game from nothing but its topic.
     *
     * `$sofar` is what the earlier steps wrote — the bank, when the board is
     * being written over it. It goes in on the payload, which is where the
     * bridge already looks for a game's words.
     *
     * @param  array<string, mixed>  $sofar
     */
    public function generatePrompt(TeacherGame $game, string $step = 'all', array $sofar = []): string
    {
        $payload = $game->data() ?: [];
        if (isset($sofar['items'])) {
            $payload['items'] = $sofar['items'];
        }

        return $this->run([
            'mode' => 'generate',
            'kind' => (string) $game->kind,
            'topic' => trim((string) $game->theme),
            'step' => $step,
            'payload' => $payload,
        ])['prompt'];
    }

    /**
     * Which kind of bank this game needs, from what the teacher described.
     *
     * The teacher is not asked. "Verb and word forms" against "little words" is a
     * distinction about how a pack is built, and someone making a bingo game for
     * Tuesday has no reason to know it — so they say what the lesson is for and
     * this reads it. One small call, made for a game the teacher is writing
     * themselves as well as one the AI writes: the answer decides which fields a
     * word has in the editor, not just how it is generated.
     *
     * Never fatal. An unknown answer, an unparseable one, or a model that is
     * having a bad day all land on vocabulary — the most general shape and what
     * most games are — and the teacher is shown what was chosen and can change
     * it. A game is not worth losing over this.
     *
     * @return array{type: string, why: string}
     */
    public function classify(string $topic, string $describe, Writer $writer, string $kind = ''): array
    {
        $fallback = ['type' => 'vocabulario', 'why' => ''];

        try {
            $prompt = $this->run([
                'mode' => 'classify',
                'topic' => $topic,
                'describe' => $describe,
                // So a bingo game is never handed a quiz-only deck.
                'kind' => $kind,
            ])['prompt'];

            $written = $writer->write($prompt);
            $chosen = (string) ($written->data['type'] ?? '');
            $known = array_column($this->types(), 'id');

            if (! in_array($chosen, $known, true)) {
                return $fallback;
            }

            return ['type' => $chosen, 'why' => (string) ($written->data['why'] ?? '')];
        } catch (\Throwable) {
            return $fallback;
        }
    }

    /**
     * The deck types a teacher may pick from, newest understanding first.
     *
     * Read from the builder's registry so a type added there appears on the
     * create form without a change here or in the template.
     *
     * @return list<array{id: string, label: string, blurb: string}>
     */
    public function types(): array
    {
        return $this->run(['mode' => 'types'])['types'] ?? [];
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
