@extends('account.layout')

@section('title', 'Create your account')

@section('content')
<div class="wrap narrow">
  <div class="card accent">
    <h1>Create your free account</h1>
    <p class="lead">Your account keeps your games in one place, private to you.</p>

    <form class="stack" method="post" action="{{ route('register.store') }}">
      @csrf
      <div>
        <label for="name">Your name</label>
        <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" maxlength="255" required autofocus>
        @error('name') <p class="error">{{ $message }}</p> @enderror
      </div>
      <div>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="255" required>
        <p class="hint">We'll send a link to confirm it.</p>
        @error('email') <p class="error">{{ $message }}</p> @enderror
      </div>
      <div>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="new-password" minlength="10" required>
        <p class="hint">At least 10 characters. A short sentence is easy to remember and hard to guess.</p>
        @error('password') <p class="error">{{ $message }}</p> @enderror
      </div>
      <div>
        <label for="password_confirmation">Password again</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
      </div>
      <button class="btn btn-primary btn-block" type="submit">Create account</button>
    </form>

    <p class="foot">Already have an account? <a href="{{ route('login') }}">Log in</a></p>
  </div>
</div>
@endsection
