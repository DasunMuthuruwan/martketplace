@extends('layouts.public')

@section('title', $seller->name)

@section('content')
    {{-- Seller profile (components §5.4): public details only; contact stays on each ad. --}}
    <section class="mb-8 flex flex-wrap items-center gap-4 rounded-card border border-line bg-surface p-5 sm:p-6">
        <x-ui.avatar :name="$seller->name" size="64" />
        <div class="min-w-0 flex-1">
            <h1 class="text-h1 font-[650] prose-user">{{ $seller->name }}</h1>
            <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-small text-fg-subtle">
                <span>{{ __('public.member_since', ['date' => $seller->created_at?->translatedFormat('F Y')]) }}</span>
                @if ($seller->hasVerifiedEmail())
                    <span class="inline-flex items-center gap-1 text-fg-muted"><x-ui.icon name="badge-check" size="16" class="text-success" />{{ __('public.email_verified') }}</span>
                @endif
                <span>{{ trans_choice('public.ads_count', $listings->total(), ['count' => number_format($listings->total())]) }}</span>
            </p>
        </div>
    </section>

    <h2 class="mb-3 text-h2 font-semibold">{{ __('public.seller_ads', ['name' => $seller->name]) }}</h2>
    @if ($listings->isEmpty())
        <x-ui.empty-state icon="package" :title="__('listings.detail.seller_no_ads')" />
    @else
        <div class="listing-grid-area">
            <ul class="listing-grid">
                @foreach ($listings as $listing)
                    <li><x-listing.card :listing="$listing" :present="$present" :with-code="true" class="h-full" /></li>
                @endforeach
            </ul>
        </div>
        <x-ui.pagination :paginator="$listings" class="mt-8" />
    @endif
@endsection
