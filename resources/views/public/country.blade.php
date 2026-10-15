@extends('layouts.public')

@section('title', $country->name)

@section('content')
    @php($cc = strtolower($country->iso2))

    {{-- Hero (layout doc §3.1). Phones get a search box here: the header only has a search icon. --}}
    <section class="mb-8 rounded-card border border-line bg-surface px-5 py-7 sm:px-8 sm:py-10">
        <h1 class="max-w-2xl text-h1 font-[650] sm:text-display">{{ __('public.home.hero', ['country' => $country->name]) }}</h1>
        <p class="mt-2 max-w-2xl text-body text-fg-muted">{{ __('public.home.subline') }}</p>
        <form method="GET" action="{{ route('ads.country', ['cc' => $cc]) }}" role="search" class="mt-5 flex max-w-xl lg:hidden">
            <label for="home-q" class="sr-only">{{ __('layout.search_label') }}</label>
            <input id="home-q" type="search" name="q" maxlength="100" placeholder="{{ __('layout.search_placeholder') }}"
                class="h-11 min-w-0 flex-1 rounded-l-control border border-r-0 border-control bg-surface px-3 text-fg placeholder:text-fg-subtle">
            <button type="submit" class="grid h-11 w-12 place-items-center rounded-r-control bg-primary text-primary-fg" aria-label="{{ __('layout.search') }}"><x-ui.icon name="search" size="20" /></button>
        </form>
        @if ($postUrl = \App\View\Navigation::url('post'))
            <x-ui.button :href="$postUrl" icon="plus" size="lg" class="mt-5 hidden lg:inline-flex">{{ __('public.home.post_cta') }}</x-ui.button>
        @endif
    </section>

    @if ($categories->isNotEmpty())
        <section aria-labelledby="home-categories" class="mb-10">
            <h2 id="home-categories" class="mb-3 text-h2 font-semibold">{{ __('public.home.browse') }}</h2>
            <ul class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                @foreach ($categories as $category)
                    <li><x-category.tile :category="$category" :count="$categoryCounts[$category->id] ?? 0" :href="route('ads.category', ['cc' => $cc, 'category' => $category->slug])" class="h-full" /></li>
                @endforeach
            </ul>
        </section>
    @endif

    <section aria-labelledby="home-latest" class="mb-10">
        <div class="mb-3 flex items-baseline justify-between gap-4">
            <h2 id="home-latest" class="text-h2 font-semibold">{{ __('public.latest') }}</h2>
            @if ($latest->isNotEmpty())
                <a href="{{ route('ads.country', ['cc' => $cc]) }}" class="inline-flex items-center gap-1 text-ui text-fg-muted hover:text-fg">{{ __('public.home.see_all') }}<x-ui.icon name="chevron-right" size="16" /></a>
            @endif
        </div>
        @if ($latest->isEmpty())
            <x-ui.empty-state icon="package" :title="__('public.home.empty_title')">
                {{ __('public.home.empty_text', ['country' => $country->name]) }}
                @if ($postUrl = \App\View\Navigation::url('post'))
                    <x-slot:action><x-ui.button :href="$postUrl" icon="plus">{{ __('public.home.post_cta') }}</x-ui.button></x-slot:action>
                @endif
            </x-ui.empty-state>
        @else
            <div class="listing-grid-area">
                <ul class="listing-grid">
                    @foreach ($latest as $listing)
                        <li><x-listing.card :listing="$listing" :present="$present" class="h-full" :eager="$loop->first ? 'high' : $loop->index < 4" /></li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    @include('public.partials.safety-strip')
@endsection
