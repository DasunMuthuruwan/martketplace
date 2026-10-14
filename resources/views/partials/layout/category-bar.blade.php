{{-- Category bar under the header (desktop, navigation doc §2): top-level categories of the visitor's
     country. Mobile shows category chips on the home page instead. --}}
@if ($currentCountry && $navCategories->isNotEmpty())
    <nav aria-label="{{ __('layout.categories') }}" class="hidden border-b border-line bg-surface lg:block">
        <ul class="mx-auto flex h-11 max-w-(--container-page) items-center gap-1 overflow-x-auto px-6">
            @foreach ($navCategories as $category)
                @php($url = route('ads.category', ['cc' => strtolower($currentCountry->iso2), 'category' => $category->slug]))
                <li>
                    <a href="{{ $url }}" @if (url()->current() === $url) aria-current="page" @endif
                        class="inline-flex h-9 items-center whitespace-nowrap rounded-control px-3 text-ui text-fg-muted hover:bg-surface-hover hover:text-fg aria-[current]:font-semibold aria-[current]:text-fg">{{ $category->name }}</a>
                </li>
            @endforeach
        </ul>
    </nav>
@endif
