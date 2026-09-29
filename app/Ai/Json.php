<?php

namespace App\Ai;

use RuntimeException;

/**
 * Reading JSON a model wrote.
 *
 * The same salvage the builder does in src/ai/provider.js (repairJSON), for
 * the same reason: models wrap JSON in code fences and put real newlines
 * inside string values, and a pack thrown away over a stray backtick costs a
 * teacher part of their credit's budget.
 */
class Json
{
    /**
     * @return array<string, mixed>
     */
    public static function decode(string $text): array
    {
        $cleaned = trim($text);

        // A code fence is formatting, not content.
        if (str_starts_with($cleaned, '```')) {
            $cleaned = preg_replace('/^```(?:json)?\s*\n?/', '', $cleaned) ?? $cleaned;
            $cleaned = preg_replace('/\n?```\s*$/', '', $cleaned) ?? $cleaned;
        }

        $data = json_decode($cleaned, true);
        if (is_array($data)) {
            return $data;
        }

        $data = json_decode(self::escapeControlCharactersInStrings($cleaned), true);
        if (is_array($data)) {
            return $data;
        }

        throw new RuntimeException('The model did not return usable JSON: '.json_last_error_msg());
    }

    /**
     * Turn real newlines and tabs inside string values into escapes, walking
     * the text to know which quotes open and close a string.
     */
    private static function escapeControlCharactersInStrings(string $text): string
    {
        $out = '';
        $inString = false;

        for ($i = 0, $length = strlen($text); $i < $length; $i++) {
            $char = $text[$i];

            if ($char === '"' && ($i === 0 || $text[$i - 1] !== '\\')) {
                $inString = ! $inString;
                $out .= $char;
            } elseif ($inString && $char === "\n") {
                $out .= '\\n';
            } elseif ($inString && $char === "\r") {
                continue;
            } elseif ($inString && $char === "\t") {
                $out .= '\\t';
            } else {
                $out .= $char;
            }
        }

        return $out;
    }
}
