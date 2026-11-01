@extends('account.layout')

@section('title', 'How did it go?')

@push('styles')
  .faces { display: flex; gap: 10px; flex-wrap: wrap; border: 0; padding: 0; margin: 0; }
  .face { flex: 1 1 120px; margin: 0; cursor: pointer; }
  .face input { position: absolute; opacity: 0; }
  .face span { display: block; text-align: center; padding: 12px 8px; border: 2px solid var(--edge); border-radius: 14px; background: var(--paper); font-weight: 700; }
  .face span b { display: block; font-size: 2rem; line-height: 1.2; }
  .face input:checked + span { border-color: var(--accent); background: var(--wash); }
  .face input:focus-visible + span { outline: 3px solid var(--gold); outline-offset: 2px; }
  .issues { border: 0; padding: 0; margin: 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 6px; }
  .issues[hidden] { display: none; }
  .show-other { justify-self: start; align-self: flex-start; padding: 0; border: 0; background: none; color: var(--accent); font: inherit; font-size: .95rem; text-decoration: underline; cursor: pointer; }
  .show-other[hidden] { display: none; }
  .legend { font-weight: 700; font-size: .95rem; margin-bottom: 6px; padding: 0; }
  textarea { width: 100%; min-height: 110px; font: inherit; color: var(--ink); background: var(--paper); border: 2px solid var(--edge); border-radius: 12px; padding: .7em .85em; }
  textarea:focus { border-color: var(--accent); outline: none; }
@endpush

@section('content')
<div class="wrap narrow">
  <div class="card accent">
    <h1>How did {{ $theme ? '“'.$theme.'”' : 'your game' }} go?</h1>

    @if (session('status'))
      <p class="notice">{{ session('status') }}</p>
    @else
      <p class="lead">Your answer comes straight to the people who make these games.</p>

      <form class="stack" method="post" action="{{ $action }}" id="feedback" data-once>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <fieldset class="faces">
          <legend class="visually-hidden">How did it go?</legend>
          @foreach ($ratings as $value => $option)
            <label class="face">
              <input type="radio" name="rating" value="{{ $value }}" required @checked(old('rating', $rating) == $value)>
              <span><b>{{ $option['face'] }}</b>{{ $option['label'] }}</span>
            </label>
          @endforeach
        </fieldset>
        @error('rating') <p class="error">{{ $message }}</p> @enderror

        <fieldset class="issues" id="highlights" data-main="What worked best?" data-other="Anything that worked well?">
          <legend class="legend">What worked best?</legend>
          @foreach ($highlights as $value => $label)
            <label class="check"><input type="checkbox" name="highlights[]" value="{{ $value }}"> {{ $label }}</label>
          @endforeach
        </fieldset>

        <button class="show-other" type="button" id="show-other" hidden></button>

        <fieldset class="issues" id="problems" data-main="Did anything get in the way?" data-other="Anything that could be better?">
          <legend class="legend">Did anything get in the way?</legend>
          @foreach ($issues as $value => $label)
            <label class="check"><input type="checkbox" name="issues[]" value="{{ $value }}"> {{ $label }}</label>
          @endforeach
        </fieldset>

        <div>
          <label for="comment">Anything else we should know?</label>
          <textarea id="comment" name="comment" maxlength="2000">{{ old('comment') }}</textarea>
        </div>

        <button class="btn btn-primary btn-block" type="submit">Send</button>
      </form>

      {{-- The face from the email counts as soon as a person is here, so a
           teacher who goes no further has still answered. A script because a
           mail scanner opens links too, and runs none: see FeedbackController.
           The question that fits the face leads and the opposite one is a tap
           away. Without a script both show. --}}
      <script>
        (function () {
          const form = document.getElementById('feedback');
          const chosen = () => form.querySelector('input[name=rating]:checked');
          const highlights = document.getElementById('highlights');
          const problems = document.getElementById('problems');
          const showOther = document.getElementById('show-other');
          const comment = document.getElementById('comment');
          let otherOpen = false;
          const fit = () => {
            const rating = chosen();
            const [main, other] = rating?.value === '3' ? [highlights, problems] : [problems, highlights];
            main.after(showOther);
            showOther.after(other);
            main.querySelector('legend').textContent = main.dataset.main;
            other.querySelector('legend').textContent = other.dataset.other;
            showOther.textContent = other.dataset.other;
            main.hidden = !rating;
            other.hidden = !rating || !otherOpen;
            showOther.hidden = !rating || otherOpen;
          };
          const grow = () => {
            comment.style.height = 'auto';
            comment.style.height = `${comment.scrollHeight + comment.offsetHeight - comment.clientHeight}px`;
          };
          showOther.addEventListener('click', () => {
            otherOpen = true;
            fit();
          });
          comment.addEventListener('input', grow);
          if (comment.value) grow();
          const send = () => {
            const rating = chosen();
            if (!rating) return;
            fetch(form.action, {
              method: 'POST',
              credentials: 'same-origin',
              headers: { Accept: 'application/json', 'X-CSRF-TOKEN': form.elements._token.value },
              body: new URLSearchParams({ token: form.elements.token.value, rating: rating.value }),
            }).catch(() => {});
          };
          form.querySelectorAll('input[name=rating]').forEach(input => input.addEventListener('change', () => {
            fit();
            send();
          }));
          fit();
          send();
        })();
      </script>
    @endif
  </div>
</div>
@endsection
