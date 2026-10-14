<div>
    <h1>{{ __('admin.nav.countries') }}</h1>
    <p><a href="{{ route('admin.locations') }}">{{ __('admin.nav.locations') }}</a> · <a href="{{ route('admin.location-requests') }}">{{ __('admin.nav.location_requests') }}</a></p>

    @if (session('status'))
        <p class="status" role="status">{{ session('status') }}</p>
    @endif

    <p>
        <label>{{ __('staff.accounts.search') }}
            <input type="search" wire:model.live.debounce.400ms="q" maxlength="60">
        </label>
        <label>{{ __('admin.reports.status') }}
            <select wire:model.live="active">
                <option value="">{{ __('public.any') }}</option>
                <option value="1">{{ __('locations.active') }}</option>
                <option value="0">{{ __('locations.inactive') }}</option>
            </select>
        </label>
    </p>

    <table>
        <thead><tr><th>{{ __('locations.code') }}</th><th>{{ __('auth.ui.name') }}</th><th>{{ __('locations.currency') }}</th><th>{{ __('admin.reports.status') }}</th><th></th></tr></thead>
        <tbody>
            @foreach ($countries as $country)
                <tr wire:key="country-{{ $country->id }}">
                    <td><code>{{ $country->iso2 }}</code></td>
                    <td>{{ $country->name }}</td>
                    <td>{{ $country->default_currency }}</td>
                    <td>{{ $country->is_active ? __('locations.active') : __('locations.inactive') }}</td>
                    <td><button type="button" wire:click="select({{ $country->id }})">{{ __('admin.catalogue.edit') }}</button></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    {{ $countries->links('livewire.admin.pagination') }}

    @if ($selected)
        <section class="pane" aria-labelledby="country-title">
            <h2 id="country-title">{{ $selected->name }} <span class="muted">({{ $selected->iso2 }} · {{ $selected->is_active ? __('locations.active') : __('locations.inactive') }})</span></h2>

            <h3>{{ __('locations.checklist') }}</h3>
            <ul>
                @foreach ($checklist as $item => $state)
                    <li>{{ $state['ok'] ? '✓' : ($state['required'] ? '✗' : '•') }} {{ __('locations.checks.'.$item) }}@if (! $state['required']) <span class="muted">({{ __('locations.reminder') }})</span>@endif</li>
                @endforeach
            </ul>
            @error('activate')<p class="error" role="alert">{{ $message }}</p>@enderror
            @if ($canActivate)
                <p>
                    @if ($selected->is_active)
                        <button type="button" wire:click="toggleActive" wire:confirm="{{ __('locations.confirm_deactivate') }}">{{ __('locations.deactivate') }}</button>
                    @else
                        <button type="button" wire:click="toggleActive" wire:confirm="{{ __('locations.confirm_activate') }}">{{ __('locations.activate') }}</button>
                    @endif
                </p>
            @else
                <p class="muted">{{ __('locations.owner_activates') }}</p>
            @endif

            <form wire:submit="save">
                <label for="cn-name">{{ __('auth.ui.name') }}</label>
                <input id="cn-name" type="text" wire:model="name" maxlength="80" required>
                @error('name')<p class="error">{{ $message }}</p>@enderror

                <fieldset>
                    <legend>{{ __('locations.labels') }}</legend>
                    <p class="muted">{{ __('locations.labels_help') }}</p>
                    @foreach (['region', 'subregion', 'city', 'locality'] as $level)
                        <label for="cn-{{ $level }}">{{ __('geo.labels.'.$level) }}</label>
                        <input id="cn-{{ $level }}" type="text" wire:model="{{ $level }}Label" maxlength="40">
                    @endforeach
                </fieldset>

                <label for="cn-currency">{{ __('locations.currency') }}</label>
                <select id="cn-currency" wire:model="currency" required>
                    <option value=""></option>
                    @foreach ($currencies as $c)
                        <option value="{{ $c->code }}">{{ $c->code }} · {{ $c->name }}</option>
                    @endforeach
                </select>
                @error('currency')<p class="error">{{ $message }}</p>@enderror

                <label for="cn-locale">{{ __('locations.number_locale') }}</label>
                <input id="cn-locale" type="text" wire:model="numberLocale" maxlength="15" required>
                @error('numberLocale')<p class="error">{{ $message }}</p>@enderror

                <label for="cn-tz">{{ __('locations.timezone') }}</label>
                <input id="cn-tz" type="text" wire:model="timezone" maxlength="40">
                @error('timezone')<p class="error">{{ $message }}</p>@enderror

                <label for="cn-levels">{{ __('locations.region_levels') }}</label>
                <select id="cn-levels" wire:model="regionLevels" @disabled($hasRegions)>
                    <option value="0">0</option><option value="1">1</option><option value="2">2</option>
                </select>
                @if ($hasRegions)<p class="muted">{{ __('locations.levels_locked') }}</p>@endif
                @error('regionLevels')<p class="error">{{ $message }}</p>@enderror

                <label><input type="checkbox" wire:model="requireRegion"> {{ __('locations.require_region') }}</label>
                <label><input type="checkbox" wire:model="requireCity"> {{ __('locations.require_city') }}</label>

                <label for="cn-sort">{{ __('admin.catalogue.sort') }}</label>
                <input id="cn-sort" type="number" wire:model="sortOrder" min="0" max="65535">

                <fieldset>
                    <legend>{{ __('locations.area_units') }}</legend>
                    @foreach ($units as $unit)
                        <label><input type="checkbox" wire:model="areaUnits" value="{{ $unit->id }}"> {{ $unit->name }} ({{ $unit->symbol }})</label>
                    @endforeach
                    @error('areaUnits.*')<p class="error">{{ $message }}</p>@enderror
                    <label for="cn-default-unit">{{ __('locations.default_area_unit') }}</label>
                    <select id="cn-default-unit" wire:model="defaultAreaUnit">
                        <option value="">{{ __('admin.catalogue.none') }}</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                    @error('defaultAreaUnit')<p class="error">{{ $message }}</p>@enderror
                    <label for="cn-land-unit">{{ __('locations.land_price_unit') }}</label>
                    <select id="cn-land-unit" wire:model="landPriceUnit">
                        <option value="">{{ __('admin.catalogue.none') }}</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                </fieldset>

                <p>
                    <button type="submit">{{ __('staff.manage.save') }}</button>
                    <button type="button" wire:click="close">{{ __('admin.catalogue.cancel') }}</button>
                </p>
            </form>
        </section>
    @endif
</div>
