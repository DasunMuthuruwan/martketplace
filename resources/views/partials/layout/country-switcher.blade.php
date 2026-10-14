{{-- Country switcher (global UX §1): a small form posting to country.choose, which remembers the
     choice and opens that country's home. Works without JavaScript. --}}
@if ($switcherCountries->isNotEmpty())
    <details class="group relative" data-dropdown>
        <summary class="inline-flex h-10 cursor-pointer list-none items-center gap-2 rounded-control px-2 text-ui text-fg hover:bg-surface-hover [&::-webkit-details-marker]:hidden"
            aria-label="{{ __('layout.country_label', ['country' => $currentCountry?->name ?? __('layout.all_countries')]) }}">
            @if ($currentCountry)
                <x-ui.flag :code="$currentCountry->iso2" class="text-base" />
                <span class="hidden xl:inline">{{ $currentCountry->name }}</span>
            @else
                <x-ui.icon name="globe" size="20" />
            @endif
            <x-ui.icon name="chevron-down" size="16" class="text-fg-muted transition-transform group-open:rotate-180" />
        </summary>
        <div class="absolute right-0 z-40 mt-1 max-h-80 w-64 overflow-auto rounded-card border border-line bg-surface p-1 shadow-overlay">
            <form method="POST" action="{{ route('country.choose') }}">
                @csrf
                @foreach ($switcherCountries as $option)
                    <button type="submit" name="country" value="{{ strtolower($option->iso2) }}" @if ($currentCountry?->is($option)) aria-current="true" @endif
                        class="flex w-full items-center gap-2 rounded-control px-3 py-2 text-left text-ui text-fg hover:bg-surface-hover aria-[current]:font-semibold">
                        <x-ui.flag :code="$option->iso2" />{{ $option->name }}
                    </button>
                @endforeach
            </form>
            <a href="{{ route('countries') }}" class="mt-1 block border-t border-line px-3 py-2 text-ui text-fg-muted hover:text-fg">{{ __('layout.all_countries') }}</a>
        </div>
    </details>
@endif
