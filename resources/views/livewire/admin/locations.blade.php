<div>
    <h1>{{ __('admin.nav.locations') }}</h1>
    <p><a href="{{ route('admin.countries') }}">{{ __('admin.nav.countries') }}</a> · <a href="{{ route('admin.location-requests') }}">{{ __('admin.nav.location_requests') }}</a></p>

    @if (session('status'))
        <p class="status" role="status">{{ session('status') }}</p>
    @endif

    <label for="loc-country">{{ __('geo.labels.country') }}</label>
    <select id="loc-country" wire:model.live="countryId">
        <option value=""></option>
        @foreach ($countries as $c)
            <option value="{{ $c->id }}">{{ $c->iso2 }} · {{ $c->name }}@unless ($c->is_active) ({{ __('locations.inactive') }})@endunless</option>
        @endforeach
    </select>

    @if ($country)
        {{-- Regions --}}
        @if ($country->region_levels > 0)
            <h2>{{ $country->label('region') }}</h2>
            @error('regions')<p class="error" role="alert">{{ $message }}</p>@enderror
            <p><button type="button" wire:click="createRegion">{{ __('locations.add', ['label' => $country->label('region')]) }}</button></p>
            <ul>
                @foreach ($level1 as $region)
                    <li wire:key="region-{{ $region->id }}">
                        {{ $region->name }} <span class="muted">/{{ $region->slug }}@if ($region->code) · {{ $region->code }}@endif @unless ($region->is_active)· {{ __('admin.catalogue.inactive') }}@endunless</span>
                        <button type="button" wire:click="editRegion({{ $region->id }})">{{ __('admin.catalogue.edit') }}</button>
                        @if ($country->region_levels >= 2)
                            <button type="button" wire:click="createRegion({{ $region->id }})">{{ __('locations.add', ['label' => $country->label('subregion')]) }}</button>
                        @endif
                        <button type="button" wire:click="deleteRegion({{ $region->id }})" wire:confirm="{{ __('admin.catalogue.confirm_delete') }}">{{ __('admin.catalogue.delete') }}</button>
                        @if ($level2->has($region->id))
                            <ul>
                                @foreach ($level2->get($region->id) as $sub)
                                    <li wire:key="region-{{ $sub->id }}">
                                        {{ $sub->name }} <span class="muted">/{{ $sub->slug }} @unless ($sub->is_active)· {{ __('admin.catalogue.inactive') }}@endunless</span>
                                        <button type="button" wire:click="editRegion({{ $sub->id }})">{{ __('admin.catalogue.edit') }}</button>
                                        <button type="button" wire:click="deleteRegion({{ $sub->id }})" wire:confirm="{{ __('admin.catalogue.confirm_delete') }}">{{ __('admin.catalogue.delete') }}</button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($regionEditing)
                <form wire:submit="saveRegion" class="pane">
                    <h3>{{ $regionId ? __('admin.catalogue.edit') : __('locations.add', ['label' => $regionParent ? $country->label('subregion') : $country->label('region')]) }}@if ($regionParent) · {{ $regionNames[(int) $regionParent] ?? '' }}@endif</h3>
                    <label for="r-name">{{ __('auth.ui.name') }}</label>
                    <input id="r-name" type="text" wire:model="regionName" maxlength="100" required>
                    @error('regionName')<p class="error">{{ $message }}</p>@enderror
                    @error('regionParent')<p class="error">{{ $message }}</p>@enderror
                    <label for="r-code">{{ __('locations.code') }}</label>
                    <input id="r-code" type="text" wire:model="regionCode" maxlength="40">
                    <label for="r-sort">{{ __('admin.catalogue.sort') }}</label>
                    <input id="r-sort" type="number" wire:model="regionSort" min="0" max="65535">
                    <label><input type="checkbox" wire:model="regionActive"> {{ __('admin.catalogue.active') }}</label>
                    <p>
                        <button type="submit">{{ __('staff.manage.save') }}</button>
                        <button type="button" wire:click="closeRegion">{{ __('admin.catalogue.cancel') }}</button>
                    </p>
                </form>
            @endif
        @endif

        {{-- Cities --}}
        <h2>{{ $country->label('city') }}</h2>
        @error('cities')<p class="error" role="alert">{{ $message }}</p>@enderror
        <p>
            @if ($cityRegions->isNotEmpty())
                <label>{{ $country->label($country->region_levels >= 2 ? 'subregion' : 'region') }}
                    <select wire:model.live="cityFilter">
                        <option value="">{{ __('public.any') }}</option>
                        @foreach ($cityRegions as $r)
                            <option value="{{ $r->id }}">{{ $r->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            <label>{{ __('staff.accounts.search') }} <input type="search" wire:model.live.debounce.400ms="citySearch" maxlength="80"></label>
            <button type="button" wire:click="createCity">{{ __('locations.add', ['label' => $country->label('city')]) }}</button>
        </p>

        @if ($cities->isEmpty())
            <p class="muted">{{ __('locations.no_cities') }}</p>
        @else
            <table>
                <tbody>
                    @foreach ($cities as $city)
                        <tr wire:key="city-{{ $city->id }}">
                            <td>{{ $city->name }} <span class="muted">/{{ $city->slug }} · {{ $regionNames[$city->region_id] ?? '' }}
                                · {{ trans_choice('locations.listings', $city->listings_count, ['count' => $city->listings_count]) }}
                                @if ($city->is_curated) · {{ __('locations.curated') }} @endif
                                @unless ($city->is_active) · {{ __('admin.catalogue.inactive') }} @endunless</span></td>
                            <td>
                                <button type="button" wire:click="editCity({{ $city->id }})">{{ __('admin.catalogue.edit') }}</button>
                                <button type="button" wire:click="showLocalities({{ $city->id }})">{{ $country->label('locality') }}</button>
                                <button type="button" wire:click="startMerge({{ $city->id }})">{{ __('locations.merge') }}</button>
                                @if ($city->listings_count === 0)
                                    <button type="button" wire:click="deleteCity({{ $city->id }})" wire:confirm="{{ __('admin.catalogue.confirm_delete') }}">{{ __('admin.catalogue.delete') }}</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $cities->links('livewire.admin.pagination') }}
        @endif

        @if ($cityEditing)
            <form wire:submit="saveCity" class="pane">
                <h3>{{ $cityId ? __('admin.catalogue.edit') : __('locations.add', ['label' => $country->label('city')]) }}</h3>
                <label for="ci-name">{{ __('auth.ui.name') }}</label>
                <input id="ci-name" type="text" wire:model="cityName" maxlength="100" required>
                @error('cityName')<p class="error">{{ $message }}</p>@enderror
                @if ($cityRegions->isNotEmpty())
                    <label for="ci-region">{{ $country->label($country->region_levels >= 2 ? 'subregion' : 'region') }}</label>
                    <select id="ci-region" wire:model="cityRegion" required>
                        <option value=""></option>
                        @foreach ($cityRegions as $r)
                            <option value="{{ $r->id }}">{{ $r->name }}</option>
                        @endforeach
                    </select>
                    @if ($cityId)<p class="muted">{{ __('locations.move_help') }}</p>@endif
                @endif
                @error('cityRegion')<p class="error">{{ $message }}</p>@enderror
                <label><input type="checkbox" wire:model="cityActive"> {{ __('admin.catalogue.active') }}</label>
                <p>
                    <button type="submit">{{ __('staff.manage.save') }}</button>
                    <button type="button" wire:click="closeCity">{{ __('admin.catalogue.cancel') }}</button>
                </p>
            </form>
        @endif

        @if ($mergeFrom)
            <section class="pane" aria-labelledby="merge-title">
                <h3 id="merge-title">{{ __('locations.merge_title', ['name' => $mergeFrom->name]) }}</h3>
                <p class="muted">{{ __('locations.merge_help') }}</p>
                @error('mergeInto')<p class="error" role="alert">{{ $message }}</p>@enderror
                <label>{{ __('locations.merge_search') }} <input type="search" wire:model.live.debounce.400ms="mergeSearch" maxlength="80"></label>
                <ul>
                    @foreach ($mergeTargets as $target)
                        <li wire:key="merge-{{ $target->id }}">{{ $target->name }} <span class="muted">/{{ $target->slug }} · {{ $regionNames[$target->region_id] ?? '' }}</span>
                            <button type="button" wire:click="mergeInto({{ $target->id }})" wire:confirm="{{ __('locations.confirm_merge') }}">{{ __('locations.merge_into') }}</button></li>
                    @endforeach
                </ul>
                <button type="button" wire:click="cancelMerge">{{ __('admin.catalogue.cancel') }}</button>
            </section>
        @endif

        {{-- Localities --}}
        @if ($localityCity)
            <section class="pane" aria-labelledby="loc-title">
                <h3 id="loc-title">{{ $country->label('locality') }} · {{ $localityCity->name }}</h3>
                @error('localities')<p class="error" role="alert">{{ $message }}</p>@enderror
                <ul>
                    @forelse ($localityCity->localities as $locality)
                        <li wire:key="locality-{{ $locality->id }}">{{ $locality->name }} <span class="muted">/{{ $locality->slug }} @unless ($locality->is_active)· {{ __('admin.catalogue.inactive') }}@endunless</span>
                            <button type="button" wire:click="editLocality({{ $locality->id }})">{{ __('admin.catalogue.edit') }}</button>
                            <button type="button" wire:click="deleteLocality({{ $locality->id }})" wire:confirm="{{ __('admin.catalogue.confirm_delete') }}">{{ __('admin.catalogue.delete') }}</button></li>
                    @empty
                        <li class="muted">{{ __('locations.no_localities') }}</li>
                    @endforelse
                </ul>
                <form wire:submit="saveLocality">
                    <label for="lo-name">{{ $localityId ? __('admin.catalogue.edit') : __('locations.add', ['label' => $country->label('locality')]) }}</label>
                    <input id="lo-name" type="text" wire:model="localityName" maxlength="100" required>
                    @error('localityName')<p class="error">{{ $message }}</p>@enderror
                    <label><input type="checkbox" wire:model="localityActive"> {{ __('admin.catalogue.active') }}</label>
                    <button type="submit">{{ __('staff.manage.save') }}</button>
                    @if ($localityId)<button type="button" wire:click="closeLocality">{{ __('admin.catalogue.cancel') }}</button>@endif
                </form>
            </section>
        @endif
    @endif
</div>
