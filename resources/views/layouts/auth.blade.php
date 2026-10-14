{{-- Sign-in screens (layout doc §3.7): a centred card (max 420 px) on the canvas with the wordmark
     above it; no header search or bottom navigation. Pages: @extends('layouts.auth'),
     @section('title'), @section('content'). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.layout.head')
    <meta name="robots" content="noindex">
    <title>@yield('title') | {{ config('app.name') }}</title>
    @stack('head')
</head>
<body class="flex min-h-dvh flex-col items-center px-4 py-8 sm:py-12">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-control focus:bg-primary focus:px-4 focus:py-2 focus:text-primary-fg">{{ __('layout.skip') }}</a>
    <div class="mb-6">@include('partials.layout.brand')</div>
    <main id="main" tabindex="-1" class="legacy w-full max-w-[420px] rounded-card border border-line bg-surface p-6 sm:p-8">
        <h1 class="mb-4 text-h1 font-semibold">@yield('title')</h1>
        @if (session('status'))
            <x-ui.alert variant="success" class="mb-4">{{ trans()->has('account.status.'.session('status')) ? __('account.status.'.session('status')) : session('status') }}</x-ui.alert>
        @endif
        @yield('content')
    </main>
    <p class="mt-6 flex items-center gap-3 text-small text-fg-subtle">
        <a href="{{ route('home') }}" class="hover:text-fg">{{ __('layout.back_to_site') }}</a>
        @include('partials.layout.theme-toggle')
    </p>
</body>
</html>
