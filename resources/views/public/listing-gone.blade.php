@extends('layouts.public')

@section('title', $sold ? __('public.sold_title') : __('public.gone_title'))

@section('content')
    <h1>{{ $sold ? __('public.sold_title') : __('public.gone_title') }}</h1>
    @if ($sold)
        <p><strong>{{ $listing->title }}</strong></p>
    @endif
    <p>{{ $sold ? __('public.sold_text') : __('public.gone_text') }}</p>
    @if ($listing->category && $listing->country?->is_active)
        <p>
            <a href="{{ route('ads.category', ['cc' => strtolower($listing->country->iso2), 'category' => $listing->category->parent?->slug ?? $listing->category->slug, 'subcategory' => $listing->category->parent ? $listing->category->slug : null]) }}">
                {{ __('public.browse_similar', ['category' => $listing->category->name, 'country' => $listing->country->name]) }}
            </a>
        </p>
    @endif
@endsection
