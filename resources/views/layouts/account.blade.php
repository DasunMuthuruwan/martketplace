{{-- Account area (navigation doc §2, layout §3.5): the public layout plus a side menu on desktop and
     a tab row on phones. Pages: @extends('layouts.account'), @section('title') and either
     @section('account') (a form in a card, settings) or @section('account_full') (lists). --}}
@extends('layouts.public')

@php
    $accountLinks = collect([
        'overview' => [__('account.nav.overview'), \App\View\Navigation::url('account')],
        'my_ads' => [__('layout.my_ads'), \App\View\Navigation::url('my_ads')],
        'saved' => [__('layout.saved'), \App\View\Navigation::url('saved')],
        'reviews' => [__('reviews.mine_title'), auth()->user()?->hasVerifiedEmail() ? route('account.reviews') : null],
        'profile' => [__('account.nav.profile'), route('account.settings.profile')],
        'contact' => [__('account.nav.contact'), route('account.settings.contact')],
        'security' => [__('account.nav.security'), route('account.settings.security')],
        // Promotion orders (B5): once selling is on, or the account has orders.
        'orders' => [__('boosts.order.orders'), (config('marketplace.boosts.sales_enabled') || \App\Models\BoostOrder::where('user_id', auth()->id())->exists()) ? route('account.orders') : null],
    ])->filter(fn ($item) => $item[1] !== null)->map(fn ($item) => ['label' => $item[0], 'href' => $item[1]]);
    // The overview URL falls back to the profile page when there is no overview: keep one entry per URL.
    $accountLinks = $accountLinks->unique('href');
    $current = rtrim(request()->url(), '/');
    $activeKey = $accountLinks->search(fn ($item) => rtrim($item['href'], '/') === $current)
        ?: (request()->routeIs('account.two-factor') ? 'security' : (request()->routeIs('account.listings.stats', 'account.listings.promote') ? 'my_ads' : (request()->routeIs('account.orders.show') ? 'orders' : null)));
@endphp

@section('content')
    <div class="grid gap-6 lg:grid-cols-[232px_minmax(0,1fr)]">
        <nav aria-label="{{ __('account.nav.label') }}" class="hidden lg:block">
            <ul class="sticky top-24 space-y-1">
                @foreach ($accountLinks as $key => $item)
                    <li><a href="{{ $item['href'] }}" @if ($key === $activeKey) aria-current="page" @endif
                        class="block rounded-control px-3 py-2 text-ui text-fg-muted hover:bg-surface-hover hover:text-fg aria-[current]:bg-surface-muted aria-[current]:font-semibold aria-[current]:text-fg">{{ $item['label'] }}</a></li>
                @endforeach
            </ul>
        </nav>
        <div class="min-w-0">
            <x-ui.tabs class="mb-6 lg:hidden" :label="__('account.nav.label')" :items="$accountLinks->all()" :active="$activeKey" />
            @hasSection('account_full')
                <h1 class="mb-4 text-h1 font-semibold">@yield('title')</h1>
                @yield('account_full')
            @else
                <div class="max-w-2xl rounded-card border border-line bg-surface p-5 sm:p-6">
                    <h1 class="mb-4 text-h1 font-semibold">@yield('title')</h1>
                    @yield('account')
                </div>
            @endif
        </div>
    </div>
@endsection
