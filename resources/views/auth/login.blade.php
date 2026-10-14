@extends('layouts.auth')

@section('title', __('auth.ui.login_title'))

@section('content')
    <form method="POST" action="{{ route('login.store') }}" novalidate class="space-y-4">
        @csrf
        <x-ui.input type="email" :label="__('auth.ui.email')" name="email" :value="old('email')" required autofocus autocomplete="username" maxlength="191" />
        <x-ui.input type="password" :label="__('auth.ui.password')" name="password" required autocomplete="current-password" />
        <div class="flex flex-wrap items-center justify-between gap-2">
            <x-ui.checkbox :label="__('auth.ui.remember')" name="remember" />
            <a href="{{ route('password.request') }}" class="text-ui font-medium text-fg underline underline-offset-2">{{ __('auth.ui.forgot_link') }}</a>
        </div>
        <x-ui.button type="submit" size="lg" block>{{ __('auth.ui.login') }}</x-ui.button>
    </form>

    <p class="mt-6 border-t border-line pt-4 text-center text-ui text-fg-muted">
        {{ __('auth.ui.no_account') }} <a href="{{ route('register') }}" class="font-medium text-fg underline underline-offset-2">{{ __('auth.ui.register_title') }}</a>
    </p>
@endsection
