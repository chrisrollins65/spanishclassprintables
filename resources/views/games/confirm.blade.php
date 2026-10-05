@extends('account.layout')

@section('title', 'What we will build')

@push('styles')
  /* The same choice cards as the form before it, so changing the kind here
     looks like the choice it is rather than a correction. */
  .choices { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
             gap: 16px; margin-top: 8px; }
  .choice { position: relative; }
  .choice input { position: absolute; opacity: 0; width: 1px; height: 1px; }
  .choice span { display: block; border: 2px solid var(--edge); border-radius: 16px; padding: 18px; background: var(--paper); cursor: pointer; }
  .choice input:checked + span { border-color: var(--accent); background: var(--card); box-shadow: 0 6px 18px rgba(190, 0, 135, .12); }
  .choice input:focus-visible + span { outline: 3px solid var(--gold); outline-offset: 2px; }
  .choice strong { display: block; font: 600 1.2rem 'Fredoka', sans-serif; margin-bottom: 4px; }
  .choice em { font-style: normal; color: var(--muted); font-size: .92rem; }
  /* What was read from the description, stated before the cards rather than
     as a label on one of them: the teacher is being told something, and only
     then asked whether it is right. */
  .reading { border: 2px solid var(--accent); border-radius: 16px; padding: 18px 20px;
             background: var(--card); margin-top: 8px; }
  .reading .what { font: 600 1.35rem 'Fredoka', sans-serif; margin: 0; }
  .reading .why { color: var(--muted); margin: 6px 0 0; font-size: .95rem; }
  .asked { margin-top: 22px; font-size: .95rem; color: var(--muted); }
  .asked dt { font-weight: 700; color: var(--ink); }
  .asked dd { margin: 2px 0 10px; }
  .step { margin-top: 26px; }
  .step > h2 { font-size: 1.15rem; }
  .step > p { color: var(--muted); font-size: .95rem; margin-top: 4px; }
  .go { margin-top: 26px; }
  .back { display: inline-block; margin-top: 14px; font-size: .95rem; }
@endpush

@section('content')
@php
  $chosen = collect($types)->firstWhere('id', $pending['type']) ?? ($types[0] ?? null);
@endphp
<div class="wrap roomy">
  <div class="card accent">
    <h1>What we'll build</h1>
    <p class="lead">
      Check this is the kind of practice you wanted — it decides what every clue on the board asks for.
    </p>

    <div class="reading">
      <p class="what">{{ $chosen['label'] ?? 'Vocabulary words' }}</p>
      @if ($pending['why'])
        <p class="why">{{ $pending['why'] }}</p>
      @else
        <p class="why">{{ $chosen['blurb'] ?? '' }}</p>
      @endif
    </div>

    {{-- data-once: THIS is the press that spends a credit now, and a double-tap
         spent two. It also swaps the button to "Making it…", which is the only
         thing on screen saying the press worked — without it the page sits
         still while the game is made and invites another press. The server
         refuses the repeat as well; see TeacherGameController::makeGame. --}}
    <form method="post" action="{{ route('my-games.begin') }}" data-once>
      @csrf
      <input type="hidden" name="kind" value="{{ $pending['kind'] }}">
      <input type="hidden" name="theme" value="{{ $pending['theme'] }}">
      <input type="hidden" name="describe" value="{{ $pending['describe'] }}">
      <input type="hidden" name="written_by" value="{{ $pending['written_by'] }}">
      {{-- What the classifier said, so begin() can drop its reason if the
           teacher picks something else. --}}
      <input type="hidden" name="classified" value="{{ $pending['type'] }}">
      <input type="hidden" name="why" value="{{ $pending['why'] }}">

      <div class="step">
        <h2>Not what you meant?</h2>
        <div class="choices">
          @foreach ($types as $type)
            <label class="choice">
              <input type="radio" name="type" value="{{ $type['id'] }}"
                     {{ $type['id'] === ($chosen['id'] ?? '') ? 'checked' : '' }} required>
              <span>
                <strong>{{ $type['label'] }}</strong>
                <em>{{ $type['blurb'] }}</em>
              </span>
            </label>
          @endforeach
        </div>
        @error('type') <p class="error">{{ $message }}</p> @enderror
      </div>

      <dl class="asked">
        <dt>Topic</dt>
        <dd>{{ $pending['theme'] }}</dd>
        @if ($pending['describe'])
          <dt>What it should practise</dt>
          <dd>{{ $pending['describe'] }}</dd>
        @endif
        <dt>Written by</dt>
        <dd>{{ $pending['written_by'] === 'ai' ? 'AI, then yours to change' : 'You' }}</dd>
      </dl>

      <button class="btn btn-primary btn-block go" type="submit"
              data-idle="Make this game" data-busy="Making it…">Make this game</button>
    </form>

    <a class="back" href="{{ route('my-games.create') }}">← Change the topic or description</a>
  </div>
</div>
@endsection
