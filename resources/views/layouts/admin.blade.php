{{-- Admin layout (layout doc §3.6): top bar, grouped sidebar on desktop, drawer on phones and
     tablets. Used by the Livewire admin screens (#[Layout('layouts.admin')], $slot, $title). Livewire
     injects its CSP-safe scripts with the request nonce. --}}
@php
    $adminBadges = [];
    try {
        if (auth()->user()?->can(\App\Authorization\Permissions::LISTINGS_MODERATE)) {
            $adminBadges['pending'] = \App\Models\Listing::where('status', \App\Enums\ListingStatus::PendingReview)->count() ?: null;
        }
        if (auth()->user()?->can(\App\Authorization\Permissions::REPORTS_VIEW)) {
            $adminBadges['reports'] = \App\Models\Report::where('status', \App\Enums\ReportStatus::Open)->count() ?: null;
        }
        if (auth()->user()?->can(\App\Authorization\Permissions::REVIEWS_MODERATE)) {
            $adminBadges['reviews'] = \App\Models\SellerReview::where('status', 'published')
                ->where(fn ($q) => $q->where('comment_status', 'pending')->orWhereNotNull('reported_at'))->count() ?: null;
        }
        if (auth()->user()?->can(\App\Authorization\Permissions::BOOSTS_MANAGE)) {
            $adminBadges['orders'] = \App\Models\BoostOrder::where('status', \App\Enums\BoostOrderStatus::Pending)->count() ?: null;
        }
    } catch (\Throwable $e) {
        report($e);
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.layout.head')
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title ?? __('admin.title') }} | {{ config('app.name') }}</title>
</head>
<body class="min-h-dvh">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-control focus:bg-primary focus:px-4 focus:py-2 focus:text-primary-fg">{{ __('layout.skip') }}</a>
    <header class="sticky top-0 z-30 border-b border-line bg-surface">
        <div class="flex h-14 items-center gap-2 px-4 sm:gap-3 lg:px-6">
            <x-ui.button variant="ghost" icon="menu" :label="__('admin.menu')" class="lg:hidden" data-modal-open="admin-menu" />
            @include('partials.layout.brand', ['href' => route('admin.dashboard')])
            <x-ui.badge variant="outline" class="max-sm:hidden">{{ __('admin.title') }}</x-ui.badge>
            {{-- Narrow screens: "View site" shows its icon only and the theme choice moves into the menu drawer (WCAG 1.4.10). --}}
            <div class="ml-auto flex shrink-0 items-center gap-2">
                <x-ui.button variant="ghost" size="sm" :href="route('home')" icon="external-link"><span class="max-sm:sr-only">{{ __('admin.nav.site') }}</span></x-ui.button>
                <div class="max-sm:hidden">@include('partials.layout.theme-toggle')</div>
            </div>
        </div>
    </header>
    <div class="min-h-[calc(100dvh-3.5rem)] lg:grid lg:grid-cols-[248px_minmax(0,1fr)]">
        <aside class="hidden border-r border-line bg-surface px-3 py-5 lg:block">
            <div class="sticky top-20">@include('partials.layout.admin-nav')</div>
        </aside>
        <main id="main" tabindex="-1" class="legacy min-w-0 px-4 py-6 lg:px-8">
            {{ $slot }}
        </main>
    </div>
    <x-ui.drawer id="admin-menu" :title="__('admin.title')" side="left">
        @include('partials.layout.admin-nav')
        <div class="mt-6 flex items-center justify-between border-t border-line px-3 pt-4 sm:hidden">
            <span class="text-small text-fg-subtle">{{ __('ui.theme.label') }}</span>
            @include('partials.layout.theme-toggle')
        </div>
    </x-ui.drawer>
    <x-ui.toasts />
</body>
</html>
