@extends('layouts.auth')

@section('title', __('auth.ui.confirm_title'))

@section('content')
    <p class="mb-4 text-ui text-fg-muted">{{ __('auth.ui.confirm_intro') }}</p>
    <form method="POST" action="{{ route('password.confirm.store') }}" novalidate class="space-y-4">
        @csrf
        <x-ui.input type="password" :label="__('auth.ui.password')" name="password" required autofocus autocomplete="current-password" />
        <x-ui.button type="submit" size="lg" block>{{ __('auth.ui.confirm') }}</x-ui.button>
    </form>
@endsection
