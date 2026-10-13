@extends('layouts.public')

@section('title', __('public.choose_country_title'))

@section('content')
    <h1>{{ __('public.choose_country_title') }}</h1>
    <form method="POST" action="{{ route('country.choose') }}">
        @csrf
        <label for="country" class="muted">{{ __('auth.ui.country') }}</label>
        <select id="country" name="country" required>
            @foreach ($countries as $country)
                <option value="{{ strtolower($country->iso2) }}">{{ $country->name }}</option>
            @endforeach
        </select>
        <button type="submit">{{ __('public.choose_country') }}</button>
        @error('country')<p class="muted" role="alert">{{ $message }}</p>@enderror
    </form>

    <h2>{{ __('public.latest') }}</h2>
    @if ($latest->isEmpty())
        <p>{{ __('public.no_results') }}</p>
    @else
        <ul class="cards">
            @foreach ($latest as $listing)
                @include('partials.listing-card', ['withCode' => true])
            @endforeach
        </ul>
    @endif
@endsection
