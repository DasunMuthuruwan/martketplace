@extends('layouts.wizard')

@section('step_title', __('posting.location.title'))
@section('step_intro', __('posting.location.intro'))

@section('step')
    {{-- Location (global UX §2): the country's own terms and number of levels; each level loads after
         the one before it (the page reloads with GET); Continue saves. --}}
    @php
        $action = $listing ? route('listings.update', [$listing, 'location']) : route('listings.store');
        $reload = $listing ? route('listings.edit', [$listing, 'location']) : route('listings.create');
        $levels = $country->region_levels;
    @endphp

    @if ($category)
        <p class="mb-5 flex flex-wrap items-center gap-2 text-ui text-fg-muted">
            {{ __('posting.location.category') }} <x-ui.chip>{{ $category->parent?->name }} › {{ $category->name }}</x-ui.chip>
            <a href="{{ $listing ? route('listings.edit', [$listing, 'category']) : route('listings.create') }}" class="font-medium text-fg underline underline-offset-2">{{ __('posting.change') }}</a>
        </p>
    @endif

    <x-ui.form-errors class="mb-4" />

    <form method="POST" action="{{ $action }}" novalidate class="space-y-4" data-posting-form>
        @csrf
        <input type="hidden" name="category_id" value="{{ $category?->id }}">
        @if ($listing === null)<input type="hidden" name="category" value="{{ $category?->id }}">@endif

        <x-ui.select :label="__('geo.labels.country')" name="country_id" required :options="$countries->pluck('name', 'id')->all()" :selected="$country->id"
            data-reload="{{ $reload }}" data-clears="region_id,subregion_id,city_id,locality_id" />

        @if ($levels > 0)
            <x-ui.select :label="$country->label('region')" name="region_id" :required="$country->require_region" :placeholder="__('posting.location.choose')"
                :options="$regions->pluck('name', 'id')->all()" :selected="$values['region_id']" data-reload="{{ $reload }}" data-clears="subregion_id,city_id,locality_id" />
        @endif
        @if ($levels >= 2 && $values['region_id'])
            <x-ui.select :label="$country->label('subregion')" name="subregion_id" :required="$country->require_region" :placeholder="__('posting.location.choose')"
                :options="$subregions->pluck('name', 'id')->all()" :selected="$values['subregion_id']" data-reload="{{ $reload }}" data-clears="city_id,locality_id" />
        @endif

        @if ($cities->isNotEmpty())
            <x-ui.combobox :label="$country->label('city')" name="city_id" :required="$country->require_city" :placeholder="__('posting.location.type_town')"
                :options="$cities->pluck('name', 'id')->all()" :selected="$values['city_id']" data-reload-on-change="{{ $reload }}" />
        @elseif ($levels > 0)
            <p class="text-small text-fg-subtle">{{ __('posting.location.choose_region_first', ['label' => mb_strtolower($country->label($levels >= 2 ? 'subregion' : 'region'))]) }}</p>
        @endif

        @if ($localities->isNotEmpty())
            <x-ui.select :label="$country->label('locality')" name="locality_id" :placeholder="__('posting.location.optional')"
                :options="$localities->pluck('name', 'id')->all()" :selected="$values['locality_id']" />
        @else
            <x-ui.input :label="$country->label('locality')" name="locality_text" :value="$values['locality_text']" maxlength="80"
                :hint="__('posting.location.no_address')" />
        @endif

        <details class="rounded-card border border-line p-3" @if (old('proposed_city')) open @endif>
            <summary class="cursor-pointer text-ui font-medium text-fg">{{ __('posting.location.not_listed') }}</summary>
            <div class="mt-3 space-y-2">
                <p class="text-small text-fg-muted">{{ __('posting.location.not_listed_help') }}</p>
                <x-ui.input :label="__('posting.location.town_name')" name="proposed_city" :value="old('proposed_city')" maxlength="80" />
            </div>
        </details>

        {{-- Without JavaScript: reload the lists after choosing a country or region. --}}
        <noscript><button type="submit" formmethod="GET" formaction="{{ $reload }}" formnovalidate class="text-ui underline">{{ __('posting.location.refresh') }}</button></noscript>

        <div class="flex justify-between gap-2 border-t border-line pt-4 max-lg:fixed max-lg:inset-x-0 max-lg:bottom-0 max-lg:z-30 max-lg:bg-surface max-lg:px-4 max-lg:pb-[calc(12px+env(safe-area-inset-bottom))] max-lg:pt-3">
            <x-ui.button variant="secondary" :href="$listing ? route('listings.edit', [$listing, 'category']) : route('listings.create')" icon="chevron-left">{{ __('posting.back') }}</x-ui.button>
            <x-ui.button type="submit" icon-right="chevron-right">{{ __('posting.continue') }}</x-ui.button>
        </div>
    </form>

    @if ($countryChange)
        {{-- GLB-11: values that depend on the country are cleared, never converted. --}}
        <x-ui.modal id="country-change" :title="__('posting.country_change.title', ['country' => $country->name])" :open="true">
            <p class="mb-2 text-ui text-fg-muted">{{ __('posting.country_change.intro') }}</p>
            <ul class="list-disc space-y-1 pl-5 text-ui text-fg">
                @foreach ($countryChange as $label)<li>{{ $label }}</li>@endforeach
            </ul>
            <p class="mt-3 text-small text-fg-subtle">{{ __('listings.country_change.confirm') }}</p>
            <x-slot:footer>
                <x-ui.button variant="secondary" :href="route('listings.edit', [$listing, 'location'])">{{ __('posting.country_change.keep', ['country' => $listing->country->name]) }}</x-ui.button>
                <form method="POST" action="{{ $action }}">
                    @csrf
                    @foreach (old() as $key => $value)
                        @if (is_string($value) && ! in_array($key, ['_token', 'confirm_country_change'], true))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                    @endforeach
                    <input type="hidden" name="confirm_country_change" value="1">
                    <x-ui.button type="submit">{{ __('posting.country_change.change', ['country' => $country->name]) }}</x-ui.button>
                </form>
            </x-slot:footer>
        </x-ui.modal>
    @endif
@endsection
