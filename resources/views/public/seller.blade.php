@extends('layouts.public')

@section('title', $seller->name)

@section('content')
    <h1>{{ $seller->name }}</h1>
    <p class="muted">
        {{ __('public.member_since', ['date' => $seller->created_at?->format('F Y')]) }}
        @if ($seller->hasVerifiedEmail()) · {{ __('public.email_verified') }} @endif
    </p>

    <h2>{{ __('public.seller_ads', ['name' => $seller->name]) }}</h2>
    @if ($listings->isEmpty())
        <p>{{ __('public.no_results') }}</p>
    @else
        <ul class="cards">
            @foreach ($listings as $listing)
                @include('partials.listing-card', ['withCode' => true])
            @endforeach
        </ul>
        <nav aria-label="{{ __('public.pages') }}">
            @if ($listings->previousPageUrl())<a href="{{ $listings->previousPageUrl() }}" rel="prev">{{ __('public.previous') }}</a>@endif
            @if ($listings->nextPageUrl())<a href="{{ $listings->nextPageUrl() }}" rel="next">{{ __('public.next') }}</a>@endif
        </nav>
    @endif
@endsection
