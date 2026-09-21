@extends('account.layout')

@section('title', 'Confirm your email')

@section('content')
<div class="wrap narrow">
  <div class="card accent">
    <h1>Check your inbox</h1>
    <p class="lead">
      We sent a link to <strong>{{ auth()->user()->email }}</strong>. Click it to confirm your
      email, and your account is ready. If it isn't there in a minute, look in spam or promotions.
    </p>

    @if (session('status') === 'verification-link-sent')
      <p class="notice">A new link is on its way.</p>
    @endif

    <form class="stack" method="post" action="{{ route('verification.send') }}">
      @csrf
      <button class="btn btn-ghost btn-block" type="submit">Send the link again</button>
    </form>

    <p class="foot">Wrong address? <a href="{{ route('account') }}">Change it</a></p>
  </div>
</div>
@endsection
