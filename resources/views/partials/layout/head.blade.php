{{-- Shared <head> parts: theme before paint (no flash), bundled CSS/JS, favicon. --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="light dark">
<meta name="csrf-token" content="{{ csrf_token() }}">
@include('partials.theme-script')
@php
    // Preload the Latin text font (performance budget, forms/a11y doc §5); skipped when there is no build (tests, CI).
    try {
        $fontPreload = \Illuminate\Support\Facades\Vite::asset('node_modules/@fontsource-variable/geist/files/geist-latin-wght-normal.woff2');
    } catch (\Throwable) {
        $fontPreload = null;
    }
@endphp
@if ($fontPreload)<link rel="preload" href="{{ $fontPreload }}" as="font" type="font/woff2" crossorigin>@endif
@vite(['resources/css/app.css', 'resources/js/app.js'])
@if (config('marketplace.brand.logo'))
    <link rel="icon" href="{{ asset(config('marketplace.brand.logo')) }}">
@endif
