@extends('layouts.plain')

@section('title', __('auth.ui.confirm_title'))

@section('content')
    <p>{{ __('auth.ui.confirm_intro') }}</p>
    <form method="POST" action="{{ route('password.confirm.store') }}" novalidate>
        @csrf
        <label for="password">{{ __('auth.ui.password') }}</label>
        <input type="password" id="password" name="password" required autofocus autocomplete="current-password">
        @error('password')<p class="error">{{ $message }}</p>@enderror
        <button type="submit">{{ __('auth.ui.confirm') }}</button>
    </form>
@endsection
