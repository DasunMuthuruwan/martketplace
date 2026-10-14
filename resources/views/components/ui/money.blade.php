@props([
    'amount',
    'currency',            // App\Models\Currency
    'country' => null,     // App\Models\Country: its number locale (G-5)
    'locale' => null,      // or an explicit locale
    'withCode' => false,   // cross-country pages: "$12,500 USD"
    'size' => 'price',     // price (cards) | price-xl (detail) | inherit
])
{{-- Full figure in the listing's own currency, never abbreviated or converted (Q-UX-4). --}}
@php
    $money = app(\App\Services\Money\Money::class);
    $formatted = $country !== null
        ? $money->forCountry($amount, $currency, $country, (bool) $withCode)
        : $money->format($amount, $currency, $locale ?? 'en', (bool) $withCode);
    $sizes = ['price' => 'text-price font-bold', 'price-xl' => 'text-price-xl font-bold', 'inherit' => ''];
@endphp
@if ($formatted !== null)
    <span {{ $attributes->class(['tabular whitespace-nowrap text-fg', $sizes[$size] ?? '']) }}>{{ $formatted }}</span>@if ($slot->isNotEmpty())<span class="text-small font-normal text-fg-subtle"> {{ $slot }}</span>@endif
@endif
