@extends('account.layout')

@section('title', 'Messages')

@push('styles')
  .periods { display: flex; flex-wrap: wrap; gap: 14px; margin-top: 10px; font-weight: 700; }
  .periods .on { color: var(--ink); text-decoration: none; }
  .say { padding: 12px 0; border-top: 1px solid var(--edge); }
  .say:first-of-type { margin-top: 18px; }
  .say p { white-space: pre-wrap; overflow-wrap: anywhere; }
  .say .who { color: var(--muted); font-size: .88rem; }
  .say .tags { font-size: .88rem; font-weight: 700; }
  .empty { color: var(--muted); margin-top: 18px; }
@endpush

@section('content')
<div class="wrap roomy">
  <div class="card accent">
    <h1>Messages</h1>
    <p class="lead">What people have sent through the contact form and the feedback forms. A rating on its own is counted on Insights instead.</p>
    <div class="periods">
      @foreach ($filters as $value => $label)
        <a href="{{ route('admin.messages', ['show' => $value]) }}" @class(['on' => $value === $filter])>{{ $label }}</a>
      @endforeach
    </div>

    @forelse ($messages as $sent)
      <div class="say">
        @if ($sent instanceof \App\Models\ContactMessage)
          @php($address = $sent->email)
          <div class="tags">Contact form · {{ $sent->topicLabel() }}</div>
          <p>{{ $sent->message }}</p>
          <div class="who">
            {{ $sent->name ?: 'No name' }} ·
        @else
          @php($address = $sent->email ?? $sent->user?->email)
          <div class="tags">Game feedback · {{ $sent->ratingFace() }} {{ $sent->ratingLabel() }}</div>
          @if ($sent->issueLabels() !== [])
            <div class="tags">{{ implode(' · ', $sent->issueLabels()) }}</div>
          @endif
          @if ($sent->highlightLabels() !== [])
            <div class="tags">{{ implode(' · ', $sent->highlightLabels()) }}</div>
          @endif
          @if ($sent->comment)
            <p>{{ $sent->comment }}</p>
          @endif
          <div class="who">
            @if ($sent->user)
              <a href="{{ route('admin.teacher', $sent->user) }}">{{ $sent->user->name }}</a> ·
            @endif
            {{ $sent->room_code ?? 'their own game' }} ·
        @endif
          @if ($address)
            <a href="mailto:{{ $address }}">{{ $address }}</a> ·
          @else
            No address ·
          @endif
          <span title="{{ $sent->created_at }}">{{ $sent->created_at->diffForHumans() }}</span>
          @if ($sent->spam_reason)
            · Held: {{ $sent->spam_reason }}
          @endif
        </div>
      </div>
    @empty
      <p class="empty">Nothing here yet.</p>
    @endforelse

    {{ $messages->links() }}
  </div>
</div>
@endsection
