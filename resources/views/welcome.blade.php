{{-- Temporary placeholder. The designed home page is built in Phase 6 (F2.1). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <style nonce="{{ Vite::cspNonce() }}">
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font-family: system-ui, sans-serif; background: #f7f7f7; color: #0a0a0a; }
        main { text-align: center; padding: 2rem 1rem; }
        @media (prefers-color-scheme: dark) { body { background: #0a0a0a; color: #f5f5f5; } }
    </style>
</head>
<body>
    <main>
        <h1>{{ config('app.name') }}</h1>
        <p>{{ __('home.placeholder_title') }} · {{ __('home.placeholder_text') }}</p>
    </main>
</body>
</html>
