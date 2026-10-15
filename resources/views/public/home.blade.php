@extends('layouts.public')

@section('title', __('public.choose_country_title'))

@section('content')
    {{-- `/` when the visitor's country is unknown (global UX §1): a country grid, then the latest ads
         from every country (prices with their currency code, places with the country). --}}
    <section class="mb-10">
        <h1 class="text-h1 font-[650]">{{ __('public.choose_country_title') }}</h1>
        <p class="mt-1 text-body text-fg-muted">{{ __('public.home.choose_help') }}</p>
        @error('country')<x-ui.alert variant="danger" class="mt-4">{{ $message }}</x-ui.alert>@enderror

        @if ($countries->isEmpty())
            <x-ui.empty-state class="mt-6" icon="globe" :title="__('public.home.no_countries')" />
        @else
            <form method="POST" action="{{ route('country.choose') }}" class="mt-5">
                @csrf
                <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($countries as $country)
                        <li>
                            <button type="submit" name="country" value="{{ strtolower($country->iso2) }}"
                                class="flex w-full items-center gap-3 rounded-card border border-line bg-surface p-3 text-left text-ui font-medium text-fg transition-colors duration-(--duration-fast) hover:border-control">
                                <x-ui.flag :code="$country->iso2" class="text-xl" />
                                <span class="min-w-0 flex-1 truncate">{{ $country->name }}</span>
                                <x-ui.icon name="chevron-right" size="16" class="text-fg-subtle" />
                            </button>
                        </li>
                    @endforeach
                </ul>
            </form>
        @endif
    </section>

    @if ($latest->isNotEmpty())
        <section aria-labelledby="home-latest" class="mb-10">
            <h2 id="home-latest" class="mb-3 text-h2 font-semibold">{{ __('public.home.latest_everywhere') }}</h2>
            <div class="listing-grid-area">
                <ul class="listing-grid">
                    @foreach ($latest as $listing)
                        <li><x-listing.card :listing="$listing" :present="$present" :with-code="true" class="h-full" :eager="$loop->first ? 'high' : $loop->index < 4" /></li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @include('public.partials.safety-strip')
@endsection
