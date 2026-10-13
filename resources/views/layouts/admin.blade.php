{{-- Plain admin layout for M6. Replaced by the designed admin UI in Phase 6 (F5.1). Livewire injects its
     CSP-safe scripts with the request nonce. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title ?? __('admin.title') }} | {{ config('app.name') }}</title>
    <style nonce="{{ Vite::cspNonce() }}">
        body { margin: 0; font-family: system-ui, sans-serif; background: #f7f7f7; color: #0a0a0a; }
        header, main { max-width: 80rem; margin: 0 auto; padding: 1rem; }
        header nav { display: flex; gap: 1rem; flex-wrap: wrap; }
        main { background: #fff; border: 1px solid #e5e5e5; border-radius: 10px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border-bottom: 1px solid #e5e5e5; padding: .4rem; text-align: left; vertical-align: top; }
        .muted { color: #4d4d4d; font-size: .875rem; }
        .status { background: #f0f0f0; padding: .75rem; border-radius: 6px; }
        .error { color: #c5221f; }
        .flag { color: #c5221f; font-weight: 600; }
        .pane { border: 1px solid #e5e5e5; border-radius: 8px; padding: 1rem; margin-top: 1rem; }
        .pane img { max-width: 12rem; height: auto; margin: .25rem; }
        textarea, input, select, button { font: inherit; padding: .4rem; }
        textarea { width: 100%; box-sizing: border-box; }
        :focus-visible { outline: 2px solid #0a0a0a; outline-offset: 2px; }
    </style>
</head>
<body>
    <header>
        <nav aria-label="{{ __('admin.title') }}">
            <a href="{{ route('admin.dashboard') }}"><strong>{{ __('admin.title') }}</strong></a>
            @can(\App\Authorization\Permissions::LISTINGS_MODERATE)
                <a href="{{ route('admin.moderation') }}">{{ __('admin.nav.moderation') }}</a>
                <a href="{{ route('admin.changes') }}">{{ __('admin.nav.changes') }}</a>
            @endcan
            @can(\App\Authorization\Permissions::REPORTS_VIEW)
                <a href="{{ route('admin.reports') }}">{{ __('admin.nav.reports') }}</a>
            @endcan
            @can(\App\Authorization\Permissions::USERS_VIEW)
                <a href="{{ route('admin.users') }}">{{ __('admin.nav.users') }}</a>
            @endcan
            @can(\App\Authorization\Permissions::STAFF_MANAGE)
                <a href="{{ route('admin.staff') }}">{{ __('admin.nav.staff') }}</a>
            @endcan
            <a href="{{ route('home') }}">{{ __('admin.nav.site') }}</a>
        </nav>
    </header>
    <main>
        {{ $slot }}
    </main>
</body>
</html>
