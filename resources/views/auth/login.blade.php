@extends('layouts.plain')

@section('title', __('auth.ui.login_title'))

@section('content')
    <form method="POST" action="{{ route('login.store') }}" novalidate>
        @csrf

        <label for="email">{{ __('auth.ui.email') }}</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
        @error('email')<p class="error" id="email-error" role="alert">{{ $message }}</p>@enderror

        <label for="password">{{ __('auth.ui.password') }}</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
        @error('password')<p class="error">{{ $message }}</p>@enderror

        <label><input type="checkbox" name="remember" value="1"> {{ __('auth.ui.remember') }}</label>

        <button type="submit">{{ __('auth.ui.login') }}</button>
    </form>

    <p><a href="{{ route('password.request') }}">{{ __('auth.ui.forgot_link') }}</a></p>
    <p>{{ __('auth.ui.no_account') }} <a href="{{ route('register') }}">{{ __('auth.ui.register_title') }}</a></p>
@endsection
