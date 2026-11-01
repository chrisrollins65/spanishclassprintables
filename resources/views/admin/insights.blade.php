@extends('account.layout')

@section('title', 'Insights')

@push('styles')
  .periods { display: flex; gap: 14px; margin-top: 10px; font-weight: 700; }
  .periods .on { color: var(--ink); text-decoration: none; }
  .tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-top: 20px; }
  .tile { border: 1px solid var(--edge); border-radius: 14px; background: var(--paper); padding: 12px 14px; }
  .tile b { display: block; font: 600 1.9rem/1.2 'Fredoka', sans-serif; }
  .tile span { color: var(--muted); font-size: .88rem; }
  .data { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: .95rem; }
  .data th { text-align: left; font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); padding: 6px 10px; }
  .data td { padding: 8px 10px; border-top: 1px solid var(--edge); vertical-align: top; }
  .data .num { text-align: right; font-weight: 700; white-space: nowrap; }
  .bar-cell { width: 40%; }
  .bar { height: 12px; min-width: 2px; border-radius: 0 4px 4px 0; background: var(--accent); }
  .say { padding: 12px 0; border-top: 1px solid var(--edge); }
  .say:first-of-type { margin-top: 10px; }
  .say .who { color: var(--muted); font-size: .88rem; }
  .say .tags { font-size: .88rem; font-weight: 700; }
  .card h2 + .hint { margin-bottom: 4px; }
  .empty { color: var(--muted); margin-top: 10px; }
@endpush

