<?php

namespace App\Support;

use App\Models\ContactMessage;
use App\Models\Feedback;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Everything people have sent us through the site, newest first, for the
 * admin's Messages page.
 *
 * The two tables share no columns worth showing together, so they are merged
 * on id and date alone, and the page of rows is then loaded as real models.
 * A rating with nothing else is not a message; Insights counts those.
 */
class Messages
{
    /** The page's views, keyed by the value in the URL. The first is the default. */
    public const FILTERS = [
        'all' => 'All',
        'contact' => 'Contact form',
        'feedback' => 'Game feedback',
        'held' => 'Held as spam',
    ];

    /** @return LengthAwarePaginator<int, ContactMessage|Feedback> */
    public function page(string $filter, int $perPage = 25): LengthAwarePaginator
    {
        $held = $filter === 'held';

        $messages = match ($filter) {
            'contact' => $this->contactMessages($held),
            'feedback' => $this->feedback($held),
            default => $this->contactMessages($held)->unionAll($this->feedback($held)),
        };

        $page = DB::query()->fromSub($messages, 'messages')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $rows = collect($page->items());
        $contactMessages = ContactMessage::findMany($rows->where('kind', 'contact')->pluck('id'))->keyBy('id');
        $feedback = Feedback::with('user')->findMany($rows->where('kind', 'feedback')->pluck('id'))->keyBy('id');

        return $page->through(fn (object $row): ContactMessage|Feedback => $row->kind === 'contact'
            ? $contactMessages[$row->id]
            : $feedback[$row->id]);
    }

    private function contactMessages(bool $held): Builder
    {
        return DB::table('contact_messages')
            ->selectRaw("'contact' as kind, id, created_at")
            ->whereNull('spam_reason', not: $held);
    }

    private function feedback(bool $held): Builder
    {
        return DB::table('feedback')
            ->selectRaw("'feedback' as kind, id, created_at")
            ->whereNull('spam_reason', not: $held)
            ->where(fn (Builder $query) => $query->whereNotNull('comment')
                ->orWhereNotNull('issues')
                ->orWhereNotNull('highlights')
                ->orWhereNotNull('email'));
    }
}
