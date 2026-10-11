{{-- Plain, functional layout for Phase 5 screens. Replaced by the designed layouts in Phase 6 (F1.3). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') | {{ config('app.name') }}</title>
    <style nonce="{{ Vite::cspNonce() }}">
        body { margin: 0; font-family: system-ui, sans-serif; background: #f7f7f7; color: #0a0a0a; }
        main { max-width: 28rem; margin: 3rem auto; padding: 1.5rem; background: #fff; border: 1px solid #e5e5e5; border-radius: 10px; }
        label { display: block; font-weight: 600; margin-top: 1rem; }
        input[type=text], input[type=email], input[type=password], input[type=tel], select { width: 100%; box-sizing: border-box; padding: .6rem; margin-top: .3rem; border: 1px solid #858585; border-radius: 6px; font: inherit; }
        button { margin-top: 1.25rem; padding: .7rem 1rem; background: #0a0a0a; color: #fff; border: 0; border-radius: 6px; font: inherit; cursor: pointer; }
        .hint { color: #4d4d4d; font-size: .875rem; }
        .error { color: #c5221f; font-size: .875rem; margin: .3rem 0 0; }
        .status { background: #f0f0f0; padding: .75rem; border-radius: 6px; }
        .hp { position: absolute; left: -10000px; width: 1px; height: 1px; overflow: hidden; }
        :focus-visible { outline: 2px solid #0a0a0a; outline-offset: 2px; }
    </style>
    @stack('head')
</head>
<body>
    <main>
        <h1>@yield('title')</h1>
        @if (session('status'))
            <p class="status" role="status">{{ session('status') }}</p>
        @endif
        @yield('content')
    </main>
</body>
</html>
