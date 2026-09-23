@extends('account.layout')

@section('title', 'Edit ' . $game->theme)

@push('styles')
  .editor { display: grid; gap: 20px; }
  .bar { position: sticky; top: 0; z-index: 5; display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
         background: rgba(253, 248, 241, .96); border-bottom: 1px solid var(--edge); padding: 12px 0; }
  .bar .theme { flex: 1; min-width: 200px; font: 600 1.25rem 'Fredoka', sans-serif; padding: .5em .7em;
                border: 2px solid var(--edge); border-radius: 12px; background: var(--card); color: var(--ink); }
  .bar .state { font-size: .92rem; color: var(--muted); min-width: 7em; }
  .bar .state.unsaved { color: #8A5A00; font-weight: 700; }
  .bar .state.failed { color: #B3261E; font-weight: 700; }
  .bar .btn { padding: .7em 1.2em; font-size: .98rem; }

  .checks { border-radius: 14px; padding: 14px 18px; font-size: .95rem; }
  .checks.clear { background: #E3F6F4; color: #0B4F4B; }
  .checks.problems { background: var(--gold-soft); color: #5B4200; }
  .checks ul { margin: 8px 0 0; padding-left: 20px; display: grid; gap: 4px; }
  .checks h3 { font-size: 1rem; margin-bottom: 2px; }
  .muted-note { color: var(--muted); font-size: .9rem; margin-top: 6px; }

  /* Fields are small and dense here: this is a working screen, not a form a
     teacher fills in once. */
  .field { min-width: 0; }
  .field label { font-size: .78rem; font-weight: 700; color: var(--muted); margin-bottom: 3px; }
  .field input, .field textarea, .field select {
    width: 100%; font: inherit; font-size: .95rem; color: var(--ink); background: var(--card);
    border: 2px solid var(--edge); border-radius: 10px; padding: .45em .6em;
  }
  .field textarea { resize: vertical; line-height: 1.4; }
  .field input:focus, .field textarea:focus, .field select:focus { border-color: var(--accent); outline: none; }
  .field-pair { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
  @media (max-width: 720px) { .field-pair { grid-template-columns: 1fr; } }

  .list-tools { display: flex; align-items: center; justify-content: space-between; gap: 12px;
                margin: 16px 0 10px; font-size: .9rem; color: var(--muted); flex-wrap: wrap; }
  .list-tools .check { font-size: .9rem; font-weight: 700; }

  /* One line per word, opened one at a time — thirty open cards is several
     screens of scrolling when all a teacher wants is to find one word. */
  .word-list { display: grid; gap: 10px; }
  .word-row { border: 1px solid var(--edge); border-radius: 12px; background: var(--paper); padding: 10px 12px; display: grid; gap: 10px; }
  .word-head { display: flex; align-items: flex-end; gap: 10px; min-width: 0; }
  .word-head .field { min-width: 0; }
  .word-head .n { flex: none; width: 1.8em; text-align: right; color: var(--muted); font-size: .8rem; font-weight: 700; padding-bottom: .55em; }
  /* The one control that says there is more behind this line, so it leads the
     row and is drawn as a button rather than a bare glyph. */
  .word-open { flex: none; width: 28px; height: 28px; border-radius: 50%; border: 1px solid var(--edge);
               background: var(--card); color: var(--accent); padding: 0; cursor: pointer;
               display: grid; place-items: center; margin-bottom: .35em; transition: transform .15s ease; }
  .word-open svg { width: 15px; height: 15px; display: block; }
  .word-open:hover { border-color: var(--accent); }
  .word-row { cursor: pointer; position: relative; }
  .word-row.open { background: var(--card); border-color: var(--accent); }
  .word-row input, .word-row select, .word-row textarea { cursor: auto; }
  .word-head .article { flex: 0 0 4.5rem; }
  .word-head .face, .word-head .en { flex: 1; }
  .word-detail { display: grid; gap: 10px; }
  .drop { flex: none; background: none; border: 0; cursor: pointer; color: var(--muted); padding: 0 .2em .4em; }
  .drop svg { width: 18px; height: 18px; display: block; }
  .drop:hover { color: #B3261E; }

  /* Asked in the row, not in a browser dialog, which on a phone covers the
     word it is asking about. */
  .confirm { display: none; align-items: center; gap: 10px; flex-wrap: wrap;
             background: #FDECEA; color: #8A1F17; border-radius: 10px; padding: 8px 12px; font-weight: 700; font-size: .92rem; }
  .word-row.confirming .confirm { display: flex; }
  .confirm button { border: 0; border-radius: 999px; cursor: pointer; font: 700 .88rem 'Nunito', sans-serif; padding: .45em 1em; }
  .confirm-yes { background: #B3261E; color: #fff; }
  .confirm-no { background: transparent; color: #8A1F17; text-decoration: underline; }
  .word-list.compact .word-row { padding: 6px 12px; }
  .word-list.compact .word-detail { display: none; }
  .word-list.compact .word-row.open .word-detail { display: grid; padding-bottom: 6px; }
  .word-list.compact .word-row.open .word-open { transform: rotate(180deg); }
  .word-list:not(.compact) .word-open { visibility: hidden; }
  .add { margin-top: 14px; }
  @media (max-width: 560px) {
    .word-head { flex-wrap: wrap; padding-right: 26px; }
    .word-head .face, .word-head .en { flex: 1 1 100%; }
    /* Pinned to the corner: in the wrapped layout it otherwise lands in the
       middle of the fields, beside whichever one happens to be last. */
    .drop { position: absolute; top: 8px; right: 8px; padding: 0; }
  }

  /* The board as the class sees it: a column per category, cheapest at the top.
     Wider than a phone, so it scrolls sideways one column at a time. */
  .column-jump { display: none; width: 100%; margin: 16px 0 0; font: inherit; padding: .55em .7em;
                 border: 2px solid var(--edge); border-radius: 12px; background: var(--card); color: var(--ink); }
  /* An empty strip whose scrollbar drives the board's, so a teacher reading the
     $100 row does not have to scroll to the foot of the board to move sideways.
     Hidden unless the board is actually wider than the page. */
  .board-rail { display: none; overflow-x: auto; margin-top: 16px; }
  .board-rail.needed { display: block; }
  .board-rail-inner { height: 1px; }
  @media (max-width: 720px) { .board-rail { display: none !important; } }

  .board-grid { display: flex; gap: 10px; margin-top: 16px; overflow-x: auto; padding-bottom: 8px; scroll-snap-type: x proximity; }
  .board-col { flex: 1 0 0; min-width: 190px; display: flex; flex-direction: column; gap: 8px; scroll-snap-align: start; }
  .board-cat {
    font: 700 .85rem/1.3 'Nunito', sans-serif; text-transform: uppercase; text-align: center;
    padding: 8px 6px; border: 2px solid var(--edge); border-radius: 10px; background: var(--wash);
    color: var(--ink); width: 100%; resize: vertical; overflow-wrap: anywhere;
  }
  /* minmax(0, 1fr), and min-width on the children: a text input refuses to
     shrink below its default twenty characters, so without these every box
     hangs out past the side of its square. */
  .board-cell { border: 1px solid var(--edge); border-radius: 10px; background: var(--paper); padding: 8px;
                display: grid; grid-template-columns: minmax(0, 1fr); gap: 6px; }
  .board-cell > *, .board-cell input, .board-cell textarea, .board-cell select { min-width: 0; max-width: 100%; }
  .board-cell-head { display: flex; align-items: center; justify-content: space-between; gap: 6px; }
  .board-value { font: 700 .9rem 'Fredoka', sans-serif; color: var(--accent); }
  .board-heard { font-size: .62rem; text-transform: uppercase; letter-spacing: .06em; font-weight: 800;
                 background: var(--gold-soft); color: #7A5200; border-radius: 4px; padding: 2px 5px; }
  .board-prompt, .board-answer {
    width: 100%; font: inherit; font-size: .88rem; color: var(--ink); background: var(--card);
    border: 2px solid var(--edge); border-radius: 8px; padding: .4em .5em; resize: vertical;
  }
  /* Muted and italic, like the reveal itself: support text, not a second clue. */
  .board-prompt-en { color: var(--muted); font-style: italic; }
  .board-answer { font-weight: 700; }
  .board-prompt:focus, .board-answer:focus { border-color: var(--accent); outline: none; }
  @media (max-width: 720px) {
    .column-jump { display: block; }
    .board-col { flex: 0 0 auto; min-width: min(300px, 82vw); }
  }

  .final { margin-top: 18px; border: 1px solid var(--edge); border-radius: 12px; background: var(--paper); padding: 14px 16px; }
  .final h3 { font-size: 1.1rem; }
  .final-empty .add { margin-top: 12px; }
  .final .fields { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px; }
  .final .fields .wide { grid-column: 1 / -1; }
  @media (max-width: 720px) { .final .fields { grid-template-columns: 1fr; } }

  /* The article beside the Spanish it belongs to, the English under it. */
  .board-answer-line { display: flex; gap: 6px; align-items: stretch; min-width: 0; }
  .board-answer-line .board-answer { flex: 1; min-width: 0; }
  .board-en { display: flex; gap: 6px; align-items: center; min-width: 0; }
  .board-article { flex: 0 0 3.4rem; font: inherit; font-size: .82rem; color: var(--ink);
                   background: var(--card); border: 2px solid var(--edge); border-radius: 8px; padding: .3em .2em; }
  .board-article:disabled { color: var(--muted); opacity: .6; }
  .board-english { flex: 1; min-width: 0; font: inherit; font-size: .82rem; font-style: italic; color: var(--muted);
                   background: var(--card); border: 2px solid var(--edge); border-radius: 8px; padding: .35em .5em; }
  .board-english:focus, .board-article:focus { border-color: var(--accent); outline: none; color: var(--ink); }
  .board-add { width: 100%; text-align: left; background: var(--gold-soft); color: #7A5200; border: 0; cursor: pointer;
               font: 700 .78rem 'Nunito', sans-serif; border-radius: 8px; padding: .45em .6em; }
  .board-add:hover { background: #FFE9B3; }

  .fold { padding: 0; }
  .fold summary { cursor: pointer; padding: clamp(18px, 3vw, 26px); font: 600 1.2rem 'Fredoka', sans-serif; }
  .fold summary::marker { color: var(--accent); }
  .fold[open] summary { border-bottom: 1px solid var(--edge); }
  .fold .words { padding: 0 clamp(18px, 3vw, 26px) clamp(18px, 3vw, 26px); }

  .print-row { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 16px; }
  .print-row .btn { font-size: .98rem; padding: .7em 1.2em; }
  .print-row .btn:disabled { opacity: .7; cursor: progress; }

  .loading { color: var(--muted); }
@endpush

@section('content')
{{-- Wider than the account pages: five board columns side by side is the point
     of the board view, and 760px fits three. --}}
<div class="wrap">
  {{-- The editor builds itself from the game's own data (public/site/editor.js):
       one payload shape serves bingo and the quiz, and which sections appear
       depends on which games the payload holds. --}}
  <div id="editor" class="editor"
       data-payload-url="{{ route('my-games.payload', $game) }}"
       data-save-url="{{ route('my-games.update', $game) }}"
       data-play-url="{{ route('my-games.play', $game) }}"
       data-games-url="{{ route('my-games') }}"
       data-cards-url="{{ route('my-games.cards', $game) }}"
       data-cards-status-url="{{ route('my-games.cards.status', [$game, 'SIZE']) }}"
       data-game-id="{{ $game->id }}">
    <p class="loading">Loading your game…</p>
  </div>
</div>

{{-- Stamped with each file's modified time, for the same reason the room shell
     stamps its own (RoomController): these are served bare, and a browser will
     happily keep yesterday's copy. --}}
@php use App\Http\Controllers\RoomController; @endphp
<script src="/game/shared/rng.js{{ RoomController::stamp('game/shared/rng.js') }}"></script>
<script src="/game/shared/bingoCards.js{{ RoomController::stamp('game/shared/bingoCards.js') }}"></script>
<script src="/game/shared/jeopardyBoard.js{{ RoomController::stamp('game/shared/jeopardyBoard.js') }}"></script>
<script src="/site/editor.js{{ RoomController::stamp('site/editor.js') }}"></script>
@endsection
