{{-- Plain public layout for Phase 5 pages. Replaced by the designed layouts in Phase 6 (F1.3). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
    <style nonce="{{ Vite::cspNonce() }}">
        body { margin: 0; font-family: system-ui, sans-serif; background: #f7f7f7; color: #0a0a0a; }
        header, main, footer { max-width: 72rem; margin: 0 auto; padding: 1rem; }
        header { display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }
        main { background: #fff; border: 1px solid #e5e5e5; border-radius: 10px; }
        a { color: #0a0a0a; }
        ul.cards { list-style: none; padding: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr)); gap: 1rem; }
        ul.cards li { border: 1px solid #e5e5e5; border-radius: 8px; padding: .75rem; }
        ul.cards img { width: 100%; height: auto; border-radius: 6px; }
        .muted { color: #4d4d4d; font-size: .875rem; }
        .status { background: #f0f0f0; padding: .75rem; border-radius: 6px; }
        form.filters { display: flex; gap: .5rem; flex-wrap: wrap; align-items: end; }
        form.filters label { display: flex; flex-direction: column; font-size: .875rem; }
        input, select, button { font: inherit; padding: .4rem; }
        :focus-visible { outline: 2px solid #0a0a0a; outline-offset: 2px; }
    </style>
    @stack('head')
</head>
<body>
    <header>
        <a href="{{ route('home') }}"><strong>{{ config('app.name') }}</strong></a>
        @isset($currentCountry)
            <a href="{{ route('country.home', ['cc' => strtolower($currentCountry->iso2)]) }}">{{ $currentCountry->name }}</a>
        @endisset
        <a href="{{ route('countries') }}">{{ __('public.all_countries') }}</a>
        <a href="{{ route('ads.global') }}">{{ __('public.search_all') }}</a>
        @auth
            <a href="{{ route('account.settings.profile') }}">{{ __('public.my_account') }}</a>
        @else
            <a href="{{ route('login') }}">{{ __('auth.ui.login') }}</a>
        @endauth
    </header>
    <main>
        @if (session('status'))
            <p class="status" role="status">{{ session('status') }}</p>
        @endif
        @yield('content')
    </main>
    <footer class="muted">
        @foreach (\App\Models\Page::footerLinks() as $pageSlug => $pageTitle)
            <a href="{{ route('page.show', $pageSlug) }}">{{ $pageTitle }}</a>
        @endforeach
        <p>{{ config('marketplace.geo.attribution') }}</p>
    </footer>
</body>
</html>
