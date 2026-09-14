<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A message sent from the homepage's contact form.
 */
class ContactMessage extends Model
{
    /** The form's topic choices, keyed by the value stored. */
    public const TOPICS = [
        'worksheets' => 'The worksheets',
        'games' => 'The online games',
        'idea' => 'An idea or a topic request',
        'problem' => 'Something isn’t working',
        'other' => 'Something else',
    ];

    protected $fillable = ['name', 'email', 'topic', 'message', 'spam_reason'];

    public function topicLabel(): string
    {
        return self::TOPICS[$this->topic] ?? $this->topic;
    }
}
