{{-- Account menu (navigation doc §2): guests get "Log in"; members a menu with their screens,
     the admin panel for staff, the theme choice and log out. --}}
@auth
    @php($user = auth()->user())
    <details class="group relative" data-dropdown>
        <summary class="inline-flex h-10 cursor-pointer list-none items-center gap-2 rounded-control px-1.5 hover:bg-surface-hover [&::-webkit-details-marker]:hidden" aria-label="{{ __('layout.account_menu') }}">
            <x-ui.avatar :name="$user->name" size="32" />
        </summary>
        <div class="absolute right-0 z-40 mt-1 w-64 rounded-card border border-line bg-surface p-1 shadow-overlay">
            <p class="truncate px-3 pb-2 pt-1.5 text-small text-fg-subtle">{{ $user->name }}</p>
            @foreach (['my_ads' => ['list', 'layout.my_ads'], 'saved' => ['heart', 'layout.saved'], 'settings' => ['settings', 'layout.settings']] as $destination => [$icon, $text])
                @if ($url = \App\View\Navigation::url($destination))
                    <x-ui.dropdown-item :href="$url" :icon="$icon">{{ __($text) }}</x-ui.dropdown-item>
                @endif
            @endforeach
            @can(\App\Authorization\Permissions::ACCESS_ADMIN)
                <x-ui.dropdown-item :href="route('admin.dashboard')" icon="shield-check">{{ __('layout.admin_panel') }}</x-ui.dropdown-item>
            @endcan
            <div class="my-1 flex items-center justify-between border-t border-line px-3 pt-2">
                <span class="text-small text-fg-subtle">{{ __('ui.theme.label') }}</span>
                @include('partials.layout.theme-toggle')
            </div>
            <form method="POST" action="{{ route('logout') }}" class="border-t border-line pt-1">
                @csrf
                <x-ui.dropdown-item type="submit" icon="log-out">{{ __('layout.log_out') }}</x-ui.dropdown-item>
            </form>
        </div>
    </details>
@else
    <x-ui.button variant="ghost" :href="route('login')" icon="circle-user" class="max-lg:px-2">
        <span class="max-lg:sr-only">{{ __('auth.ui.login') }}</span>
    </x-ui.button>
@endauth
