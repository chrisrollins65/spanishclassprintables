@extends('account.layout')

@section('title', $teacher->name)

@push('styles')
  .admin-table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: .95rem; }
  .admin-table th { text-align: left; font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); padding: 6px 10px; }
  .admin-table td { padding: 10px; border-top: 1px solid var(--edge); vertical-align: top; }
  .num { text-align: right; font-weight: 700; }
  .plus { color: #0B6B3A; }
  .minus { color: #B3261E; }
  .row-form { display: flex; gap: 8px; align-items: flex-end; flex-wrap: wrap; }
  .row-form .field { flex: 1; min-width: 140px; }
  .row-form input { width: 100%; font: inherit; border: 2px solid var(--edge); border-radius: 10px; padding: .5em .7em; background: var(--card); }
  .row-form .btn { padding: .6em 1.1em; font-size: .95rem; }
  .balance { font: 700 2rem 'Fredoka', sans-serif; color: var(--accent); }
  .small-btn { border: 0; background: none; color: var(--accent); cursor: pointer; font: 700 .88rem 'Nunito', sans-serif; text-decoration: underline; padding: 0; }
  .locked-tag { color: #8A5A00; font-weight: 700; font-size: .85rem; }
@endpush

@section('content')
<div class="wrap">
  <div class="card accent">
    <h1>{{ $teacher->name }}</h1>
    <p class="lead">{{ $teacher->email }} · joined {{ $teacher->created_at->toFormattedDateString() }}</p>

    @if (session('status'))
      <p class="notice">{{ session('status') }}</p>
    @endif

    {{-- Three numbers, because a support question needs all three: what they
         can spend now, what their drafts are sitting on, and what they have
         been given altogether. The ledger total alone looks like a balance
         and isn't one. --}}
    <p class="balance">{{ $teacher->availableCredits() }} credits</p>
    <p class="hint">ready to use · {{ $teacher->heldCredits() }} held by drafts ·
      {{ $teacher->creditsPurchased() }} bought or given in total</p>

    {{-- By hand: an apology, a test account, a sale that arrived without its
         webhook. Undoing a purchase is a refund, not this. --}}
    <form class="row-form" method="post" action="{{ route('admin.credits', $teacher) }}" data-once style="margin-top: 14px">
      @csrf
      <div class="field">
        <label for="delta">Credits (+/−)</label>
        <input id="delta" name="delta" type="number" step="1" min="-100" max="100" required>
      </div>
      <div class="field">
        <label for="note">Why</label>
        <input id="note" name="note" type="text" maxlength="200" required>
      </div>
      <button class="btn btn-ghost" type="submit">Record it</button>
    </form>
    @error('delta') <p class="error">{{ $message }}</p> @enderror
    @error('note') <p class="error">{{ $message }}</p> @enderror
  </div>

  <div class="card">
    <h2>Ledger</h2>
    <table class="admin-table">
      <thead><tr><th>When</th><th>What</th><th>Reference</th><th class="num">Credits</th><th></th></tr></thead>
      <tbody>
        @forelse ($entries as $entry)
          <tr>
            <td>{{ $entry->created_at->toDayDateTimeString() }}</td>
            <td>{{ ucfirst($entry->reason) }}<br><span class="hint">{{ $entry->note }}</span></td>
            <td><span class="hint">{{ $entry->reference }}</span></td>
            <td class="num {{ $entry->delta > 0 ? 'plus' : 'minus' }}">{{ $entry->delta > 0 ? '+' : '' }}{{ $entry->delta }}</td>
            <td>
              @if ($entry->reason === \App\Models\CreditEntry::PURCHASE)
                {{-- Paddle refunds the money and tells us through the webhook;
                     the credits come off there, not here. --}}
                <form method="post" action="{{ route('admin.refund', $entry) }}" data-once
                      onsubmit="return confirm({{ Js::from('Ask Paddle to refund '.$entry->reference.' in full?') }})">
                  @csrf
                  <input type="hidden" name="reason" value="Requested by the teacher">
                  <button class="small-btn" type="submit">Refund</button>
                </form>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="hint">Nothing yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="card">
    <h2>Games</h2>
    <table class="admin-table">
      <thead><tr><th>Game</th><th>Kind</th><th>Where from</th><th></th></tr></thead>
      <tbody>
        @forelse ($games as $game)
          <tr>
            <td>
              {{ $game->theme }}
              @if ($game->isLocked())
                <br><span class="locked-tag">Locked — {{ $game->locked_reason }}</span>
              @endif
              @if ($game->trashed())
                <br><span class="hint">Removed by the teacher</span>
              @endif
            </td>
            <td>{{ $game->gamesLabel() }}</td>
            <td><span class="hint">{{ $game->source_code ? 'Claimed from '.$game->source_code : 'Created here' }}</span></td>
            <td>
              <form method="post" action="{{ $game->isLocked() ? route('admin.unlock', $game) : route('admin.lock', $game) }}" data-once>
                @csrf
                <button class="small-btn" type="submit">{{ $game->isLocked() ? 'Unlock' : 'Lock' }}</button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="4" class="hint">No games.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
