{{-- Places of one country (ADM-03, GLB-14..16): regions (and subregions) on the left, towns on the right with
     their localities, editing, and merging a duplicate town after a preview. Labels come from the country. --}}
<div>
    <x-admin.page-header :title="__('admin.nav.locations')" :description="__('admin.locations.places_intro')" />
    @include('livewire.admin.partials.locations-tabs', ['active' => 'admin.locations'])

    @if (session('status'))
        <x-ui.alert variant="success" role="status" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    <div class="mb-5 max-w-sm">
        <x-ui.select :label="__('geo.labels.country')" id="loc-country" wire:model.live="countryId" :placeholder="__('admin.locations.choose_country')" :selected="$countryId"
            :options="$countries->mapWithKeys(fn ($c) => [$c->id => $c->iso2.' · '.$c->name.($c->is_active ? '' : ' ('.__('locations.inactive').')')])->all()" />
    </div>

    @if (! $country)
        <x-ui.empty-state icon="map-pin" :title="__('admin.locations.choose_country')">{{ __('admin.locations.choose_country_text') }}</x-ui.empty-state>
    @else
        @php($cityRegionLabel = $country->label($country->region_levels >= 2 ? 'subregion' : 'region'))
        <div @class(['grid items-start gap-4', 'xl:grid-cols-[minmax(0,380px)_minmax(0,1fr)]' => $country->region_levels > 0])>
            {{-- Regions --}}
            @if ($country->region_levels > 0)
                <section aria-labelledby="regions-title" class="rounded-card border border-line bg-surface">
                    <div class="flex items-center justify-between gap-2 border-b border-line px-4 py-3">
                        <h2 id="regions-title" class="text-h3 font-semibold">{{ $country->label('region') }} <span class="font-normal text-fg-subtle">· {{ $level1->count() }}</span></h2>
                        <x-ui.button size="sm" variant="secondary" icon="plus" wire:click="createRegion">{{ __('locations.add', ['label' => $country->label('region')]) }}</x-ui.button>
                    </div>
                    @error('regions')<x-ui.alert variant="danger" role="alert" class="m-3">{{ $message }}</x-ui.alert>@enderror

                    @if ($regionEditing)
                        <form wire:submit="saveRegion" class="space-y-3 border-b border-line bg-surface-muted p-4">
                            <h3 class="text-ui font-semibold">{{ $regionId ? __('admin.catalogue.edit_named', ['name' => $regionName]) : __('locations.add', ['label' => $regionParent ? $country->label('subregion') : $country->label('region')]) }}@if ($regionParent) · {{ $regionNames[(int) $regionParent] ?? '' }}@endif</h3>
                            <x-ui.input :label="__('admin.name')" id="r-name" wire:model="regionName" maxlength="100" required :error="$errors->first('regionName') ?: $errors->first('regionParent')" />
                            <div class="grid grid-cols-2 gap-3">
                                <x-ui.input :label="__('locations.code')" id="r-code" wire:model="regionCode" maxlength="40" />
                                <x-ui.input type="number" :label="__('admin.catalogue.sort')" id="r-sort" wire:model="regionSort" min="0" max="65535" />
                            </div>
                            <x-ui.checkbox type="switch" :label="__('admin.catalogue.active')" id="r-active" wire:model="regionActive" :checked="$regionActive" />
                            <div class="flex gap-2">
                                <x-ui.button type="submit" size="sm" icon="check">{{ __('staff.manage.save') }}</x-ui.button>
                                <x-ui.button size="sm" variant="ghost" wire:click="closeRegion">{{ __('admin.catalogue.cancel') }}</x-ui.button>
                            </div>
                        </form>
                    @endif

                    @if ($level1->isEmpty())
                        <p class="px-4 py-6 text-center text-small text-fg-subtle">{{ __('admin.locations.no_regions') }}</p>
                    @else
                        <ul class="max-h-[36rem] divide-y divide-line overflow-y-auto">
                            @foreach ($level1 as $region)
                                <li wire:key="region-{{ $region->id }}">
                                    @include('livewire.admin.partials.region-row', ['region' => $region, 'sub' => false])
                                    @if ($level2->has($region->id))
                                        <ul class="divide-y divide-line border-t border-line bg-surface-muted/40">
                                            @foreach ($level2->get($region->id) as $subregion)
                                                <li wire:key="region-{{ $subregion->id }}" class="pl-5">@include('livewire.admin.partials.region-row', ['region' => $subregion, 'sub' => true])</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif

            {{-- Towns --}}
            <div class="min-w-0 space-y-4">
                @if ($cityEditing)
                    <form wire:submit="saveCity" class="space-y-3 rounded-card border border-line bg-surface p-4">
                        <h3 class="text-h3 font-semibold">{{ $cityId ? __('admin.catalogue.edit_named', ['name' => $cityName]) : __('locations.add', ['label' => $country->label('city')]) }}</h3>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-ui.input :label="__('admin.name')" id="ci-name" wire:model="cityName" maxlength="100" required :error="$errors->first('cityName')" />
                            @if ($cityRegions->isNotEmpty())
                                <x-ui.select :label="$cityRegionLabel" id="ci-region" wire:model="cityRegion" required placeholder="" :selected="$cityRegion"
                                    :hint="$cityId ? __('locations.move_help') : null" :error="$errors->first('cityRegion')"
                                    :options="$cityRegions->mapWithKeys(fn ($r) => [$r->id => $r->name])->all()" />
                            @endif
                        </div>
                        <x-ui.checkbox type="switch" :label="__('admin.catalogue.active')" id="ci-active" wire:model="cityActive" :checked="$cityActive" />
                        <div class="flex gap-2">
                            <x-ui.button type="submit" size="sm" icon="check">{{ __('staff.manage.save') }}</x-ui.button>
                            <x-ui.button size="sm" variant="ghost" wire:click="closeCity">{{ __('admin.catalogue.cancel') }}</x-ui.button>
                        </div>
                    </form>
                @endif

                @if ($mergeFrom)
                    @include('livewire.admin.partials.city-merge')
                @endif

                @if ($localityCity)
                    <section aria-labelledby="loc-title" class="rounded-card border border-line bg-surface p-4">
                        <div class="mb-3 flex items-center justify-between gap-2">
                            <h3 id="loc-title" class="text-h3 font-semibold">{{ $country->label('locality') }} · {{ $localityCity->name }}</h3>
                            <x-ui.button size="sm" variant="ghost" icon="x" wire:click="hideLocalities" :label="__('admin.catalogue.cancel')" />
                        </div>
                        @error('localities')<x-ui.alert variant="danger" role="alert" class="mb-3">{{ $message }}</x-ui.alert>@enderror
                        @if ($localityCity->localities->isEmpty())
                            <p class="mb-3 text-small text-fg-subtle">{{ __('locations.no_localities') }}</p>
                        @else
                            <ul class="mb-3 flex flex-wrap gap-2">
                                @foreach ($localityCity->localities as $locality)
                                    <li wire:key="locality-{{ $locality->id }}" @class(['inline-flex items-center gap-1 rounded-full border border-line py-0.5 pl-3 pr-1 text-small', 'text-fg-subtle line-through' => ! $locality->is_active])>
                                        {{ $locality->name }}
                                        <x-ui.button size="sm" variant="ghost" icon="pencil" class="size-7" wire:click="editLocality({{ $locality->id }})" :label="__('admin.catalogue.edit_named', ['name' => $locality->name])" />
                                        <x-ui.button size="sm" variant="ghost" icon="x" class="size-7" wire:click="deleteLocality({{ $locality->id }})" wire:confirm="{{ __('admin.catalogue.confirm_delete') }}"
                                            :label="__('admin.catalogue.delete_named', ['name' => $locality->name])" />
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <form wire:submit="saveLocality" class="flex flex-wrap items-end gap-3">
                            <x-ui.input :label="$localityId ? __('admin.catalogue.edit_named', ['name' => $localityName]) : __('locations.add', ['label' => $country->label('locality')])" id="lo-name"
                                wire:model="localityName" maxlength="100" required class="min-w-56" :error="$errors->first('localityName')" />
                            <x-ui.checkbox type="switch" :label="__('admin.catalogue.active')" id="lo-active" wire:model="localityActive" :checked="$localityActive" class="pb-2.5" />
                            <x-ui.button type="submit" size="sm" :icon="$localityId ? 'check' : 'plus'" class="mb-1">{{ $localityId ? __('staff.manage.save') : __('admin.catalogue.add_option') }}</x-ui.button>
                            @if ($localityId)<x-ui.button size="sm" variant="ghost" wire:click="closeLocality" class="mb-1">{{ __('admin.catalogue.cancel') }}</x-ui.button>@endif
                        </form>
                    </section>
                @endif

                <section aria-labelledby="cities-title">
                    <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
                        <h2 id="cities-title" class="text-h3 font-semibold">{{ $country->label('city') }} <span class="font-normal text-fg-subtle">· {{ number_format($cities->total()) }}</span></h2>
                        <x-ui.button size="sm" variant="secondary" icon="plus" wire:click="createCity">{{ __('locations.add', ['label' => $country->label('city')]) }}</x-ui.button>
                    </div>
                    @error('cities')<x-ui.alert variant="danger" role="alert" class="mb-3">{{ $message }}</x-ui.alert>@enderror
                    <div class="mb-3 flex flex-wrap items-end gap-3">
                        @if ($cityRegions->isNotEmpty())
                            <x-ui.select :label="$cityRegionLabel" id="ci-filter" wire:model.live="cityFilter" :placeholder="__('public.any')" class="min-w-48"
                                :options="$cityRegions->mapWithKeys(fn ($r) => [$r->id => $r->name])->all()" />
                        @endif
                        <x-ui.input type="search" :label="__('admin.locations.search_places', ['label' => $country->label('city')])" id="ci-q" wire:model.live.debounce.400ms="citySearch" maxlength="80" class="min-w-56" />
                    </div>

                    @if ($cities->isEmpty())
                        <x-ui.empty-state icon="map-pin" :title="__('locations.no_cities')" />
                    @else
                        <x-ui.table :caption="$country->label('city')">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('admin.name') }}</th>
                                    @if ($cityRegions->isNotEmpty())<th scope="col">{{ $cityRegionLabel }}</th>@endif
                                    <th scope="col" class="num">{{ __('admin.locations.ads') }}</th>
                                    <th scope="col"><span class="sr-only">{{ __('admin.staff.actions') }}</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($cities as $city)
                                    <tr wire:key="city-{{ $city->id }}">
                                        <td data-label="{{ __('admin.name') }}">
                                            <div>
                                                <span @class(['font-medium', 'text-fg' => $city->is_active, 'text-fg-subtle line-through' => ! $city->is_active])>{{ $city->name }}</span>
                                                @if ($city->is_curated)<x-ui.badge variant="outline" class="ml-1">{{ __('locations.curated') }}</x-ui.badge>@endif
                                                <span class="block font-mono text-micro text-fg-subtle">/{{ $city->slug }}</span>
                                            </div>
                                        </td>
                                        @if ($cityRegions->isNotEmpty())<td data-label="{{ $cityRegionLabel }}" class="text-fg-muted">{{ $regionNames[$city->region_id] ?? '–' }}</td>@endif
                                        <td data-label="{{ __('admin.locations.ads') }}" class="num text-fg-muted">{{ number_format($city->listings_count) }}</td>
                                        <td class="text-right">
                                            <div class="inline-flex items-center gap-0.5">
                                                <x-ui.button size="sm" variant="ghost" icon="pencil" wire:click="editCity({{ $city->id }})" :label="__('admin.catalogue.edit_named', ['name' => $city->name])" />
                                                <x-ui.button size="sm" variant="ghost" icon="list" wire:click="showLocalities({{ $city->id }})" :label="$country->label('locality').': '.$city->name" />
                                                <x-ui.button size="sm" variant="ghost" icon="arrow-up-down" wire:click="startMerge({{ $city->id }})" :label="__('admin.locations.merge_named', ['name' => $city->name])" />
                                                @if ($city->listings_count === 0)
                                                    <x-ui.button size="sm" variant="ghost" icon="trash-2" wire:click="deleteCity({{ $city->id }})" wire:confirm="{{ __('admin.catalogue.confirm_delete') }}"
                                                        :label="__('admin.catalogue.delete_named', ['name' => $city->name])" />
                                                @else
                                                    {{-- Used by ads: switch it off instead; keeps the icons in line. --}}
                                                    <span class="size-8" aria-hidden="true"></span>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-ui.table>
                        {{ $cities->links('livewire.admin.pagination') }}
                    @endif
                </section>
            </div>
        </div>
    @endif
</div>
