@extends('layouts.public')

@section('title', $country->name)

@section('content')
    <h1>{{ $country->name }}</h1>

    <form method="GET" action="{{ route('ads.country', ['cc' => strtolower($country->iso2)]) }}" role="search">
        <label for="q" class="muted">{{ __('public.keyword') }}</label>
        <input type="search" id="q" name="q" maxlength="100">
        <button type="submit">{{ __('public.search') }}</button>
    </form>

    <h2>{{ __('public.categories') }}</h2>
    <ul>
        @foreach ($categories as $category)
            <li>
                <a href="{{ route('ads.category', ['cc' => strtolower($country->iso2), 'category' => $category->slug]) }}">{{ $category->name }}</a>
                <ul>
                    @foreach ($category->children as $child)
                        <li><a href="{{ route('ads.category', ['cc' => strtolower($country->iso2), 'category' => $category->slug, 'subcategory' => $child->slug]) }}">{{ $child->name }}</a></li>
                    @endforeach
                </ul>
            </li>
        @endforeach
    </ul>

    <h2>{{ __('public.latest') }}</h2>
    @if ($latest->isEmpty())
        <p>{{ __('public.no_results') }}</p>
    @else
        <ul class="cards">
            @foreach ($latest as $listing)
                @include('partials.listing-card')
            @endforeach
        </ul>
    @endif
@endsection
