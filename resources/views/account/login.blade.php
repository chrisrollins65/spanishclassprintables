@extends('account.layout')

@section('title', 'Log in')

@section('content')
<div class="wrap narrow">
  <div class="card accent">
    <h1>Welcome back</h1>
    <p class="lead">Log in to edit and play your games.</p>

    @if (session('status'))
      <p class="notice">{{ session('status') }}</p>
    @endif

    <form class="stack" method="post" action="{{ route('login') }}">
      @csrf
      <div>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
        @error('email') <p class="error">{{ $message }}</p> @enderror
      </div>
      <div>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        @error('password') <p class="error">{{ $message }}</p> @enderror
      </div>
      <label class="check"><input type="checkbox" name="remember" value="1"> Keep me logged in on this computer</label>
      <button class="btn btn-primary btn-block" type="submit">Log in</button>
    </form>

    <p class="foot">
      <a href="{{ route('password.request') }}">Forgot your password?</a><br>
      New here? <a href="{{ route('register') }}">Create a free account</a>
    </p>
  </div>
</div>
@endsection
