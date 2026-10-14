@extends('layouts.auth')

@section('title', __('auth.ui.verify_title'))

@section('content')
    <div class="space-y-4">
        <span class="grid size-12 place-items-center rounded-full bg-surface-muted text-fg"><x-ui.icon name="badge-check" size="24" /></span>
        <p class="text-ui text-fg-muted">{{ __('auth.ui.verify_intro') }}</p>
        @if (session('status') === 'verification-link-sent')
            <x-ui.alert variant="success">{{ __('auth.ui.verify_resent') }}</x-ui.alert>
        @endif
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-ui.button type="submit" size="lg" block>{{ __('auth.ui.resend') }}</x-ui.button>
        </form>
        <form method="POST" action="{{ route('logout') }}" class="text-center">
            @csrf
            <x-ui.button type="submit" variant="link">{{ __('auth.ui.logout') }}</x-ui.button>
        </form>
    </div>
@endsection
