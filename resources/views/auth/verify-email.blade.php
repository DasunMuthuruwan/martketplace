@extends('layouts.plain')

@section('title', __('auth.ui.verify_title'))

@section('content')
    <p>{{ __('auth.ui.verify_intro') }}</p>
    @if (session('status') === 'verification-link-sent')
        <p class="status" role="status">{{ __('auth.ui.verify_resent') }}</p>
    @endif
    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button type="submit">{{ __('auth.ui.resend') }}</button>
    </form>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">{{ __('auth.ui.logout') }}</button>
    </form>
@endsection
