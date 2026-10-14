{{-- Footer (navigation doc §2): categories, help and legal pages that are published, theme toggle,
     © with the configured name, the Dev Talk line (Q-UX-1, configurable) and the GeoNames credit. --}}
@php
    $help = collect(['safety', 'prohibited-items', 'faq', 'contact', 'about'])->filter(fn ($slug) => $footerPages->has($slug));
    $legal = collect(['terms', 'privacy'])->filter(fn ($slug) => $footerPages->has($slug));
    $other = $footerPages->keys()->diff($help)->diff($legal);
    // Not configured = the approved default; configured empty = hidden (Q-UX-1).
    $parentUrl = config('marketplace.brand.parent_url') ?? __('layout.parent.url');
    $parentName = config('marketplace.brand.parent_name') ?? __('layout.parent.name');
@endphp
<footer class="mt-12 border-t border-line bg-surface">
    <div class="mx-auto grid max-w-(--container-page) gap-8 px-4 py-10 sm:grid-cols-2 lg:grid-cols-4 lg:px-6">
        <div class="space-y-3">
            @include('partials.layout.brand')
            <p class="max-w-xs text-small text-fg-subtle">{{ __('layout.tagline') }}</p>
            @include('partials.layout.theme-toggle')
        </div>
        @if ($currentCountry && $navCategories->isNotEmpty())
            <nav aria-label="{{ __('layout.categories') }}">
                <h2 class="mb-2 text-ui font-semibold text-fg">{{ __('layout.categories') }}</h2>
                <ul class="space-y-1.5">
                    @foreach ($navCategories as $category)
                        <li><a class="text-ui text-fg-muted hover:text-fg" href="{{ route('ads.category', ['cc' => strtolower($currentCountry->iso2), 'category' => $category->slug]) }}">{{ $category->name }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif
        @if ($help->isNotEmpty() || $other->isNotEmpty())
            <nav aria-label="{{ __('layout.help') }}">
                <h2 class="mb-2 text-ui font-semibold text-fg">{{ __('layout.help') }}</h2>
                <ul class="space-y-1.5">
                    @foreach ($help->merge($other) as $slug)
                        <li><a class="text-ui text-fg-muted hover:text-fg" href="{{ route('page.show', $slug) }}">{{ $footerPages[$slug] }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif
        @if ($legal->isNotEmpty())
            <nav aria-label="{{ __('layout.legal') }}">
                <h2 class="mb-2 text-ui font-semibold text-fg">{{ __('layout.legal') }}</h2>
                <ul class="space-y-1.5">
                    @foreach ($legal as $slug)
                        <li><a class="text-ui text-fg-muted hover:text-fg" href="{{ route('page.show', $slug) }}">{{ $footerPages[$slug] }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif
    </div>
    <div class="border-t border-line">
        <div class="mx-auto flex max-w-(--container-page) flex-col gap-1 px-4 py-4 text-small text-fg-subtle sm:flex-row sm:items-center sm:justify-between lg:px-6">
            <p>© {{ now()->year }} {{ config('app.name') }}@if ($parentUrl) · <a href="{{ $parentUrl }}" rel="noopener" class="underline underline-offset-2 hover:text-fg">{{ $parentName }}</a>@endif</p>
            <p>{{ config('marketplace.geo.attribution') }}</p>
        </div>
    </div>
</footer>
