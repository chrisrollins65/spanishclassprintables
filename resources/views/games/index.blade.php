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
  .locked { color: #8A5A00; background: var(--gold-soft); border-radius: 10px; padding: 8px 12px; margin-top: 6px; font-size: .9rem; font-weight: 600; }
@endpush

@section('content')
<div class="wrap medium">
  <div class="card accent">
    <h1>My games</h1>
    <p class="lead">Add a game you bought on TpT with its code. Your copy is private to your account.</p>

    @if (session('status'))
      <p class="notice">{{ session('status') }}</p>
    @endif

    <form class="claim" method="post" action="{{ route('my-games.claim') }}">
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
              <p class="meta">{{ $game->gamesLabel() }} · changed {{ $game->updated_at->diffForHumans() }}</p>
              {{-- A refunded purchase keeps the game but stops it playing, so
                   say so here rather than letting the buttons fail. --}}
              @if ($game->isLocked())
                <p class="locked">Locked — this game's purchase was refunded. Buy a credit to use it again; your words and questions are still here.</p>
              @endif
            </div>
            <div class="actions">
              @unless ($game->isLocked())
                <a class="btn btn-ghost" href="{{ route('my-games.edit', $game) }}">Edit</a>
                <a class="btn btn-primary" href="{{ route('my-games.play', $game) }}">Play ▸</a>
              @endunless
              <form method="post" action="{{ route('my-games.destroy', $game) }}"
                    onsubmit="return confirm({{ Js::from('Remove “'.$game->theme.'” from your games? Your changes to it will be lost.') }})">
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
