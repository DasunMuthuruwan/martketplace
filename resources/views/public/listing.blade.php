@extends('layouts.public')

@section('title', $listing->title.' – '.$present->location($listing, true))

@section('content')
    <p class="muted">
        <a href="{{ route('ads.category', ['cc' => strtolower($listing->country->iso2), 'category' => $listing->category->parent?->slug ?? $listing->category->slug, 'subcategory' => $listing->category->parent ? $listing->category->slug : null]) }}">{{ $listing->category->name }}</a>
        · {{ $present->location($listing, true) }}
    </p>
    <h1>{{ $listing->title }}</h1>

    @if ($listing->images->isNotEmpty())
        <ul class="cards">
            @foreach ($listing->images as $image)
                <li><img src="{{ $image->url('lg') }}" alt="{{ $listing->title }}" width="{{ $image->width }}" height="{{ $image->height }}" loading="{{ $loop->first ? 'eager' : 'lazy' }}"></li>
            @endforeach
        </ul>
    @endif

    <p><strong>{{ $present->price($listing) }}</strong></p>
    @if ($unit = $present->unitPrice($listing))
        <p class="muted">{{ $unit }}</p>
    @endif
    <p class="muted">{{ __('public.posted', ['date' => $listing->published_at?->toFormattedDayDateString()]) }}</p>

    @if ($attributes !== [])
        <h2>{{ __('public.details') }}</h2>
        <dl>
            @if ($listing->condition)
                <dt>{{ __('public.condition') }}</dt><dd>{{ __('public.conditions.'.$listing->condition->value) }}</dd>
            @endif
            @foreach ($attributes as $label => $value)
                <dt>{{ $label }}</dt><dd>{{ $value }}</dd>
            @endforeach
        </dl>
    @endif

    <h2>{{ __('public.description') }}</h2>
    <p>{!! nl2br(e($listing->description)) !!}</p>

    <h2>{{ __('public.contact') }}</h2>
    {{-- Numbers are never in the HTML (MSG-04): buttons call POST /listings/{id}/contact (wired up in F2.3). --}}
    @if ($buttons === [])
        <p>{{ __('public.no_contact') }}</p>
    @else
        @foreach ($buttons as $channel => $decision)
            @if ($decision === 'login_required')
                <a href="{{ route('login') }}">{{ __('public.login_to_contact') }}</a>
            @else
                <button type="button" data-contact-url="{{ route('listings.contact', $listing) }}" data-channel="{{ $channel }}">
                    {{ $channel === 'call' ? __('public.show_number') : __('public.whatsapp') }}
                </button>
            @endif
        @endforeach
    @endif
    <p class="muted">{{ __('public.safety') }}</p>

    <h2>{{ __('public.seller') }}</h2>
    <p>
        <a href="{{ $listing->user->sellerUrl() }}">{{ $listing->user->name }}</a><br>
        <span class="muted">{{ __('public.member_since', ['date' => $listing->user->created_at?->format('F Y')]) }}</span>
        @if ($listing->user->hasVerifiedEmail()) <span class="muted">· {{ __('public.email_verified') }}</span> @endif
    </p>

    @auth
        <form method="POST" action="{{ route('listings.favourite', $listing) }}" data-favourited="{{ $favourited ? '1' : '0' }}">
            @csrf
            <button type="submit">{{ __('public.save') }}</button>
        </form>
    @endauth

    @if ($similar->isNotEmpty())
        <h2>{{ __('public.similar') }}</h2>
        <ul class="cards">
            @foreach ($similar as $item)
                @include('partials.listing-card', ['listing' => $item])
            @endforeach
        </ul>
    @endif
@endsection
