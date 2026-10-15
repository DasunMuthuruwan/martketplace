{{-- Countries (GLB-01..05): the list beside one country's activation checklist and settings. Only the
     Owner (settings.manage) activates; a country needs a currency, its regions and towns first. --}}
<div data-shortcuts>
    <x-admin.page-header :title="__('admin.nav.countries')" :description="__('admin.locations.countries_intro')">
        @include('livewire.admin.partials.shortcuts', ['keys' => ['j' => 'next', 'k' => 'previous']])
    </x-admin.page-header>
    @include('livewire.admin.partials.locations-tabs', ['active' => 'admin.countries'])

    @if (session('status'))
        <x-ui.alert variant="success" role="status" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    <div class="grid items-start gap-4 lg:grid-cols-[minmax(260px,340px)_minmax(0,1fr)]">
        <div @class(['min-w-0 space-y-3', 'max-lg:hidden' => $selected])>
            <div class="grid grid-cols-[minmax(0,1fr)_auto] items-end gap-2">
                <x-ui.input type="search" :label="__('admin.locations.search_countries')" id="cn-q" wire:model.live.debounce.400ms="q" maxlength="60" />
                <x-ui.select :label="__('admin.reports.status')" id="cn-active" wire:model.live="active" :placeholder="__('public.any')"
                    :options="['1' => __('locations.active'), '0' => __('locations.inactive')]" />
            </div>
            <ul class="divide-y divide-line overflow-hidden rounded-card border border-line bg-surface" aria-label="{{ __('admin.nav.countries') }}">
                @forelse ($countries as $country)
                    @php($current = $selected?->id === $country->id)
                    <li wire:key="country-{{ $country->id }}">
                        <button type="button" wire:click="select({{ $country->id }})" data-queue-item @if ($current) aria-current="true" @endif
                            @class([
                                'flex w-full items-center gap-3 px-3 py-2.5 text-left hover:bg-surface-hover',
                                'bg-surface-muted shadow-[inset_3px_0_0_var(--fg)]' => $current,
                            ])>
                            <x-ui.flag :code="$country->iso2" class="text-h3" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-ui font-medium text-fg">{{ $country->name }}</span>
                                <span class="block text-small text-fg-subtle"><span class="font-mono">{{ $country->iso2 }}</span> · {{ $country->default_currency ?? '–' }}</span>
                            </span>
                            @if ($country->is_active)<x-ui.badge variant="success">{{ __('locations.active') }}</x-ui.badge>@endif
                        </button>
                    </li>
                @empty
                    <li class="px-3 py-6 text-center text-small text-fg-subtle">{{ __('admin.locations.no_countries') }}</li>
                @endforelse
            </ul>
            {{ $countries->links('livewire.admin.pagination') }}
        </div>

        <div class="min-w-0">
            @if ($selected)
                <x-ui.button variant="ghost" size="sm" icon="chevron-left" wire:click="close" class="mb-2 lg:hidden">{{ __('admin.locations.back_countries') }}</x-ui.button>
                <article aria-labelledby="country-title" class="space-y-5 rounded-card border border-line bg-surface p-4 lg:p-5" wire:key="country-pane-{{ $selected->id }}">
                    <header class="flex items-center gap-3">
                        <x-ui.flag :code="$selected->iso2" class="text-h1" />
                        <div class="min-w-0">
                            <h2 id="country-title" class="text-h2 font-semibold">{{ $selected->name }}</h2>
                            <p class="flex items-center gap-2 text-small text-fg-subtle">
                                <span class="font-mono">{{ $selected->iso2 }}</span>
                                <x-ui.badge :variant="$selected->is_active ? 'success' : 'outline'">{{ $selected->is_active ? __('locations.active') : __('locations.inactive') }}</x-ui.badge>
                            </p>
                        </div>
                    </header>

                    <section aria-labelledby="country-checklist" class="rounded-control border border-line p-3">
                        <h3 id="country-checklist" class="mb-2 text-ui font-semibold">{{ __('locations.checklist') }}</h3>
                        <ul class="space-y-1.5 text-small">
                            @foreach ($checklist as $item => $state)
                                <li class="flex items-start gap-2">
                                    @if ($state['ok'])
                                        <x-ui.icon name="circle-check" size="16" class="mt-px text-success" /><span class="sr-only">{{ __('admin.locations.done') }}:</span>
                                    @elseif ($state['required'])
                                        <x-ui.icon name="circle-x" size="16" class="mt-px text-danger" /><span class="sr-only">{{ __('admin.locations.missing') }}:</span>
                                    @else
                                        <x-ui.icon name="info" size="16" class="mt-px text-fg-subtle" /><span class="sr-only">{{ __('locations.reminder') }}:</span>
                                    @endif
                                    <span @class(['text-fg' => $state['required'], 'text-fg-muted' => ! $state['required']])>{{ __('locations.checks.'.$item) }}@if (! $state['required']) <span class="text-fg-subtle">({{ __('locations.reminder') }})</span>@endif</span>
                                </li>
                            @endforeach
                        </ul>
                        @error('activate')<x-ui.alert variant="danger" role="alert" class="mt-3">{{ $message }}</x-ui.alert>@enderror
                        <div class="mt-3 border-t border-line pt-3">
                            @if ($canActivate)
                                @if ($selected->is_active)
                                    <x-ui.button size="sm" variant="secondary" icon="eye-off" wire:click="toggleActive" wire:confirm="{{ __('locations.confirm_deactivate') }}">{{ __('locations.deactivate') }}</x-ui.button>
                                @else
                                    <x-ui.button size="sm" icon="globe" wire:click="toggleActive" wire:confirm="{{ __('locations.confirm_activate') }}">{{ __('locations.activate') }}</x-ui.button>
                                @endif
                            @else
                                <p class="text-small text-fg-subtle">{{ __('locations.owner_activates') }}</p>
                            @endif
                        </div>
                    </section>

                    <form wire:submit="save" class="space-y-5">
                        <x-ui.input :label="__('admin.name')" id="cn-name" wire:model="name" maxlength="80" required :error="$errors->first('name')" />

                        <fieldset class="space-y-2">
                            <legend class="text-ui font-medium text-fg">{{ __('locations.labels') }}</legend>
                            <p class="text-small text-fg-subtle">{{ __('locations.labels_help') }}</p>
                            <div class="grid gap-3 sm:grid-cols-4">
                                @foreach (['region', 'subregion', 'city', 'locality'] as $level)
                                    <x-ui.input :label="__('geo.labels.'.$level)" id="cn-{{ $level }}" wire:model="{{ $level }}Label" maxlength="40" />
                                @endforeach
                            </div>
                        </fieldset>

                        <div class="grid gap-3 sm:grid-cols-3">
                            <x-ui.select :label="__('locations.currency')" id="cn-currency" wire:model="currency" required placeholder="" :selected="$currency" :error="$errors->first('currency')"
                                :options="$currencies->mapWithKeys(fn ($c) => [$c->code => $c->code.' · '.$c->name])->all()" />
                            <x-ui.input :label="__('locations.number_locale')" id="cn-locale" wire:model="numberLocale" maxlength="15" required :error="$errors->first('numberLocale')" />
                            <x-ui.input :label="__('locations.timezone')" id="cn-tz" wire:model="timezone" maxlength="40" :error="$errors->first('timezone')" />
                            <x-ui.select :label="__('locations.region_levels')" id="cn-levels" wire:model="regionLevels" :disabled="$hasRegions" :selected="$regionLevels"
                                :hint="$hasRegions ? __('locations.levels_locked') : null" :error="$errors->first('regionLevels')" :options="['0' => '0', '1' => '1', '2' => '2']" />
                            <x-ui.input type="number" :label="__('admin.catalogue.sort')" id="cn-sort" wire:model="sortOrder" min="0" max="65535" />
                        </div>
                        <div class="flex flex-wrap gap-x-6 gap-y-2">
                            <x-ui.checkbox type="switch" :label="__('locations.require_region')" id="cn-req-region" wire:model="requireRegion" :checked="$requireRegion" />
                            <x-ui.checkbox type="switch" :label="__('locations.require_city')" id="cn-req-city" wire:model="requireCity" :checked="$requireCity" />
                        </div>

                        <fieldset class="space-y-3 rounded-control border border-line p-3">
                            <legend class="px-1 text-ui font-medium text-fg">{{ __('locations.area_units') }}</legend>
                            <div class="flex flex-wrap gap-x-5 gap-y-2">
                                @foreach ($units as $unit)
                                    <x-ui.checkbox :label="$unit->name.' ('.$unit->symbol.')'" id="cn-unit-{{ $unit->id }}" wire:model="areaUnits" :value="$unit->id"
                                        :checked="in_array($unit->id, array_map('intval', $areaUnits), true)" />
                                @endforeach
                            </div>
                            @error('areaUnits.*')<p class="text-small text-danger">{{ $message }}</p>@enderror
                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-ui.select :label="__('locations.default_area_unit')" id="cn-default-unit" wire:model="defaultAreaUnit" :placeholder="__('admin.catalogue.none')" :selected="$defaultAreaUnit"
                                    :error="$errors->first('defaultAreaUnit')" :options="$units->mapWithKeys(fn ($u) => [$u->id => $u->name])->all()" />
                                <x-ui.select :label="__('locations.land_price_unit')" id="cn-land-unit" wire:model="landPriceUnit" :placeholder="__('admin.catalogue.none')" :selected="$landPriceUnit"
                                    :options="$units->mapWithKeys(fn ($u) => [$u->id => $u->name])->all()" />
                            </div>
                        </fieldset>

                        <div class="flex gap-2 border-t border-line pt-4">
                            <x-ui.button type="submit" icon="check">{{ __('staff.manage.save') }}</x-ui.button>
                            <x-ui.button variant="ghost" wire:click="close">{{ __('admin.catalogue.cancel') }}</x-ui.button>
                        </div>
                    </form>
                </article>
            @else
                <x-ui.empty-state icon="globe" :title="__('admin.locations.choose_country')" class="max-lg:hidden">{{ __('admin.moderation.choose_text') }}</x-ui.empty-state>
            @endif
        </div>
    </div>
</div>
