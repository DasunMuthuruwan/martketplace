{{-- Mobile bottom navigation (Q-UX-3, components §3.1): Home · Search · Post · Saved · Account.
     Hidden from 1024 px. Guests are sent to log in for Post, Saved and Account and come back after.
     A destination whose screen is not built yet shows as unavailable. --}}
@php
    $cc = $currentCountry ? strtolower($currentCountry->iso2) : null;
    $guest = ! auth()->check();
    $loginFor = fn (?string $url) => $url === null ? null : ($guest ? route('login') : $url);
    $items = [
        'home' => ['house', __('layout.nav.home'), $cc ? route('country.home', ['cc' => $cc]) : route('home')],
        'search' => ['search', __('layout.nav.search'), $cc ? route('ads.country', ['cc' => $cc]) : route('ads.global')],
        'post' => ['plus', __('layout.nav.post'), $loginFor(\App\View\Navigation::url('post'))],
        'saved' => ['heart', __('layout.nav.saved'), $loginFor(\App\View\Navigation::url('saved'))],
        'account' => ['circle-user', __('layout.nav.account'), $guest ? route('login') : \App\View\Navigation::url('account')],
    ];
    $current = request()->url();
@endphp
<nav aria-label="{{ __('layout.nav.label') }}" data-bottom-nav
    class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface pb-[env(safe-area-inset-bottom)] lg:hidden">
    <ul class="grid h-14 grid-cols-5">
        @foreach ($items as $key => [$icon, $text, $url])
            @php($active = $url !== null && rtrim($url, '/') === rtrim($current, '/'))
            <li class="flex">
                @if ($url === null)
                    <span aria-disabled="true" title="{{ __('layout.nav.soon') }}" class="flex flex-1 flex-col items-center justify-center gap-0.5 text-micro text-fg-subtle opacity-50">
                        @if ($key === 'post')
                            <span class="-mt-4 grid size-11 place-items-center rounded-full border-4 border-surface bg-primary text-primary-fg"><x-ui.icon :name="$icon" size="20" /></span>
                        @else
                            <x-ui.icon :name="$icon" size="20" />
                        @endif
                        {{ $text }}
                    </span>
                @else
                    <a href="{{ $url }}" @if ($active) aria-current="page" @endif
                        class="flex flex-1 flex-col items-center justify-center gap-0.5 text-micro text-fg-subtle aria-[current]:font-semibold aria-[current]:text-fg">
                        @if ($key === 'post')
                            <span class="-mt-4 grid size-11 place-items-center rounded-full border-4 border-surface bg-primary text-primary-fg"><x-ui.icon :name="$icon" size="20" /></span>
                        @else
                            <x-ui.icon :name="$icon" size="20" />
                        @endif
                        {{ $text }}
                    </a>
                @endif
            </li>
        @endforeach
    </ul>
</nav>
