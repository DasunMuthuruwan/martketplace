{{-- Public layout (navigation doc §2, Q-UX-1/3). Pages: @extends('layouts.public') with
     @section('content') and either $seo (PageSeo) or @section('title'). Data for the header and
     footer comes from App\View\Composers\PublicLayoutComposer. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.layout.head')
    @isset($seo)
        <title>{{ $seo->title }} | {{ config('app.name') }}</title>
        <meta name="robots" content="{{ $seo->robots }}">
        @if ($seo->description)<meta name="description" content="{{ $seo->description }}">@endif
        @if ($seo->canonical)<link rel="canonical" href="{{ $seo->canonical }}">@endif
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:type" content="{{ $seo->ogType }}">
        <meta property="og:title" content="{{ $seo->title }}">
        @if ($seo->description)<meta property="og:description" content="{{ $seo->description }}">@endif
        @if ($seo->canonical)<meta property="og:url" content="{{ $seo->canonical }}">@endif
        @if ($seo->image)
            <meta property="og:image" content="{{ $seo->image }}">
            <meta name="twitter:card" content="summary_large_image">
        @else
            <meta name="twitter:card" content="summary">
        @endif
        @foreach ($seo->jsonLdBlocks() as $json)
            <script type="application/ld+json" nonce="{{ Vite::cspNonce() }}">{!! $json !!}</script>
        @endforeach
    @else
        <meta name="robots" content="{{ $robots ?? \App\Services\Seo\PageSeo::robots(false) }}">
        <title>@yield('title') | {{ config('app.name') }}</title>
    @endisset
    @stack('head')
</head>
{{-- Bottom padding on the body keeps the content and the footer clear of the mobile bottom navigation. --}}
<body class="flex min-h-dvh flex-col pb-[calc(56px+env(safe-area-inset-bottom))] lg:pb-0">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-control focus:bg-primary focus:px-4 focus:py-2 focus:text-primary-fg">{{ __('layout.skip') }}</a>
    @include('partials.layout.header')
    @include('partials.layout.category-bar')

    <main id="main" tabindex="-1" class="legacy mx-auto w-full max-w-(--container-page) flex-1 px-4 pt-6 pb-8 lg:px-6 lg:pb-12">
        @yield('content')
    </main>

    @include('partials.layout.footer')
    {{-- A page can replace the bottom navigation with its own phone bar (listing page: price + contact). --}}
    @hasSection('mobile_bar')
        @yield('mobile_bar')
    @else
        @include('partials.layout.bottom-nav')
    @endif
    <x-ui.toasts />
</body>
</html>
