@extends('account.layout')

@section('title', 'My games')

@push('styles')
  .claim { display: flex; gap: 10px; margin-top: 18px; }
  .claim input {
    flex: 1; min-width: 0; font: 700 1.3rem/1 'Fredoka', sans-serif; letter-spacing: .16em; text-transform: uppercase;
    text-align: center; padding: .6em .4em; border: 2px solid var(--edge); border-radius: 14px; background: var(--paper);
  }
  .claim input::placeholder { color: #BBAAB5; letter-spacing: .12em; }
  .claim .btn { border-radius: 14px; }
  @media (max-width: 460px) { .claim { flex-direction: column; } }
  .games { list-style: none; margin: 0; padding: 0; display: grid; gap: 12px; }
  .game { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 16px 18px; border: 1px solid var(--edge); border-radius: 14px; background: var(--paper); }
  .game h3 { font-size: 1.2rem; }
  .game .meta { color: var(--muted); font-size: .92rem; margin-top: 2px; }
  .game .actions { display: flex; align-items: center; gap: 8px; flex: none; }
  .game .btn { padding: .65em 1.1em; font-size: .98rem; }
  .game form { margin: 0; }
  .remove { background: none; border: 0; color: var(--muted); font: 600 .9rem 'Nunito', sans-serif; cursor: pointer; text-decoration: underline; padding: .4em; }
  .remove:hover { color: #B3261E; }
  @media (max-width: 560px) { .game { flex-direction: column; align-items: flex-start; } }
  .empty { color: var(--muted); }
  .head-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
  .head-row .btn { flex: none; }
  .draft-tag { background: var(--gold-soft); color: #7A5200; font-weight: 800; font-size: .72rem; text-transform: uppercase;
               letter-spacing: .05em; border-radius: 999px; padding: .2em .7em; }
  .locked { color: #8A5A00; background: var(--gold-soft); border-radius: 10px; padding: 8px 12px; margin-top: 6px; font-size: .9rem; font-weight: 600; }
@endpush

@section('content')
<div class="wrap medium">
  <div class="card accent">
    <div class="head-row">
      <div>
        <h1>My games</h1>
        <p class="lead">Make your own, or add a game you bought on TpT with its code.</p>
      </div>
      <a class="btn btn-primary" href="{{ route('my-games.create') }}">Make a game</a>
    </div>

    <p class="hint">
      {{ $credits }} {{ $credits === 1 ? 'credit' : 'credits' }} ready to use.
      @if ($held > 0)
        {{ $held }} {{ $held === 1 ? 'is held by a draft' : 'are held by drafts' }} — deleting a draft gives its credit back.
      @endif
      <a href="{{ route('credits') }}">Get more</a>.
    </p>

    @if (session('status'))
      <p class="notice">{{ session('status') }}</p>
    @endif

    <form class="claim" method="post" action="{{ route('my-games.claim') }}" data-once>
      @csrf
      <label class="visually-hidden" for="code">Game code</label>
      {{-- Carried over from the game itself ("Change the words or questions"),
           so a teacher arriving from a projector does not retype the code. --}}
      <input id="code" name="code" type="text" value="{{ old('code', strtoupper((string) request('code'))) }}" placeholder="CODE" maxlength="8"
             autocomplete="off" autocapitalize="characters" spellcheck="false" required>
      <button class="btn btn-primary" type="submit">Add game</button>
    </form>
    @error('code') <p class="error">{{ $message }}</p> @enderror
    <p class="hint">The code is on the <strong>Play Online</strong> page of your download.</p>
  </div>

  <div class="card">
    <h2 style="margin-bottom: 16px">Your games</h2>

    @if ($games->isEmpty())
      <p class="empty">No games yet. Add one above with its code.</p>
    @else
      <ul class="games">
        @foreach ($games as $game)
          <li class="game">
            <div>
              <h3>{{ $game->theme }}</h3>
              <p class="meta">
                {{ $game->gamesLabel() }} · changed {{ $game->updated_at->diffForHumans() }}
                @unless ($game->isPublished()) · <span class="draft-tag">Draft</span> @endunless
              </p>
              {{-- A refunded purchase keeps the game but stops it playing, so
                   say so here rather than letting the buttons fail. --}}
              @if ($game->isLocked())
                <p class="locked">Locked — this game's purchase was refunded. Buy a credit to use it again; your words and questions are still here.</p>
              @endif
            </div>
            <div class="actions">
              @unless ($game->isLocked())
                <a class="btn btn-ghost" href="{{ route('my-games.edit', $game) }}">{{ $game->isPublished() ? 'Edit' : 'Finish it' }}</a>
                @if ($game->isPublished())
                  <a class="btn btn-primary" href="{{ route('my-games.play', $game) }}">Play ▸</a>
                @endif
              @endunless
              <form method="post" action="{{ route('my-games.destroy', $game) }}" data-once
                    onsubmit="return confirm({{ Js::from($game->isPublished()
                        ? 'Remove “'.$game->theme.'” from your games? Your changes to it will be lost.'
                        : 'Delete the draft “'.$game->theme.'”? Its credit goes back to you, but anything AI has already written for it stays used.') }})">
                @csrf
                @method('DELETE')
                <button class="remove" type="submit">Remove</button>
              </form>
            </div>
          </li>
        @endforeach
      </ul>
    @endif
  </div>
</div>
@endsection
