{{-- Category bar under the header (desktop, navigation doc §2): top-level categories of the visitor's
     country; the one being browsed (its own page or a subcategory) is highlighted. Mobile shows
     category tiles on the home page instead. --}}
@if ($currentCountry && $navCategories->isNotEmpty())
    @php($activeSlug = is_string(request()->route('category')) ? request()->route('category') : null)
    <nav aria-label="{{ __('layout.categories') }}" class="hidden border-b border-line bg-surface lg:block">
        <ul class="mx-auto flex h-11 max-w-(--container-page) items-center gap-1 overflow-x-auto px-6">
            @foreach ($navCategories as $category)
                @php($url = route('ads.category', ['cc' => strtolower($currentCountry->iso2), 'category' => $category->slug]))
                <li>
                    <a href="{{ $url }}" @if (url()->current() === $url) aria-current="page" @elseif ($activeSlug === $category->slug) aria-current="true" @endif
                        class="inline-flex h-9 items-center whitespace-nowrap rounded-full px-4 text-ui font-medium text-fg-muted transition-colors duration-(--duration-fast) hover:bg-surface-hover hover:text-fg aria-[current]:bg-primary aria-[current]:text-primary-fg">{{ $category->name }}</a>
                </li>
            @endforeach
        </ul>
    </nav>
@endif
