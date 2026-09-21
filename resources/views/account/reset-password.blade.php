@extends('account.layout')

@section('title', 'Choose a new password')

@section('content')
<div class="wrap narrow">
  <div class="card accent">
    <h1>Choose a new password</h1>

    <form class="stack" method="post" action="{{ route('password.update') }}">
      @csrf
      <input type="hidden" name="token" value="{{ $request->route('token') }}">
      <div>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" autocomplete="username" required>
        @error('email') <p class="error">{{ $message }}</p> @enderror
      </div>
      <div>
        <label for="password">New password</label>
        <input id="password" name="password" type="password" autocomplete="new-password" minlength="10" required autofocus>
        <p class="hint">At least 10 characters.</p>
        @error('password') <p class="error">{{ $message }}</p> @enderror
      </div>
      <div>
        <label for="password_confirmation">New password again</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
      </div>
      <button class="btn btn-primary btn-block" type="submit">Save new password</button>
    </form>
  </div>
</div>
@endsection
