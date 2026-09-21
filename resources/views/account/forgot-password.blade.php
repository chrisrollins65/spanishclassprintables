@extends('account.layout')

@section('title', 'Reset your password')

@section('content')
<div class="wrap narrow">
  <div class="card accent">
    <h1>Forgot your password?</h1>
    <p class="lead">Type your email and we'll send you a link to choose a new one.</p>

    @if (session('status'))
      <p class="notice">{{ session('status') }}</p>
    @endif

    <form class="stack" method="post" action="{{ route('password.email') }}">
      @csrf
      <div>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
        @error('email') <p class="error">{{ $message }}</p> @enderror
      </div>
      <button class="btn btn-primary btn-block" type="submit">Send the link</button>
    </form>

    <p class="foot"><a href="{{ route('login') }}">Back to log in</a></p>
  </div>
</div>
@endsection
