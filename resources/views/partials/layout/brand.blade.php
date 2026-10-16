{{-- Wordmark (Q-UX-2): the configured name with a monogram, or the configured logo files. --}}
@php
    $name = config('app.name');
    $logo = config('marketplace.brand.logo');
    $logoDark = config('marketplace.brand.logo_dark');
@endphp
{{-- The name comes from the visible text (WCAG 2.5.3 label in name), or the logo's alt text.
     min-w-0 + truncate: a long site name shortens instead of pushing the header past narrow screens (WCAG 1.4.10). --}}
<a href="{{ $href ?? route('home') }}" class="inline-flex min-w-0 items-center gap-2 rounded-control text-fg">
    @if ($logo)
        <img src="{{ asset($logo) }}" alt="{{ __('layout.home_label', ['name' => $name]) }}" height="32" class="h-8 w-auto {{ $logoDark ? 'dark:hidden' : '' }}">
        @if ($logoDark)<img src="{{ asset($logoDark) }}" alt="{{ __('layout.home_label', ['name' => $name]) }}" height="32" class="hidden h-8 w-auto dark:block">@endif
    @else
        <span aria-hidden="true" class="grid size-8 shrink-0 place-items-center rounded-control bg-primary text-ui font-bold text-primary-fg">{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</span>
        <span class="truncate text-h3 font-semibold tracking-tight">{{ $name }}</span><span class="sr-only"> {{ __('layout.home_suffix') }}</span>
    @endif
</a>
