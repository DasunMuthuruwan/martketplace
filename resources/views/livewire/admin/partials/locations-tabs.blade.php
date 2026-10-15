{{-- Sub-navigation of the locations section. $active is passed in (Livewire updates run on another route). --}}
<nav aria-label="{{ __('admin.nav.locations') }}" class="mb-5 flex gap-1 overflow-x-auto border-b border-line">
    @foreach ([
        'admin.countries' => __('admin.nav.countries'),
        'admin.locations' => __('admin.nav.locations'),
        'admin.location-requests' => __('admin.nav.location_requests'),
    ] as $route => $text)
        @php($current = $active === $route)
        <a href="{{ route($route) }}" @if ($current) aria-current="page" @endif
            @class([
                '-mb-px whitespace-nowrap border-b-2 px-3 py-2 text-ui font-medium',
                'border-fg text-fg' => $current,
                'border-transparent text-fg-muted hover:text-fg' => ! $current,
            ])>{{ $text }}</a>
    @endforeach
</nav>
