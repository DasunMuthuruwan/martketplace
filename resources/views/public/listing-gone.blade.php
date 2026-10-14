@extends('layouts.public')

@section('title', $sold ? __('public.sold_title') : __('public.gone_title'))

@section('content')
    {{-- Sold (200, noindex, for a while after the sale) or gone (410): no contact, similar ads promoted (layout doc §3.3). --}}
    <div class="mx-auto max-w-xl py-6 text-center">
        <span class="mx-auto mb-4 grid size-14 place-items-center rounded-full bg-surface-muted text-fg-muted">
            <x-ui.icon :name="$sold ? 'circle-check' : 'clock'" size="24" />
        </span>
        <h1 class="text-h1 font-[650]">{{ $sold ? __('public.sold_title') : __('public.gone_title') }}</h1>
        @if ($sold)
            <p class="mt-2 text-body font-medium text-fg prose-user">{{ $listing->title }}</p>
        @endif
        <p class="mt-2 text-body text-fg-muted">{{ $sold ? __('public.sold_text') : __('public.gone_text') }}</p>
        @if ($listing->category && $listing->country?->is_active)
            <x-ui.button class="mt-6" icon-right="chevron-right"
                :href="route('ads.category', ['cc' => strtolower($listing->country->iso2), 'category' => $listing->category->parent?->slug ?? $listing->category->slug, 'subcategory' => $listing->category->parent ? $listing->category->slug : null])">
                {{ __('public.browse_similar', ['category' => $listing->category->name, 'country' => $listing->country->name]) }}
            </x-ui.button>
        @endif
    </div>
@endsection
