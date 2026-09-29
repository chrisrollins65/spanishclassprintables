@extends('account.layout')

@section('title', 'Make a game')

@push('styles')
  .choices { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 8px; }
  @media (max-width: 620px) { .choices { grid-template-columns: 1fr; } }
  .choice { position: relative; }
  .choice input { position: absolute; opacity: 0; width: 1px; height: 1px; }
  .choice span { display: block; border: 2px solid var(--edge); border-radius: 16px; padding: 18px; background: var(--paper); cursor: pointer; }
  .choice input:checked + span { border-color: var(--accent); background: var(--card); box-shadow: 0 6px 18px rgba(190, 0, 135, .12); }
  .choice input:focus-visible + span { outline: 3px solid var(--gold); outline-offset: 2px; }
  .choice strong { display: block; font: 600 1.2rem 'Fredoka', sans-serif; margin-bottom: 4px; }
  .choice em { font-style: normal; color: var(--muted); font-size: .92rem; }
  .step { margin-top: 26px; }
  .step > h2 { font-size: 1.15rem; }
  .step > p { color: var(--muted); font-size: .95rem; margin-top: 4px; }
  .topic { width: 100%; font: 600 1.15rem 'Fredoka', sans-serif; padding: .7em .8em; margin-top: 10px;
           border: 2px solid var(--edge); border-radius: 14px; background: var(--card); color: var(--ink); }
  .topic:focus { border-color: var(--accent); outline: none; }
  .go { margin-top: 26px; }
  .credits-note { margin-top: 18px; font-size: .92rem; color: var(--muted); }
@endpush

@section('content')
<div class="wrap narrow">
  <div class="card accent">
    <h1>Make a game</h1>
    <p class="lead">One credit makes one game. You have <strong>{{ $credits }}</strong> {{ $credits === 1 ? 'credit' : 'credits' }}.</p>

    @if ($credits < 1)
      <p class="notice">
        You have no credits left.
        @if ($held > 0)
          {{ $held }} {{ $held === 1 ? 'is' : 'are' }} being held by {{ $held === 1 ? 'a draft' : 'drafts' }} — delete one from
          <a href="{{ route('my-games') }}">My games</a> to use it here instead, or
        @endif
        <a href="{{ route('credits') }}">buy more</a>.
      </p>
    @else
      {{-- data-once: this press spends a credit, and a double-tap spent two.
           The server refuses the repeat as well; see TeacherGameController. --}}
      <form method="post" action="{{ route('my-games.store') }}" data-once>
        @csrf

        <div class="step">
          <h2>Which game?</h2>
          <p>This can't be changed later — a bingo pack and a quiz board are written differently.</p>
          <div class="choices">
            @foreach ($kinds as $value => $label)
              <label class="choice">
                <input type="radio" name="kind" value="{{ $value }}" {{ $loop->first ? 'checked' : '' }} required>
                <span>
                  <strong>{{ $label }}</strong>
                  <em>{{ $value === 'bingo'
                      ? 'Thirty words, called out by the website. Students play on printed cards.'
                      : 'Five categories of clues for teams, read out by the website.' }}</em>
                </span>
              </label>
            @endforeach
          </div>
          @error('kind') <p class="error">{{ $message }}</p> @enderror
        </div>

        <div class="step">
          <h2>What is it about?</h2>
          <p>A topic your class is studying — “Food and meals”, “La ropa”, “Los verbos del aula”.</p>
          <label class="visually-hidden" for="theme">Topic</label>
          <input class="topic" id="theme" name="theme" type="text" maxlength="120" required
                 value="{{ old('theme') }}" placeholder="Food and meals">
          @error('theme') <p class="error">{{ $message }}</p> @enderror
        </div>

        <div class="step">
          <h2>Who writes it?</h2>
          <div class="choices">
            <label class="choice">
              <input type="radio" name="written_by" value="ai" {{ $aiAvailable ? 'checked' : 'disabled' }} required>
              <span>
                <strong>Let AI write it</strong>
                <em>{{ $aiAvailable
                    ? 'A minute or so, then it is yours to change — every word, clue and answer.'
                    : 'Not available at the moment. You can still write your own.' }}</em>
              </span>
            </label>
            <label class="choice">
              <input type="radio" name="written_by" value="me" {{ $aiAvailable ? '' : 'checked' }} required>
              <span>
                <strong>I'll write it</strong>
                <em>Start with an empty game and type your own words and clues.</em>
              </span>
            </label>
          </div>
          @error('written_by') <p class="error">{{ $message }}</p> @enderror
        </div>

        <button class="btn btn-primary btn-block go" type="submit"
                data-idle="Make this game" data-busy="Making it…">Make this game</button>
        <p class="credits-note">
          This uses one of your credits. While it is a draft you can delete it and get the credit back.
        </p>
      </form>
    @endif
  </div>
</div>
@endsection
