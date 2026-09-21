@extends('account.layout')

@section('title', 'Confirm your password')

@section('content')
<div class="wrap narrow">
  <div class="card accent">
    <h1>Confirm your password</h1>
    <p class="lead">This part of your account needs your password once more.</p>

    <form class="stack" method="post" action="{{ route('password.confirm') }}">
      @csrf
      <div>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required autofocus>
        @error('password') <p class="error">{{ $message }}</p> @enderror
      </div>
      <button class="btn btn-primary btn-block" type="submit">Confirm</button>
    </form>
  </div>
</div>
@endsection
