{{-- Admin navigation (components §3 admin-sidebar): grouped, permission-aware, with work-queue
     badges. Used in the desktop sidebar and the mobile drawer. --}}
@use('App\Authorization\Permissions', 'P')
@php
    $groups = [
        __('admin.groups.work') => [
            ['admin.dashboard', 'layout-grid', __('admin.dashboard.title'), P::ACCESS_ADMIN, null],
            ['admin.moderation', 'shield-check', __('admin.nav.moderation'), P::LISTINGS_MODERATE, $adminBadges['pending'] ?? null],
            ['admin.changes', 'clock', __('admin.nav.changes'), P::LISTINGS_MODERATE, null],
            ['admin.reports', 'flag', __('admin.nav.reports'), P::REPORTS_VIEW, $adminBadges['reports'] ?? null],
        ],
        __('admin.groups.catalogue') => [
            ['admin.categories', 'list', __('admin.nav.categories'), P::CATEGORIES_MANAGE, null],
            ['admin.attributes', 'sliders-horizontal', __('admin.nav.attributes'), P::ATTRIBUTES_MANAGE, null],
            ['admin.locations', 'map-pin', __('admin.nav.locations'), P::LOCATIONS_MANAGE, null],
        ],
        __('admin.groups.content') => [
            ['admin.pages', 'pencil', __('admin.nav.pages'), P::PAGES_MANAGE, null],
            ['admin.blocklist', 'circle-alert', __('admin.nav.blocklist'), P::BLOCKLIST_MANAGE, null],
        ],
        __('admin.groups.people') => [
            ['admin.users', 'user', __('admin.nav.users'), P::USERS_VIEW, null],
            ['admin.staff', 'badge-check', __('admin.nav.staff'), P::STAFF_MANAGE, null],
        ],
        __('admin.groups.system') => [
            ['admin.audit', 'eye', __('admin.nav.audit'), P::AUDIT_VIEW, null],
            ['admin.settings', 'settings', __('admin.nav.settings'), P::SETTINGS_MANAGE, null],
            ['admin.design-system', 'image', __('admin.nav.design_system'), P::SETTINGS_MANAGE, null],
        ],
    ];
@endphp
<nav aria-label="{{ __('admin.title') }}" class="space-y-5">
    @foreach ($groups as $group => $links)
        @php($visible = array_filter($links, fn ($link) => auth()->user()?->can($link[3])))
        @if ($visible !== [])
            <div>
                <p class="mb-1 px-3 text-micro font-medium uppercase tracking-wide text-fg-subtle">{{ $group }}</p>
                <ul class="space-y-0.5">
                    @foreach ($visible as [$route, $icon, $text, $permission, $badge])
                        <li>
                            <a href="{{ route($route) }}" @if (request()->routeIs($route)) aria-current="page" @endif
                                class="flex items-center gap-2.5 rounded-control px-3 py-2 text-ui text-fg-muted hover:bg-surface-hover hover:text-fg aria-[current]:bg-surface-muted aria-[current]:font-semibold aria-[current]:text-fg">
                                <x-ui.icon :name="$icon" size="16" />
                                <span class="flex-1">{{ $text }}</span>
                                @if ($badge)<span class="rounded-full bg-primary px-1.5 text-micro tabular text-primary-fg">{{ $badge }}<span class="sr-only"> {{ __('admin.waiting') }}</span></span>@endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endforeach
</nav>
