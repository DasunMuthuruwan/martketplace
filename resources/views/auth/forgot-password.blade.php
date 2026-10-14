@extends('layouts.auth')

@section('title', __('auth.ui.forgot_title'))

@section('content')
    <p class="mb-4 text-ui text-fg-muted">{{ __('auth.ui.forgot_intro') }}</p>
    <form method="POST" action="{{ route('password.email') }}" novalidate class="space-y-4">
        @csrf
        <x-ui.input type="email" :label="__('auth.ui.email')" name="email" :value="old('email')" required autofocus autocomplete="email" maxlength="191" />
        <x-ui.button type="submit" size="lg" block>{{ __('auth.ui.send_link') }}</x-ui.button>
    </form>
    <p class="mt-6 border-t border-line pt-4 text-center text-ui">
        <a href="{{ route('login') }}" class="font-medium text-fg underline underline-offset-2">{{ __('auth.ui.back_to_login') }}</a>
    </p>
@endsection
