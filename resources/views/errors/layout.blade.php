{{-- Error pages: no database queries and no built assets (they must render even when the database
     or the asset build is the problem). Colours are the design tokens, inline with the CSP nonce. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="color-scheme" content="light dark">
    <title>{{ $title }} | {{ config('app.name') }}</title>
    <style nonce="{{ Vite::cspNonce() }}">
        :root { --canvas: #f7f7f7; --surface: #fff; --line: #e5e5e5; --fg: #0a0a0a; --fg-muted: #4d4d4d; --primary: #0a0a0a; --primary-fg: #fff; }
        @media (prefers-color-scheme: dark) {
            :root { --canvas: #0a0a0a; --surface: #141414; --line: #2a2a2a; --fg: #f5f5f5; --fg-muted: #b3b3b3; --primary: #f5f5f5; --primary-fg: #0a0a0a; }
        }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 1rem; box-sizing: border-box;
            font: 400 15px/24px ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; background: var(--canvas); color: var(--fg); }
        main { width: 100%; max-width: 30rem; padding: 2rem; background: var(--surface); border: 1px solid var(--line); border-radius: 10px; text-align: center; }
        .code { margin: 0; font-size: 13px; letter-spacing: .08em; color: var(--fg-muted); }
        h1 { margin: .5rem 0; font-size: 22px; line-height: 28px; font-weight: 650; }
        p { color: var(--fg-muted); }
        a.button { display: inline-flex; align-items: center; height: 40px; padding: 0 16px; border-radius: 6px; background: var(--primary); color: var(--primary-fg); text-decoration: none; font-weight: 500; }
        a:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }
    </style>
</head>
<body>
    <main>
        <p class="code">{{ $code }}</p>
        <h1>{{ $title }}</h1>
        <p>{{ $message }}</p>
        <p><a class="button" href="{{ url('/') }}">{{ __('errors.back_home') }}</a></p>
    </main>
</body>
</html>
