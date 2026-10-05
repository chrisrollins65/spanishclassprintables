@extends('account.layout')

@section('title', 'Make a game')

@push('styles')
  /* Lays itself out to the number of options rather than to a fixed two.
     The deck types are three and a fixed 1fr 1fr left the third stranded on a
     row of its own; auto-fit gives two across for a pair and three for a trio,
     and collapses to one column on a phone without a media query. */
  .choices { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
             gap: 16px; margin-top: 8px; }
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
  .topic:focus, .describe:focus { border-color: var(--accent); outline: none; }
  .describe { width: 100%; font: inherit; font-size: 1rem; padding: .7em .8em; margin-top: 10px;
              border: 2px solid var(--edge); border-radius: 14px; background: var(--card);
              color: var(--ink); resize: vertical; }
  .go { margin-top: 26px; }
  .credits-note { margin-top: 18px; font-size: .92rem; color: var(--muted); }
@endpush

@section('content')
{{-- "roomy", not "narrow": narrow is 460px, the width of the login box, and
     this is a page of choice cards rather than a stack of inputs. At 460 the
     cards came out 167px wide, every label wrapped over three lines, and the
     column was visibly narrower than the site's own 1120px header. The prose
     keeps its own reading width inside it — see .roomy in the layout. --}}
<div class="wrap roomy">
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
      {{-- data-once: this press no longer spends a credit — that moved to the
           confirm screen — but it does cost a model call, and it is slow
           enough that an impatient second press was sending two. --}}
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
          <p>A topic your class is studying — “Food and meals”, “La ropa”, “Los verbos del aula” —
             or the piece of grammar you are drilling: “El pretérito de los verbos -ar”, “Por y para”.</p>
          <label class="visually-hidden" for="theme">Topic</label>
          <input class="topic" id="theme" name="theme" type="text" maxlength="120" required
                 value="{{ old('theme') }}" placeholder="Food and meals">
          @error('theme') <p class="error">{{ $message }}</p> @enderror
        </div>

        {{-- What the lesson is FOR, in the teacher's own words.
             This used to be a row of cards asking which kind of bank to build —
             our own distinction between words, verb forms and little words,
             which a teacher has no reason to know. They describe the lesson and
             the kind is worked out from it; the game then says which it chose
             and can be changed. The box is optional: left empty, a topic that
             does not clearly point at grammar makes a vocabulary game, which is
             what most games are. --}}
        <div class="step">
          <h2>What should it practise?</h2>
          <p>Optional, and worth a line. Say what you want them to be able to do, and
             anything about the class — what they have covered, what they keep getting
             wrong. It decides what goes on the cards and it is what the clues are
             written from.</p>
          <label class="visually-hidden" for="describe">What the game should practise</label>
          <textarea class="describe" id="describe" name="describe" rows="3" maxlength="500"
                    placeholder="They keep saying “yo levantar” instead of using the right ending. We have only done -ar verbs so far.">{{ old('describe') }}</textarea>
          @error('describe') <p class="error">{{ $message }}</p> @enderror
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

        {{-- Not "Make this game": this button no longer makes one. It reads the
             description and shows what that asks for, and the credit goes on the
             screen after it. Promising the game here and then showing another
             page would read as a step that went wrong. --}}
        <button class="btn btn-primary btn-block go" type="submit"
                data-idle="Continue" data-busy="Reading it…">Continue</button>
        <p class="credits-note">
          Next you'll see what kind of practice this makes, and can change it.
          Nothing is made and no credit is used until then.
        </p>
      </form>
    @endif
  </div>
</div>
@endsection
