<?php

namespace App\Ai;

/**
 * What a model wrote, which model wrote it, and what it cost us in cents.
 *
 * The cost travels with the writing because it is charged to the credit that
 * paid for it, and the model travels with it because "which model wrote this?"
 * is the first question when a teacher says a pack reads oddly.
 */
class Written
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly array $data,
        public readonly string $writtenBy,
        public readonly int $cents,
    ) {}
}
