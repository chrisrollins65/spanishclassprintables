@extends('account.layout')

@section('title', 'Account')

@section('content')
<div class="wrap narrow">
  <div class="card accent">
    <h1>Your account</h1>

    @if (session('status') === 'profile-information-updated')
      <p class="notice">Saved.</p>
    @endif

    {{-- Fortify puts each form's errors in its own bag, so a mistake in one
         form never shows up under the other. --}}
    <form class="stack" method="post" action="{{ route('user-profile-information.update') }}">
      @csrf
      @method('PUT')
      <div>
        <label for="name">Your name</label>
        <input id="name" name="name" type="text" value="{{ old('name', auth()->user()->name) }}" autocomplete="name" maxlength="255" required>
        @error('name', 'updateProfileInformation') <p class="error">{{ $message }}</p> @enderror
      </div>
      <div>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email', auth()->user()->email) }}" autocomplete="email" maxlength="255" required>
        <p class="hint">Changing it sends a new confirmation link to the new address.</p>
        @error('email', 'updateProfileInformation') <p class="error">{{ $message }}</p> @enderror
      </div>
      <button class="btn btn-primary" type="submit">Save</button>
    </form>
  </div>

  <div class="card">
    <h2>Change your password</h2>

    @if (session('status') === 'password-updated')
      <p class="notice">Password changed.</p>
    @endif

    <form class="stack" method="post" action="{{ route('user-password.update') }}">
      @csrf
      @method('PUT')
      <div>
        <label for="current_password">Current password</label>
        <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
        @error('current_password', 'updatePassword') <p class="error">{{ $message }}</p> @enderror
      </div>
      <div>
        <label for="password">New password</label>
        <input id="password" name="password" type="password" autocomplete="new-password" minlength="10" required>
        @error('password', 'updatePassword') <p class="error">{{ $message }}</p> @enderror
      </div>
      <div>
        <label for="password_confirmation">New password again</label>
        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
      </div>
      <button class="btn btn-primary" type="submit">Change password</button>
    </form>
  </div>
</div>
@endsection
