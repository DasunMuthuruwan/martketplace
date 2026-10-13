@extends('layouts.public')

@section('title', __('public.all_countries'))

@section('content')
    <h1>{{ __('public.all_countries') }}</h1>
    <ul>
        @foreach ($countries as $country)
            <li>
                <a href="{{ route('country.home', ['cc' => strtolower($country->iso2)]) }}">{{ $country->name }}</a>
                <span class="muted">{{ trans_choice('public.ads_count', $counts[$country->id] ?? 0, ['count' => number_format($counts[$country->id] ?? 0)]) }}</span>
            </li>
        @endforeach
    </ul>
@endsection
