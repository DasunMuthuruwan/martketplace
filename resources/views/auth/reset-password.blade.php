@extends('layouts.plain')

@section('title', __('auth.ui.reset_title'))

@section('content')
    <form method="POST" action="{{ route('password.update') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <label for="email">{{ __('auth.ui.email') }}</label>
        <input type="email" id="email" name="email" value="{{ old('email', $request->query('email')) }}" required autocomplete="username">
        @error('email')<p class="error">{{ $message }}</p>@enderror

        <label for="password">{{ __('auth.ui.password') }}</label>
        <input type="password" id="password" name="password" required autocomplete="new-password" aria-describedby="password-hint">
        <p class="hint" id="password-hint">{{ __('auth.ui.password_hint') }}</p>
        @error('password')<p class="error">{{ $message }}</p>@enderror

        <label for="password_confirmation">{{ __('auth.ui.password_confirmation') }}</label>
        <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">

        <button type="submit">{{ __('auth.ui.reset') }}</button>
    </form>
@endsection
