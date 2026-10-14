{{-- Search filters (components §5.3, global UX §5). One GET form, used in the desktop sidebar and the
     phone drawer ($prefix keeps ids unique). Works without JavaScript; resources/js/ui/search.js
     submits on select changes, clears dependent places and enables price fields once a currency is chosen. --}}
@php
    $state = $page->state();
    $links = $page->categoryLinks();
    $places = $page->locationOptions();
    $currencies = $page->currencies();
    $currency = $criteria->currency ?? $country?->default_currency;
    $priceLocked = $country === null && ($state['currency'] ?? null) === null;
    $label = 'block text-small font-semibold uppercase tracking-wide text-fg-subtle';
    $control = 'mt-1.5 h-10 w-full rounded-control border border-control bg-surface px-3 text-fg';
@endphp
<form method="GET" action="{{ $page->formAction() }}" data-search-form class="space-y-6">
    @foreach (['q', 'sort', 'view'] as $keep)
        @isset($state[$keep])<input type="hidden" name="{{ $keep }}" value="{{ $state[$keep] }}">@endisset
    @endforeach
    @if ($country === null && isset($state['category']))<input type="hidden" name="category" value="{{ $state['category'] }}">@endif

    {{-- Category: the current branch only --}}
    @if ($links['items'] !== [] || $links['back'])
        <section>
            <h3 class="{{ $label }}">{{ __('public.categories') }}</h3>
            <ul class="mt-2 space-y-0.5 text-ui">
                @if ($links['back'])
                    <li><a href="{{ $links['back']['url'] }}" class="flex items-center gap-1 rounded-control px-2 py-1.5 text-fg-muted hover:bg-surface-hover hover:text-fg"><x-ui.icon name="chevron-left" size="16" />{{ $links['back']['label'] }}</a></li>
                @endif
                @foreach ($links['items'] as $item)
                    <li><a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif
                        class="block rounded-control px-2 py-1.5 text-fg hover:bg-surface-hover aria-[current]:bg-surface-muted aria-[current]:font-semibold">{{ $item['label'] }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Location: dependent selects (country pages) --}}
    @if ($country !== null && $places['region'] !== [])
        <section class="space-y-3">
            <h3 class="{{ $label }}">{{ __('search.location') }}</h3>
            @if ($places['levels'] > 0)
                <label class="block text-ui text-fg">{{ $country->label('region') }}
                    <select name="region" class="{{ $control }}" data-autosubmit data-clears="subregion,city">
                        <option value="">{{ __('public.any') }}</option>
                        @foreach ($places['region'] as $slug => $name)<option value="{{ $slug }}" @selected(($state['region'] ?? null) === $slug)>{{ $name }}</option>@endforeach
                    </select>
                </label>
            @endif
            @if ($places['subregion'] !== [])
                <label class="block text-ui text-fg">{{ $country->label('subregion') }}
                    <select name="subregion" class="{{ $control }}" data-autosubmit data-clears="city">
                        <option value="">{{ __('public.any') }}</option>
                        @foreach ($places['subregion'] as $slug => $name)<option value="{{ $slug }}" @selected(($state['subregion'] ?? null) === $slug)>{{ $name }}</option>@endforeach
                    </select>
                </label>
            @endif
            @if ($places['city'] !== [])
                <label class="block text-ui text-fg">{{ $country->label('city') }}
                    <select name="city" class="{{ $control }}" data-autosubmit>
                        <option value="">{{ __('public.any') }}</option>
                        @foreach ($places['city'] as $slug => $name)<option value="{{ $slug }}" @selected(($state['city'] ?? null) === $slug)>{{ $name }}</option>@endforeach
                    </select>
                </label>
            @endif
        </section>
    @endif

    {{-- Price: one currency (global UX §5) --}}
    <section class="space-y-2">
        <h3 class="{{ $label }}">{{ __('search.price') }}</h3>
        @if ($country !== null)
            <p class="text-small text-fg-subtle">{{ __('search.prices_in', ['currency' => $currency]) }}</p>
        @else
            <label class="block text-ui text-fg">{{ __('public.currency') }}
                <select name="currency" class="{{ $control }}" data-price-currency>
                    <option value="">{{ __('search.choose_currency') }}</option>
                    @foreach ($currencies as $code => $name)<option value="{{ $code }}" @selected(($state['currency'] ?? null) === $code)>{{ $name }}</option>@endforeach
                </select>
            </label>
            @if ($priceLocked)<p class="text-small text-fg-subtle" id="{{ $prefix }}-price-help">{{ __('search.price_needs_currency') }}</p>@endif
        @endif
        <div class="grid grid-cols-2 gap-2">
            <label class="text-small text-fg-muted">{{ __('search.min') }}
                <input type="text" inputmode="decimal" name="min_price" value="{{ $state['min_price'] ?? '' }}" class="{{ $control }} tabular" data-price-field @disabled($priceLocked) @if ($priceLocked) aria-describedby="{{ $prefix }}-price-help" @endif>
            </label>
            <label class="text-small text-fg-muted">{{ __('search.max') }}
                <input type="text" inputmode="decimal" name="max_price" value="{{ $state['max_price'] ?? '' }}" class="{{ $control }} tabular" data-price-field @disabled($priceLocked) @if ($priceLocked) aria-describedby="{{ $prefix }}-price-help" @endif>
            </label>
        </div>
    </section>

    {{-- Ad type and condition --}}
    <section class="space-y-3">
        <label class="block text-ui text-fg">{{ __('public.type') }}
            <select name="type" class="{{ $control }}" data-autosubmit>
                <option value="">{{ __('public.any') }}</option>
                @foreach (__('public.types') as $value => $text)<option value="{{ $value }}" @selected(($state['type'] ?? null) === $value)>{{ $text }}</option>@endforeach
            </select>
        </label>
        <label class="block text-ui text-fg">{{ __('public.condition') }}
            <select name="condition" class="{{ $control }}" data-autosubmit>
                <option value="">{{ __('public.any') }}</option>
                @foreach (__('public.conditions') as $value => $text)<option value="{{ $value }}" @selected(($state['condition'] ?? null) === $value)>{{ $text }}</option>@endforeach
            </select>
        </label>
    </section>

    {{-- Category filters from the category schema --}}
    @foreach ($filters as $field)
        @php
            $current = $state['a'][$field['key']] ?? null;
            $name = 'a['.$field['key'].']';
            $options = $field['options'] ?? [];
            if ($field['depends_on'] !== null) {
                // A dependent list (Model under Make) shows only when one parent value is chosen.
                $parent = $state['a'][$field['depends_on']] ?? null;
                $parent = is_array($parent) ? (count($parent) === 1 ? reset($parent) : null) : (is_string($parent) && ! str_contains($parent, ',') ? $parent : null);
                $options = $parent === null ? [] : array_values(array_filter($options, fn ($o) => $o['parent'] === $parent));
            }
            $chosen = is_array($current) ? array_map('strval', $current) : (is_string($current) ? explode(',', $current) : []);
        @endphp
        @if (in_array($field['type'], ['select', 'multiselect'], true))
            @if ($options !== [])
                <fieldset>
                    <legend class="{{ $label }}">{{ $field['label'] }}</legend>
                    <div class="mt-2 space-y-1.5">
                        @foreach (array_slice($options, 0, 8) as $option)
                            <x-ui.checkbox :label="$option['label']" :name="$name.'[]'" :id="$prefix.'-'.$field['key'].'-'.$loop->index" :value="$option['value']" :checked="in_array($option['value'], $chosen, true)" data-autosubmit />
                        @endforeach
                        @if (count($options) > 8)
                            <details @if (array_intersect(array_column(array_slice($options, 8), 'value'), $chosen) !== []) open @endif>
                                <summary class="cursor-pointer text-small font-medium text-fg-muted hover:text-fg">{{ __('search.more_options', ['count' => count($options) - 8]) }}</summary>
                                <div class="mt-1.5 max-h-64 space-y-1.5 overflow-y-auto">
                                    @foreach (array_slice($options, 8) as $option)
                                        <x-ui.checkbox :label="$option['label']" :name="$name.'[]'" :id="$prefix.'-'.$field['key'].'-m'.$loop->index" :value="$option['value']" :checked="in_array($option['value'], $chosen, true)" data-autosubmit />
                                    @endforeach
                                </div>
                            </details>
                        @endif
                    </div>
                </fieldset>
            @endif
        @elseif ($field['type'] === 'boolean')
            <x-ui.checkbox :label="$field['label']" :name="$name" :id="$prefix.'-'.$field['key']" value="1" :checked="$current === '1' || $current === 'true'" data-autosubmit />
        @else
            <fieldset>
                <legend class="{{ $label }}">{{ $field['label'] }}@if ($field['unit']) ({{ $field['unit'] }})@endif</legend>
                <div class="mt-1.5 grid grid-cols-2 gap-2">
                    <label class="text-small text-fg-muted">{{ __('search.min') }}
                        <input type="text" inputmode="decimal" name="{{ $name }}[min]" value="{{ is_array($current) ? ($current['min'] ?? '') : '' }}" class="{{ $control }} tabular">
                    </label>
                    <label class="text-small text-fg-muted">{{ __('search.max') }}
                        <input type="text" inputmode="decimal" name="{{ $name }}[max]" value="{{ is_array($current) ? ($current['max'] ?? '') : '' }}" class="{{ $control }} tabular">
                    </label>
                </div>
                @if ($field['units'] !== [])
                    <label class="mt-2 block text-small text-fg-muted">{{ __('search.unit') }}
                        <select name="{{ $name }}[unit]" class="{{ $control }}">
                            @foreach ($field['units'] as $unit)<option value="{{ $unit['code'] }}" @selected(is_array($current) && ($current['unit'] ?? null) === $unit['code'])>{{ $unit['label'] }}</option>@endforeach
                        </select>
                    </label>
                @endif
            </fieldset>
        @endif
    @endforeach

    <div class="flex gap-2 border-t border-line pt-4">
        <x-ui.button type="submit" class="flex-1">{{ __('search.apply') }}</x-ui.button>
        <x-ui.button variant="secondary" :href="$page->clearUrl()">{{ __('search.clear_all') }}</x-ui.button>
    </div>
</form>
