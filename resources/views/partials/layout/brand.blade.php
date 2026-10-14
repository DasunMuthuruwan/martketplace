{{-- Wordmark (Q-UX-2): the configured name with a monogram, or the configured logo files. --}}
@php
    $name = config('app.name');
    $logo = config('marketplace.brand.logo');
    $logoDark = config('marketplace.brand.logo_dark');
@endphp
<a href="{{ $href ?? route('home') }}" class="inline-flex shrink-0 items-center gap-2 rounded-control text-fg" aria-label="{{ __('layout.home_label', ['name' => $name]) }}">
    @if ($logo)
        <img src="{{ asset($logo) }}" alt="" class="h-8 w-auto {{ $logoDark ? 'dark:hidden' : '' }}">
        @if ($logoDark)<img src="{{ asset($logoDark) }}" alt="" class="hidden h-8 w-auto dark:block">@endif
    @else
        <span aria-hidden="true" class="grid size-8 place-items-center rounded-control bg-primary text-ui font-bold text-primary-fg">{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</span>
        <span class="text-h3 font-semibold tracking-tight">{{ $name }}</span>
    @endif
</a>
