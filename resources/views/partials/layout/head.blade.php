{{-- Shared <head> parts: theme before paint (no flash), bundled CSS/JS, favicon. --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="light dark">
<meta name="csrf-token" content="{{ csrf_token() }}">
@include('partials.theme-script')
@vite(['resources/css/app.css', 'resources/js/app.js'])
@if (config('marketplace.brand.logo'))
    <link rel="icon" href="{{ asset(config('marketplace.brand.logo')) }}">
@endif
