@extends('layouts.public')

@section('title', $criteria->category?->name ?? ($country?->name ?? __('public.search_all')))

@section('content')
    <h1>
        {{ $criteria->category?->name ?? __('public.search_all') }}
        @if ($country) <span class="muted">· {{ $country->name }}</span> @endif
    </h1>

    @php($currency = $criteria->currency ?? ($country?->default_currency))
    <form method="GET" action="{{ request()->url() }}" class="filters" role="search">
        <label>{{ __('public.keyword') }}
            <input type="search" name="q" value="{{ $criteria->keyword }}" maxlength="100">
        </label>
        <label>{{ __('public.type') }}
            <select name="type">
                <option value="">{{ __('public.any') }}</option>
                @foreach (__('public.types') as $value => $label)
                    <option value="{{ $value }}" @selected($criteria->type === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>{{ __('public.condition') }}
            <select name="condition">
                <option value="">{{ __('public.any') }}</option>
                @foreach (__('public.conditions') as $value => $label)
                    <option value="{{ $value }}" @selected($criteria->condition === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        @if ($country === null)
            <label>{{ __('public.currency') }}
                <input type="text" name="currency" value="{{ $criteria->currency }}" maxlength="3" size="4" autocomplete="off">
            </label>
        @endif
        @if ($currency)
            <label>{{ __('public.min_price', ['currency' => $currency]) }}
                <input type="number" name="min_price" value="{{ $criteria->minPrice }}" min="0" step="any">
            </label>
            <label>{{ __('public.max_price', ['currency' => $currency]) }}
                <input type="number" name="max_price" value="{{ $criteria->maxPrice }}" min="0" step="any">
            </label>
        @endif
        <label>{{ __('public.sort') }}
            <select name="sort">
                @foreach (__('public.sort_options') as $value => $label)
                    <option value="{{ $value }}" @selected($criteria->sort === $value) @disabled($value !== 'newest' && ! $currency)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        @foreach ($filters as $field)
            @php($current = request()->input('a.'.$field['key']))
            @if (in_array($field['type'], ['select', 'multiselect'], true))
                <label>{{ $field['label'] }}
                    <select name="a[{{ $field['key'] }}]">
                        <option value="">{{ __('public.any') }}</option>
                        @foreach ($field['options'] as $option)
                            <option value="{{ $option['value'] }}" @selected($current === $option['value'])>{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </label>
            @elseif ($field['type'] === 'boolean')
                <label><input type="checkbox" name="a[{{ $field['key'] }}]" value="1" @checked($current === '1')> {{ $field['label'] }}</label>
            @else
                <label>{{ $field['label'] }} ≥
                    <input type="number" name="a[{{ $field['key'] }}][min]" value="{{ is_array($current) ? ($current['min'] ?? '') : '' }}" step="any">
                </label>
                <label>≤
                    <input type="number" name="a[{{ $field['key'] }}][max]" value="{{ is_array($current) ? ($current['max'] ?? '') : '' }}" step="any">
                </label>
                @if ($field['units'] !== [])
                    <label>&nbsp;
                        <select name="a[{{ $field['key'] }}][unit]">
                            @foreach ($field['units'] as $unit)
                                <option value="{{ $unit['code'] }}" @selected(is_array($current) && ($current['unit'] ?? null) === $unit['code'])>{{ $unit['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
            @endif
        @endforeach

        <button type="submit">{{ __('public.search') }}</button>
    </form>

    @if ($criteria->priceIgnored)
        <p class="status" role="status">{{ __('public.price_needs_currency') }}</p>
    @endif

    @php($capped = $results->total() >= $countCap)
    <p class="muted">{{ trans_choice('public.ads_count', $results->total(), ['count' => number_format($capped ? $results->total() - 1 : $results->total()).($capped ? '+' : '')]) }}</p>

    @if ($results->isEmpty())
        <p>{{ __('public.no_results') }}</p>
    @else
        <ul class="cards">
            @foreach ($results as $listing)
                @include('partials.listing-card', ['withCode' => $country === null])
            @endforeach
        </ul>
        <nav aria-label="{{ __('public.pages') }}">
            @if ($results->previousPageUrl())<a href="{{ $results->previousPageUrl() }}" rel="prev">{{ __('public.previous') }}</a>@endif
            @if ($results->nextPageUrl() && $results->currentPage() < config('marketplace.listings.max_page'))<a href="{{ $results->nextPageUrl() }}" rel="next">{{ __('public.next') }}</a>@endif
        </nav>
    @endif
@endsection
