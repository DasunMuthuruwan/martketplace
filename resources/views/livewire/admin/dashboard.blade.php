@use('App\Authorization\Permissions', 'P')
<div>
    <x-admin.page-header :title="__('admin.dashboard.title')" :description="__('admin.dashboard.intro')" />

    {{-- Work queues first (always current), then the cached breakdowns. --}}
    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
        <x-admin.stat :label="__('admin.dashboard.pending')" :value="$pending" icon="shield-check" tone="warning"
            :href="auth()->user()->can(P::LISTINGS_MODERATE) ? route('admin.moderation') : null" />
        <x-admin.stat :label="__('admin.dashboard.reports')" :value="$openReports" icon="flag" tone="warning"
            :href="auth()->user()->can(P::REPORTS_VIEW) ? route('admin.reports') : null" />
        <x-admin.stat :label="__('admin.dashboard.place_requests')" :value="$placeRequests" icon="map-pin" tone="warning"
            :href="auth()->user()->can(P::LOCATIONS_MANAGE) ? route('admin.location-requests') : null" />
        <x-admin.stat :label="__('admin.dashboard.new_users_7')" :value="$newUsers7" icon="users"
            :note="__('admin.dashboard.new_users_30', ['count' => number_format($newUsers30)])" />
        <x-admin.stat :label="__('admin.dashboard.live')" :value="$live" icon="layout-grid" />
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        @foreach ([
            'category' => [__('admin.dashboard.by_category'), $byCategory],
            'country' => [__('admin.dashboard.by_country'), $byCountry],
        ] as $kind => [$heading, $rows])
            <section aria-labelledby="by-{{ $kind }}" class="rounded-card border border-line bg-surface p-4">
                <h2 id="by-{{ $kind }}" class="mb-3 text-h3 font-semibold">{{ $heading }}</h2>
                @if ($rows === [])
                    <p class="text-small text-fg-subtle">{{ __('admin.dashboard.none') }}</p>
                @else
                    <ul class="space-y-3">
                        @foreach ($rows as $name => $total)
                            <li>
                                <div class="mb-1 flex items-center justify-between gap-3 text-small">
                                    <span class="flex min-w-0 items-center gap-2 text-fg">
                                        @if ($kind === 'country')
                                            <x-ui.flag :code="$name" /><span class="truncate">{{ $countryNames[$name] ?? $name }}</span>
                                        @else
                                            <span class="truncate">{{ $name }}</span>
                                        @endif
                                    </span>
                                    <span class="tabular text-fg-muted">{{ number_format($total) }}</span>
                                </div>
                                <progress class="share-bar" max="{{ max($live, 1) }}" value="{{ $total }}" aria-hidden="true"></progress>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach
    </div>
    <p class="mt-3 text-small text-fg-subtle">{{ __('admin.dashboard.cached') }}</p>
</div>
