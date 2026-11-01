{{-- Plain text, so nothing is escaped: HTML entities would show up literally. --}}
Game feedback from the Spanish Class Printables website.

Rating: {!! $feedback->ratingLabel() !!}
Game:   {!! $feedback->game ?: '(unknown)' !!} · {!! $feedback->room_code ?: $feedback->teacher_game_id ?: '(unknown)' !!}
From:   {!! $feedback->user?->email ?: $feedback->email ?: '(anonymous — no way to reply)' !!}
@if ($feedback->issueLabels() !== [])
Issues: {!! implode(', ', $feedback->issueLabels()) !!}
@endif
@if ($feedback->highlightLabels() !== [])
Liked:  {!! implode(', ', $feedback->highlightLabels()) !!}
@endif

{!! $feedback->comment !!}
