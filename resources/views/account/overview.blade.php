@extends('layouts.account')

@section('title', __('account.overview.title', ['name' => $user->name]))

@section('account_full')
    @unless ($user->hasVerifiedEmail())
        <x-ui.alert variant="warning" class="mb-4" :title="__('account.overview.verify_title')">
            {{ __('account.overview.verify_text') }}
            <x-slot:actions><x-ui.button size="sm" variant="secondary" :href="route('verification.notice')">{{ __('account.overview.verify_action') }}</x-ui.button></x-slot:actions>
        </x-ui.alert>
    @endunless

    <ul class="mb-8 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach (['active' => 'circle-check', 'pending' => 'clock', 'drafts' => 'pencil'] as $tab => $icon)
            <li>
                <a href="{{ route('account.listings', ['status' => $tab]) }}" class="flex h-full flex-col gap-2 rounded-card border border-line bg-surface p-4 hover:border-control">
                    <x-ui.icon :name="$icon" size="20" class="text-fg-muted" />
                    <span class="tabular text-h2 font-semibold text-fg">{{ number_format($counts[$tab]) }}</span>
                    <span class="text-small text-fg-muted">{{ __('account.listings.tab.'.$tab) }}</span>
                </a>
            </li>
        @endforeach
        <li>
            <a href="{{ route('account.favourites') }}" class="flex h-full flex-col gap-2 rounded-card border border-line bg-surface p-4 hover:border-control">
                <x-ui.icon name="heart" size="20" class="text-fg-muted" />
                <span class="tabular text-h2 font-semibold text-fg">{{ number_format($saved) }}</span>
                <span class="text-small text-fg-muted">{{ __('layout.saved') }}</span>
            </a>
        </li>
    </ul>

    @if ($counts['rejected'] > 0 || $counts['expired'] > 0)
        <x-ui.alert variant="warning" class="mb-6">
            {{ __('account.overview.attention', ['rejected' => $counts['rejected'], 'expired' => $counts['expired']]) }}
            <x-slot:actions>
                @if ($counts['rejected'] > 0)<x-ui.button size="sm" variant="secondary" :href="route('account.listings', ['status' => 'rejected'])">{{ __('account.listings.tab.rejected') }}</x-ui.button>@endif
                @if ($counts['expired'] > 0)<x-ui.button size="sm" variant="secondary" :href="route('account.listings', ['status' => 'expired'])">{{ __('account.listings.tab.expired') }}</x-ui.button>@endif
            </x-slot:actions>
        </x-ui.alert>
    @endif

    <section aria-labelledby="expiring" class="mb-8">
        <h2 id="expiring" class="mb-3 text-h2 font-semibold">{{ __('account.overview.expiring') }}</h2>
        @if ($expiring->isEmpty())
            <p class="text-ui text-fg-muted">{{ __('account.overview.nothing_expiring') }}</p>
        @else
            <ul class="space-y-3">
                @foreach ($expiring as $listing)
                    <li><x-listing.owner-row :listing="$listing" :present="$present" :lifecycle="app(\App\Actions\Listings\ListingLifecycle::class)" /></li>
                @endforeach
            </ul>
        @endif
    </section>

    <section aria-labelledby="shortcuts">
        <h2 id="shortcuts" class="mb-3 text-h2 font-semibold">{{ __('account.overview.shortcuts') }}</h2>
        <div class="flex flex-wrap gap-2">
            @if ($postUrl = \App\View\Navigation::url('post'))<x-ui.button :href="$postUrl" icon="plus">{{ __('layout.post_ad') }}</x-ui.button>@endif
            <x-ui.button variant="secondary" :href="route('account.listings')" icon="list">{{ __('layout.my_ads') }}</x-ui.button>
            <x-ui.button variant="secondary" :href="route('account.settings.contact')" icon="phone">{{ __('account.nav.contact') }}</x-ui.button>
            <x-ui.button variant="secondary" :href="route('account.settings.security')" icon="shield-check">{{ __('account.nav.security') }}</x-ui.button>
        </div>
    </section>
@endsection
