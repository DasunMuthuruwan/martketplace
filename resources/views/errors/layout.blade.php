{{-- Plain error layout: no database queries, no external assets. Designed version arrives in Phase 6. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} | {{ config('app.name') }}</title>
    <style nonce="{{ Vite::cspNonce() }}">
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: system-ui, sans-serif; background: #f7f7f7; color: #0a0a0a; }
        main { max-width: 32rem; padding: 2rem 1rem; text-align: center; }
        h1 { font-size: 1.5rem; margin: 0 0 .5rem; }
        p { color: #4d4d4d; }
        a { color: inherit; }
        @media (prefers-color-scheme: dark) { body { background: #0a0a0a; color: #f5f5f5; } p { color: #b3b3b3; } }
    </style>
</head>
<body>
    <main>
        <p>{{ $code }}</p>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        <p><a href="{{ url('/') }}">{{ __('errors.back_home') }}</a></p>
    </main>
</body>
</html>
