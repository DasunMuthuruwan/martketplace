@extends('layouts.public')

@section('title', $listing->title.' – '.$present->location($listing, true))

@php
    $cc = strtolower($listing->country->iso2);
    $category = $listing->category;
    $price = $present->priceParts($listing);
    $unit = $present->unitPrice($listing);
    $images = $listing->images;
    $seller = $listing->user;
    $categoryUrl = $category->parent
        ? route('ads.category', ['cc' => $cc, 'category' => $category->parent->slug, 'subcategory' => $category->slug])
        : route('ads.category', ['cc' => $cc, 'category' => $category->slug]);
    $contactUrl = route('listings.contact', $listing);
    $hasCall = isset($buttons['call']);
    $hasWhatsapp = isset($buttons['whatsapp']);
    // Key facts: the category's "on the card" attributes (the condition is already in Details).
    $facts = $present->cardFacts($listing, 5);
@endphp

@section('content')
    @if ($ownerView ?? false)<x-listing.status-banner :listing="$listing" />@endif

    <nav aria-label="{{ __('search.breadcrumbs') }}" class="mb-3 text-small text-fg-subtle">
        <ol class="flex flex-wrap items-center gap-1">
            <li><a class="hover:text-fg" href="{{ route('country.home', ['cc' => $cc]) }}">{{ $listing->country->name }}</a></li>
            @if ($category->parent)
                <li aria-hidden="true">›</li>
                <li><a class="hover:text-fg" href="{{ route('ads.category', ['cc' => $cc, 'category' => $category->parent->slug]) }}">{{ $category->parent->name }}</a></li>
            @endif
            <li aria-hidden="true">›</li>
            <li><a class="hover:text-fg" href="{{ $categoryUrl }}">{{ $category->name }}</a></li>
        </ol>
    </nav>

    {{-- One column on phones (gallery, panel, details); on desktop the panel is a sticky right column. --}}
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-start lg:gap-x-8">
        <div class="min-w-0 lg:col-start-1 lg:row-start-1">
            @include('public.partials.listing-gallery')
        </div>

        <aside class="lg:sticky lg:top-20 lg:col-start-2 lg:row-span-2 lg:row-start-1" aria-label="{{ __('public.contact') }}">
            <div class="space-y-4">
                <div class="space-y-4 rounded-card border border-line bg-surface p-5">
                    @include('public.partials.listing-price')
                    @include('public.partials.listing-contact')
                </div>
                @include('public.partials.listing-seller')
                <div class="flex flex-wrap gap-2">@include('public.partials.listing-actions')</div>
            </div>
        </aside>

        <div class="min-w-0 space-y-6 lg:col-start-1 lg:row-start-2">
            @if ($facts !== [])
                <ul class="flex flex-wrap gap-2" aria-label="{{ __('listings.detail.key_facts') }}">
                    @foreach ($facts as $fact)<li><x-ui.chip>{{ $fact }}</x-ui.chip></li>@endforeach
                </ul>
            @endif

            @if ($attributes !== [] || $listing->condition)
                <section aria-labelledby="ad-details" class="rounded-card border border-line bg-surface p-5">
                    <h2 id="ad-details" class="mb-3 text-h2 font-semibold">{{ __('public.details') }}</h2>
                    <dl class="grid gap-x-6 text-ui sm:grid-cols-2">
                        @if ($listing->condition)
                            <div class="flex justify-between gap-4 border-b border-line py-2"><dt class="text-fg-muted">{{ __('public.condition') }}</dt><dd class="text-right font-medium text-fg">{{ __('public.conditions.'.$listing->condition->value) }}</dd></div>
                        @endif
                        @foreach ($attributes as $label => $value)
                            <div class="flex justify-between gap-4 border-b border-line py-2"><dt class="text-fg-muted">{{ $label }}</dt><dd class="prose-user text-right font-medium text-fg">{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                </section>
            @endif

            <section aria-labelledby="ad-description" class="rounded-card border border-line bg-surface p-5">
                <h2 id="ad-description" class="mb-3 text-h2 font-semibold">{{ __('public.description') }}</h2>
                <div class="prose-user whitespace-pre-line text-body text-fg" data-clamp>{{ $listing->description }}</div>
                <button type="button" hidden data-clamp-toggle aria-expanded="false" data-label-more="{{ __('listings.detail.show_more') }}" data-label-less="{{ __('listings.detail.show_less') }}"
                    class="mt-2 text-ui font-medium text-fg underline underline-offset-2">{{ __('listings.detail.show_more') }}</button>
            </section>

            <section aria-labelledby="ad-location" class="rounded-card border border-line bg-surface p-5">
                <h2 id="ad-location" class="mb-2 text-h2 font-semibold">{{ __('listings.detail.location') }}</h2>
                <p class="flex items-center gap-2 text-ui text-fg"><x-ui.icon name="map-pin" size="16" class="text-fg-muted" />{{ $present->location($listing, true) }}</p>
            </section>

            @include('public.partials.listing-safety')
        </div>
    </div>

    @if (($makePage ?? null) && $listing->isPublic())
        <p class="mt-8"><a href="{{ $makePage['url'] }}" class="inline-flex items-center gap-1 text-ui font-medium text-fg underline-offset-2 hover:underline" data-make-page>{{ __('public.make_page.more', ['what' => $makePage['name']]) }}<x-ui.icon name="chevron-right" size="16" /></a></p>
    @endif

    @if ($similar->isNotEmpty())
        <section aria-labelledby="ad-similar" class="mt-10">
            <h2 id="ad-similar" class="mb-3 text-h2 font-semibold">{{ __('public.similar') }}</h2>
            <div class="listing-grid-area">
                <ul class="listing-grid">
                    @foreach ($similar as $item)
                        <li><x-listing.card :listing="$item" :present="$present" class="h-full" /></li>
                    @endforeach
                </ul>
            </div>
        </section>
    @endif

    @include('public.partials.listing-report')
@endsection

{{-- Phones: price + contact instead of the bottom navigation (components §3.1, §5.2). --}}
@section('mobile_bar')
    <div class="fixed inset-x-0 bottom-0 z-30 flex items-center gap-2 border-t border-line bg-surface px-4 pt-2 pb-[calc(8px+env(safe-area-inset-bottom))] lg:hidden">
        <p class="min-w-0 flex-1 truncate tabular text-price font-bold text-fg">{{ $price['amount'] }}</p>
        @if ($buttons === [])
            <span class="text-small text-fg-subtle">{{ __('listings.detail.no_contact_short') }}</span>
        @elseif (in_array('login_required', $buttons, true))
            <x-ui.button :href="route('login')" icon="phone">{{ __('listings.detail.contact') }}</x-ui.button>
        @else
            @if ($hasCall)<x-ui.button icon="phone" data-contact-channel="call" data-contact-url="{{ $contactUrl }}" data-contact-target="contact-box">{{ __('listings.detail.call') }}</x-ui.button>@endif
            @if ($hasWhatsapp)<x-ui.button variant="secondary" icon="message-circle" data-contact-channel="whatsapp" data-contact-url="{{ $contactUrl }}" data-contact-target="contact-box">{{ __('public.whatsapp') }}</x-ui.button>@endif
        @endif
    </div>
@endsection
