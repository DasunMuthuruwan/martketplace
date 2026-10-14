{{-- Public header (navigation doc §2): sticky, 64 px desktop / 56 px mobile. Desktop: wordmark,
     search, country, Post ad, Saved, account. Mobile: wordmark, search, account; the other actions
     live in the bottom navigation. --}}
@php
    $searchAction = $currentCountry ? route('ads.country', ['cc' => strtolower($currentCountry->iso2)]) : route('ads.global');
    $postUrl = \App\View\Navigation::url('post');
    $savedUrl = \App\View\Navigation::url('saved');
@endphp
<header class="sticky top-0 z-30 border-b border-line bg-surface/95 backdrop-blur supports-[backdrop-filter]:bg-surface/85">
    <div class="mx-auto flex h-14 max-w-(--container-page) items-center gap-3 px-4 lg:h-16 lg:px-6">
        @include('partials.layout.brand')

        <form method="GET" action="{{ $searchAction }}" role="search" class="ml-4 hidden max-w-xl flex-1 lg:flex">
            <label for="header-q" class="sr-only">{{ __('layout.search_label') }}</label>
            <input id="header-q" type="search" name="q" value="{{ request('q') }}" maxlength="100" placeholder="{{ __('layout.search_placeholder') }}"
                class="h-10 min-w-0 flex-1 rounded-l-control border border-r-0 border-control bg-surface px-3 text-fg placeholder:text-fg-subtle">
            <button type="submit" class="grid h-10 w-11 place-items-center rounded-r-control bg-primary text-primary-fg hover:bg-primary-hover" aria-label="{{ __('layout.search') }}">
                <x-ui.icon name="search" size="20" />
            </button>
        </form>

        <div class="ml-auto flex items-center gap-1">
            <a href="{{ $searchAction }}" class="grid size-10 place-items-center rounded-control text-fg hover:bg-surface-hover lg:hidden" aria-label="{{ __('layout.search') }}">
                <x-ui.icon name="search" size="20" />
            </a>

            @include('partials.layout.country-switcher')

            <div class="hidden items-center gap-1 lg:flex">
                @if ($savedUrl)
                    <x-ui.button variant="ghost" icon="heart" :href="$savedUrl" :label="__('layout.saved')" />
                @endif
                @if ($postUrl)
                    <x-ui.button :href="$postUrl" icon="plus">{{ __('layout.post_ad') }}</x-ui.button>
                @endif
            </div>

            @include('partials.layout.account-menu')
        </div>
    </div>
</header>
