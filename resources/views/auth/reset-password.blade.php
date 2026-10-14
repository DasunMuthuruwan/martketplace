@extends('layouts.auth')

@section('title', __('auth.ui.reset_title'))

@section('content')
    <form method="POST" action="{{ route('password.update') }}" novalidate class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-ui.input type="email" :label="__('auth.ui.email')" name="email" :value="old('email', $request->query('email'))" required autocomplete="username" maxlength="191" />
        <x-ui.input type="password" :label="__('auth.ui.password')" name="password" :hint="__('auth.ui.password_hint')" required autofocus autocomplete="new-password" />
        <x-ui.input type="password" :label="__('auth.ui.password_confirmation')" name="password_confirmation" required autocomplete="new-password" />
        <x-ui.button type="submit" size="lg" block>{{ __('auth.ui.reset') }}</x-ui.button>
    </form>
@endsection
