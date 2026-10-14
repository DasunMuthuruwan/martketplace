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
        <div class="flex h-14 items-center gap-3 px-4 lg:px-6">
            <x-ui.button variant="ghost" icon="menu" :label="__('admin.menu')" class="lg:hidden" data-modal-open="admin-menu" />
            @include('partials.layout.brand', ['href' => route('admin.dashboard')])
            <x-ui.badge variant="outline">{{ __('admin.title') }}</x-ui.badge>
            <div class="ml-auto flex items-center gap-2">
                <x-ui.button variant="ghost" size="sm" :href="route('home')" icon="external-link">{{ __('admin.nav.site') }}</x-ui.button>
                @include('partials.layout.theme-toggle')
            </div>
        </div>
    </header>
    <div class="lg:grid lg:grid-cols-[248px_minmax(0,1fr)]">
        <aside class="hidden border-r border-line bg-surface px-3 py-5 lg:block">
            <div class="sticky top-20">@include('partials.layout.admin-nav')</div>
        </aside>
        <main id="main" tabindex="-1" class="legacy min-w-0 px-4 py-6 lg:px-8">
            {{ $slot }}
        </main>
    </div>
    <x-ui.drawer id="admin-menu" :title="__('admin.title')" side="left">
        @include('partials.layout.admin-nav')
    </x-ui.drawer>
    <x-ui.toasts />
</body>
</html>
