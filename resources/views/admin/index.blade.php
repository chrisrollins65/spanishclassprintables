@extends('account.layout')

@section('title', 'Teachers')

@push('styles')
  .admin-table { width: 100%; border-collapse: collapse; margin-top: 18px; font-size: .95rem; }
  .admin-table th { text-align: left; font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); padding: 6px 10px; }
  .admin-table td { padding: 10px; border-top: 1px solid var(--edge); }
  .admin-table tr:hover td { background: var(--paper); }
  .num { text-align: right; font-weight: 700; }
  .search { display: flex; gap: 10px; margin-top: 18px; }
  .search input { flex: 1; }
@endpush

@section('content')
<div class="wrap">
  <div class="card accent">
    <h1>Teachers</h1>
    <p class="lead">{{ $teachers->total() }} accounts.</p>

    <form class="search" method="get" action="{{ route('admin.index') }}">
      <label class="visually-hidden" for="q">Search by name or email</label>
      <input id="q" name="q" type="text" value="{{ $search }}" placeholder="Name or email">
      <button class="btn btn-primary" type="submit">Search</button>
    </form>

    <table class="admin-table">
      <thead>
        <tr><th>Teacher</th><th class="num">Credits</th><th class="num">Games</th><th>Joined</th></tr>
      </thead>
      <tbody>
        @foreach ($teachers as $teacher)
          <tr>
            <td>
              <a href="{{ route('admin.teacher', $teacher) }}">{{ $teacher->name }}</a><br>
              <span class="hint">{{ $teacher->email }}</span>
            </td>
            <td class="num">{{ (int) $teacher->credits }}</td>
            <td class="num">{{ $teacher->games }}</td>
            <td>{{ $teacher->created_at->diffForHumans() }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>

    {{ $teachers->links() }}
  </div>
</div>
@endsection