@section('content')
@php($totals = $insights->totals())
@php($struggles = $insights->struggles())
@php($ai = $insights->aiWriting())
@php($ratings = $insights->ratings())
@php($feedback = $insights->latestFeedback())
@php($hardest = $insights->hardestClues())
@php($top = $insights->topGames())
<div class="wrap roomy">
  <div class="card accent">
    <h1>Insights</h1>
    <p class="lead">How the games are being used, and what teachers say about them. Our own plays are left out.</p>
    <div class="periods">
      @foreach ($periods as $period)
        <a href="{{ route('admin.insights', ['days' => $period]) }}" @class(['on' => $period === $days])>{{ $period }} days</a>
      @endforeach
    </div>

    <div class="tiles">
      <div class="tile"><b>{{ $totals['opened'] }}</b><span>Rooms opened</span></div>
      <div class="tile"><b>{{ $totals['started'] }}</b><span>Games started</span></div>
      <div class="tile"><b>{{ $totals['finish_rate'] === null ? '—' : $totals['finish_rate'].'%' }}</b><span>Finished ({{ $totals['finished'] }})</span></div>
      <div class="tile"><b>{{ $totals['median_minutes'] === null ? '—' : $totals['median_minutes'].' min' }}</b><span>Typical game</span></div>
      @foreach (\App\Models\Feedback::RATINGS as $value => $option)
        <div class="tile"><b>{{ $ratings[$value] }}</b><span>{{ $option['face'] }} {{ $option['label'] }}</span></div>
      @endforeach
    </div>
  </div>

  <div class="card">
    <h2>What teachers say</h2>
    @forelse ($feedback as $answer)
      <div class="say">
        <div>{{ $answer->ratingFace() }} <strong>{{ $answer->ratingLabel() }}</strong></div>
        @if ($answer->issueLabels() !== [])
          <div class="tags">{{ implode(' · ', $answer->issueLabels()) }}</div>
        @endif
        @if ($answer->highlightLabels() !== [])
          <div class="tags">{{ implode(' · ', $answer->highlightLabels()) }}</div>
        @endif
        @if ($answer->comment)
          <p>{{ $answer->comment }}</p>
        @endif
        <div class="who">
          {{ $answer->user?->email ?? $answer->email ?? 'Anonymous' }} ·
          {{ $answer->room_code ?? 'their own game' }} ·
          {{ $answer->created_at->diffForHumans() }}
        </div>
      </div>
    @empty
      <p class="empty">No feedback yet.</p>
    @endforelse
  </div>

  <div class="card">
    <h2>From sign-up to repeat use</h2>
    <p class="hint">Accounts created in this period, and how far each got.</p>
    <table class="data">
      <thead><tr><th>Step</th><th class="bar-cell"><span class="visually-hidden">Share</span></th><th class="num">Teachers</th><th class="num">Share</th></tr></thead>
      <tbody>
        @foreach ($insights->funnel() as $step)
          <tr title="{{ $step['label'] }}: {{ $step['count'] }}">
            <td>{{ $step['label'] }}</td>
            <td class="bar-cell"><div class="bar" style="width: {{ $step['percent'] ?? 0 }}%"></div></td>
            <td class="num">{{ $step['count'] }}</td>
            <td class="num">{{ $step['percent'] === null ? '—' : $step['percent'].'%' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div class="card">
    <h2>Which games</h2>
    <table class="data">
      <thead><tr><th>Game</th><th class="num">Started</th><th class="num">Finished</th><th class="num">Rate</th></tr></thead>
      <tbody>
        @foreach ($insights->byGame() as $row)
          <tr>
            <td>{{ $row['game'] }}</td>
            <td class="num">{{ $row['started'] }}</td>
            <td class="num">{{ $row['finished'] }}</td>
            <td class="num">{{ $row['finish_rate'] === null ? '—' : $row['finish_rate'].'%' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>

    @if ($top->isNotEmpty())
      <table class="data">
        <thead><tr><th>Most played</th><th class="num">Started</th><th class="num">Finished</th><th class="num">Rating (1–3)</th></tr></thead>
        <tbody>
          @foreach ($top as $row)
            <tr>
              <td>{{ $row['theme'] }} @if ($row['code'])<span class="hint">{{ $row['code'] }}</span>@endif</td>
              <td class="num">{{ $row['started'] }}</td>
              <td class="num">{{ $row['finished'] }}</td>
              <td class="num">{{ $row['average'] === null ? '—' : $row['average'].' ('.$row['ratings'].')' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>

  <div class="card">
    <h2>Where they struggle</h2>
    <table class="data">
      <tbody>
        <tr><td>Opened a room but never started a game</td><td class="num">{{ $struggles['abandoned'] }}</td></tr>
        <tr><td>Opened on a device with no Spanish voice</td><td class="num">{{ $struggles['no_voice'] }}</td></tr>
        <tr><td>Opened How to Play</td><td class="num">{{ $struggles['how_to'] }}</td></tr>
      </tbody>
    </table>

    @if ($struggles['wrong_codes']->isNotEmpty())
      <table class="data">
        <thead><tr><th>Codes that weren't found</th><th class="num">Times</th></tr></thead>
        <tbody>
          @foreach ($struggles['wrong_codes'] as $code => $times)
            <tr><td>{{ $code }}</td><td class="num">{{ $times }}</td></tr>
          @endforeach
        </tbody>
      </table>
    @endif

    @if ($struggles['errors']->isNotEmpty())
      <table class="data">
        <thead><tr><th>Errors on the game screen</th><th class="num">Times</th></tr></thead>
        <tbody>
          @foreach ($struggles['errors'] as $message => $times)
            <tr><td>{{ $message }}</td><td class="num">{{ $times }}</td></tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>

  <div class="card">
    <h2>Hardest clues</h2>
    <p class="hint">Quiz clues played at least three times, by how often a team got them. A low score is a hard clue or a bad one.</p>
    @if ($hardest->isEmpty())
      <p class="empty">Not enough plays yet.</p>
    @else
      <table class="data">
        <thead><tr><th>Clue</th><th>Category</th><th class="num">Played</th><th class="num">Right</th></tr></thead>
        <tbody>
          @foreach ($hardest as $clue)
            <tr>
              <td>{{ $clue['clue'] }}</td>
              <td>{{ $clue['category'] }}</td>
              <td class="num">{{ $clue['plays'] }}</td>
              <td class="num">{{ $clue['right'] }}%</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>

  <div class="card">
    <h2>AI writing</h2>
    <p class="hint">
      {{ $ai['published'] }} AI {{ $ai['published'] === 1 ? 'draft' : 'drafts' }} published.
      @if ($ai['kept'] !== null)
        Teachers kept {{ $ai['kept'] }}% of the words and clues exactly as written.
      @endif
    </p>
    @if ($ai['requests']->isNotEmpty())
      <table class="data">
        <thead><tr><th>Latest changes teachers asked AI for</th></tr></thead>
        <tbody>
          @foreach ($ai['requests'] as $request)
            <tr><td>{{ $request }}</td></tr>
          @endforeach
        </tbody>
      </table>
    @endif
  </div>
</div>
@endsection
