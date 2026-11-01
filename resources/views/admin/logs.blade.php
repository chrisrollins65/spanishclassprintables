@extends('account.layout')

@section('title', 'Logs')

@push('styles')
  .search { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
  .search input { flex: 1; min-width: 200px; }
  .periods { display: flex; flex-wrap: wrap; gap: 14px; margin-top: 14px; font-weight: 700; }
  .periods .on { color: var(--ink); text-decoration: none; }
  .log { border-top: 1px solid var(--edge); }
  .log:first-of-type { margin-top: 18px; }
  .log summary { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 10px; padding: 8px 0; cursor: pointer; }
  .log .level { font-size: .78rem; font-weight: 800; text-transform: uppercase; color: var(--muted); }
  .log .level-warning { color: #9A6A00; }
  .log .level-error, .log .level-critical, .log .level-alert, .log .level-emergency { color: var(--pink); }
  .log time { font-family: ui-monospace, Consolas, monospace; font-size: .85rem; color: var(--muted); }
  .log .text { flex: 1 1 320px; min-width: 0; overflow-wrap: anywhere; }
  .log .who { font-size: .85rem; }
  .log pre { margin: 0 0 12px; padding: 12px; max-height: 60vh; overflow: auto; background: var(--paper); border-radius: 8px;
             font-size: .82rem; white-space: pre-wrap; overflow-wrap: anywhere; }
  .empty { color: var(--muted); margin-top: 18px; }
@endpush

@section('content')
<div class="wrap roomy">
  <div class="card accent">
    <h1>Logs</h1>
    <p class="lead">What the site has written to its log, newest first.</p>

    <form class="search" method="get" action="{{ route('admin.logs') }}">
      <label class="visually-hidden" for="show">Which logs</label>
      <select id="show" name="show">
        @foreach ($periods as $days => $label)
          <option value="{{ $days }}" @selected((string) $days === $show)>{{ $label }}</option>
        @endforeach
        @foreach ($files as $listed)
          <option value="{{ $listed['file'] }}" @selected($listed['file'] === $show)>
            {{ $listed['date'] ?? $listed['file'] }} · {{ number_format(max(1, round($listed['size'] / 1024))) }} KB
          </option>
        @endforeach
      </select>
      <label class="visually-hidden" for="q">Search messages and stack traces</label>
      <input id="q" name="q" type="text" value="{{ $search }}" placeholder="Search messages and stack traces">
      @if ($level)
        <input type="hidden" name="level" value="{{ $level }}">
      @endif
      <button class="btn btn-primary" type="submit">Show</button>
    </form>

    <div class="periods">
      <a href="{{ route('admin.logs', ['show' => $show, 'q' => $search ?: null]) }}" @class(['on' => $level === null])>All {{ number_format($logged) }}</a>
      @foreach ($levels as $value => $count)
        <a href="{{ route('admin.logs', ['show' => $show, 'level' => $value, 'q' => $search ?: null]) }}" @class(['on' => $value === $level])>{{ $levelLabels[$value] }} {{ number_format($count) }}</a>
      @endforeach
    </div>

    @if ($truncated)
      <p class="empty">Only the newest {{ number_format($logged) }} entries are read.</p>
    @endif

    @forelse ($entries as $entry)
      <details class="log">
        <summary>
          <span class="level level-{{ $entry['level'] }}">{{ $entry['level'] }}</span>
          <time>{{ $entry['time'] }}</time>
          <span class="text">{{ $entry['summary'] }}</span>
          @if ($entry['user_id'])
            <a class="who" href="{{ route('admin.teacher', $entry['user_id']) }}">User #{{ $entry['user_id'] }}</a>
          @endif
        </summary>
        <pre>{{ $entry['details'] === '' ? $entry['message'] : $entry['message']."\n".$entry['details'] }}</pre>
      </details>
    @empty
      <p class="empty">{{ $logged > 0 ? 'Nothing matches.' : 'Nothing logged.' }}</p>
    @endforelse

    {{ $entries->links() }}
  </div>
</div>
@endsection
