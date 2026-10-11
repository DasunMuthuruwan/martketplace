@extends('layouts.plain')

@section('title', __('auth.ui.forgot_title'))

@section('content')
    <p>{{ __('auth.ui.forgot_intro') }}</p>
    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf
        <label for="email">{{ __('auth.ui.email') }}</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
        @error('email')<p class="error" id="email-error">{{ $message }}</p>@enderror
        <button type="submit">{{ __('auth.ui.send_link') }}</button>
    </form>
    <p><a href="{{ route('login') }}">{{ __('auth.ui.back_to_login') }}</a></p>
@endsection
