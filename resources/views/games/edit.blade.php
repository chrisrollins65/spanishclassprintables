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
  /* One switch per ROW, above the board it governs. See `heardStrip`: a row is
     heard in every category or in none, so there is nothing to put on a cell.
     Folded shut, because most teachers come to fix a clue and never open it —
     so the summary states the setting rather than naming the control, and the
     common case of wanting to KNOW costs no tap. Its own summary rules, not
     .fold's: that one dresses a whole card, this sits inside one. */
  .heard-fold { margin: 12px 0; }
  .heard-fold > summary { cursor: pointer; padding: 6px 0; font-size: .88rem;
    font-weight: 700; color: var(--muted); list-style-position: outside; }
  .heard-fold > summary::marker { color: var(--accent); }
  .heard-fold > summary:hover { color: var(--ink); }
  .heard-block { margin: 2px 0 4px; }
  .heard-rows { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
  .heard-row { display: inline-flex; align-items: center; gap: 5px; cursor: pointer;
    padding: 3px 9px 3px 6px; border: 1px solid var(--edge); border-radius: 999px;
    background: var(--paper); font-size: .85rem; }
  .heard-row.on { border-color: var(--accent); background: var(--wash); }
  .heard-row input { margin: 0; cursor: pointer; }
  /* The row a switch governs, while the pointer or the keyboard is on it. */
  .board-cell.row-lit { border-color: var(--accent); box-shadow: 0 0 0 2px var(--wash); }
  .heard-note { margin: 6px 0 0; font-size: .85rem; color: var(--muted); }
  .heard-note:empty { display: none; }
  .heard-note.warn { color: #9A3412; }
  /* Narrow: a grid rather than a wrapped flex line.
     Measured, not guessed — the five switches run out of room at 660px, which
     is why this is its own breakpoint and not the board's 720. (It was 780
     while the strip carried an inline "Read aloud, not shown:" label; that text
     is the fold's summary now, so re-measure this if the switches change.)
     Wrapped, flex leaves them at ragged x positions — three, then a lone $500;
     a grid keeps them in columns, so a short last line reads as deliberate. The
     whole pill is the <label>, so it is the tap target: padded to a finger
     here, where the 13px checkbox of the desktop layout is not something
     anyone can hit. */
  @media (max-width: 660px) {
    .heard-rows { display: grid; grid-template-columns: repeat(auto-fit, minmax(104px, 1fr)); gap: 8px; }
    /* 104px is the measured floor: "Row $500" with this padding needs 102, and
       a track narrower than its text is where a pill clips or wraps. Allowing
       for the real gutters (.wrap 20px a side, .card clamp(22px,4vw,34px)) that
       gives five across above 700px, three at 500, two on every ordinary phone
       and a plain stack below about 340. Two is not a compromise on a phone, it
       is forced: 390px leaves 280px of card, and three columns of that are 88px
       against text that needs 90. Shrinking the type to fit a third column was
       the alternative and it is the worse one — this control is read at arm's
       length over a classroom desk. */
    .heard-row { justify-content: center; padding: 12px 8px; border-radius: 12px; white-space: nowrap; }
    .heard-row input { width: 18px; height: 18px; }
  }
  /* What kind of bank this game uses, above the words it governs. Quiet by
     default — it is a statement, not a control — and the picker only opens if a
     teacher says the guess was wrong. */
  .deck-type { margin: 10px 0 4px; }
  .deck-type-now { font-size: .92rem; color: var(--muted); margin: 0; }
  .deck-type-now strong { color: var(--ink); }
  .deck-type-change { background: none; border: 0; padding: 0; margin-top: 4px;
    font: inherit; font-size: .85rem; color: var(--accent); text-decoration: underline;
    cursor: pointer; }
  .deck-type-picker { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 10px; margin-top: 10px; }
  .deck-type-option { text-align: left; padding: 12px 14px; border: 2px solid var(--edge);
    border-radius: 12px; background: var(--paper); cursor: pointer; font: inherit; }
  .deck-type-option.chosen { border-color: var(--accent); background: var(--card); }
  .deck-type-option strong { display: block; font-size: .95rem; margin-bottom: 2px; }
  .deck-type-option em { font-style: normal; font-size: .82rem; color: var(--muted); }
  /* Shown only after switching the deck of a game that is already written —
     see `switchedNote`. Gold rather than red: nothing is broken, but the
     clues now say one thing while the deck asks for another. */
  .deck-type-switched { margin-top: 12px; border: 2px solid var(--gold);
    border-radius: 14px; background: var(--gold-soft); padding: 14px 16px; }
  .deck-type-switched p { margin: 0 0 10px; font-size: .92rem; color: #5B4200; }
  .deck-type-switched p:last-child { margin: 10px 0 0; }
  .deck-type-switched .muted-note { font-size: .85rem; }

  .board-prompt, .board-answer {
    width: 100%; font: inherit; font-size: .88rem; color: var(--ink); background: var(--card);
    border: 2px solid var(--edge); border-radius: 8px; padding: .4em .5em; resize: vertical;
  }
  /* Muted and italic, like the reveal itself: support text, not a second clue. */
  .board-prompt-en { color: var(--muted); font-style: italic; }
  /* A sentence answer wraps instead of scrolling sideways; autosize() in
     editor.js grows it to its content so nothing is read two words at a time. */
  .board-answer-long { resize: vertical; line-height: 1.35; overflow: hidden; }
  .board-prompt, .board-prompt-en { overflow: hidden; }
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

  /* A draft: not playable, not printable, and one button away from being
     both. */
  .draft-bar { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap;
               background: var(--gold-soft); color: #5B4200; border-radius: 14px; padding: 14px 18px; }
  .draft-bar strong { font: 600 1.05rem 'Fredoka', sans-serif; }
  .draft-bar p { font-size: .92rem; margin-top: 2px; }
  .draft-bar form { margin: 0; }
  .writing { display: flex; align-items: center; gap: 12px; background: var(--wash); border-radius: 14px; padding: 16px 18px; }
  .writing .spinner { width: 18px; height: 18px; border-radius: 50%; flex: none;
                      border: 3px solid rgba(190, 0, 135, .25); border-top-color: var(--accent); animation: spin .8s linear infinite; }
  @keyframes spin { to { transform: rotate(360deg); } }
  .ai-meter { font-size: .88rem; color: var(--muted); margin-top: 6px; }

  /* Ask AI: one line per half, at the foot of the half it changes. */
  .ask-ai { margin-top: 20px; padding-top: 18px; border-top: 2px dashed var(--edge); }
  .ask-label { display: block; font: 600 1rem 'Fredoka', sans-serif; margin-bottom: 6px; }
  .ask-row { display: flex; gap: 10px; align-items: stretch; }
  .ask-input { flex: 1 1 auto; min-width: 0; font: inherit; padding: .6em .8em;
               border: 2px solid var(--edge); border-radius: 12px; background: var(--card); color: var(--ink); }
  .ask-input:focus { border-color: var(--accent); outline: none; }
  .ask-input:disabled { opacity: .6; }
  .ask-row .btn { flex: none; }
  .ask-note { margin-top: 10px; font-size: .92rem; border-radius: 12px; padding: 10px 12px; background: var(--wash); }
  .ask-note.problems { background: var(--gold-soft); color: #5B4200; }
  /* Waiting on a model. The spinner rides the note rather than sitting in a
     banner at the top of the page, where a teacher deep in a thirty-word bank
     would never see it. */
  .ask-note.busy { display: flex; align-items: center; gap: 10px; }
  .ask-note.busy::before { content: ''; width: 16px; height: 16px; border-radius: 50%; flex: none;
                           border: 3px solid rgba(190, 0, 135, .25); border-top-color: var(--accent);
                           animation: spin .8s linear infinite; }
  .ask-note.ready { background: #E3F6F4; color: #0B4F4B; }
  @media (max-width: 520px) { .ask-row { flex-direction: column; } }

  /* What the model just rewrote. Loud enough to find by scrolling, because
     the whole point is that the teacher checks it. */
  .ai-changed { outline: 3px solid var(--gold); outline-offset: 3px; border-radius: 12px; }

  .loading { color: var(--muted); }
@endpush

@section('content')
{{-- Wider than the account pages: five board columns side by side is the point
     of the board view, and 760px fits three. --}}
<div class="wrap">
  @if ($errors->has('publish'))
    <div class="checks problems" style="margin-bottom: 16px">
      <h3>This game isn't ready to play yet</h3>
      <ul>
        @foreach ($errors->get('publish') as $problem)
          @foreach ((array) $problem as $line)
            <li>{{ $line }}</li>
          @endforeach
        @endforeach
      </ul>
      <p class="muted-note">Fix those and publish again. Everything else is yours to judge.</p>
    </div>
  @endif

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
       data-game-id="{{ $game->id }}"
       data-kind="{{ $game->kind }}"
       {{-- The deck types, so the editor can say which one this game uses and
            let a teacher change it when the guess was wrong. --}}
       data-deck-types="{{ json_encode($deckTypes, JSON_UNESCAPED_UNICODE) }}"
       data-draft="{{ $game->isPublished() ? '' : '1' }}"
       data-write-url="{{ route('my-games.write', $game) }}"
       data-publish-url="{{ route('my-games.publish', $game) }}"
       data-writing-url="{{ route('my-games.writing', $game) }}"
       data-ai-percent="{{ $aiPercentUsed ?? '' }}"
       data-cards-worth-printing="{{ ($cardsWorthPrinting ?? true) ? '1' : '' }}"
       data-answer-sheet-url="{{ route('my-games.answer-sheet', $game) }}"
       data-answer-sheet-status-url="{{ route('my-games.answer-sheet.status', $game) }}"
       data-answer-sheet="{{ ($answerSheetAvailable ?? false) ? '1' : '' }}"
       data-ask-url="{{ route('my-games.ask', $game) }}"
       data-asking-url="{{ route('my-games.asking', $game) }}"
       data-writing="{{ \App\Jobs\WriteGame::statusOf($game->id)['status'] }}"
       {{-- An edit left running when the teacher closed the tab: the page
            picks the wait back up rather than showing a game mid-change. --}}
       data-asking="{{ \App\Jobs\EditGame::statusOf($game->id)['status'] }}">
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
