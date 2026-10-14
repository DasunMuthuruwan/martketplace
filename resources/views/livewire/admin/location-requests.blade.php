<div>
    <h1>{{ __('admin.nav.location_requests') }}</h1>
    <p><a href="{{ route('admin.locations') }}">{{ __('admin.nav.locations') }}</a> · <a href="{{ route('admin.countries') }}">{{ __('admin.nav.countries') }}</a></p>

    @if (session('status'))
        <p class="status" role="status">{{ session('status') }}</p>
    @endif

    <label>{{ __('admin.reports.status') }}
        <select wire:model.live="status">
            <option value="">{{ __('public.any') }}</option>
            @foreach ($statuses as $s)
                <option value="{{ $s->value }}">{{ __('locations.requests.statuses.'.$s->value) }}</option>
            @endforeach
        </select>
    </label>

    @if ($requests->isEmpty())
        <p class="muted">{{ __('locations.requests.empty') }}</p>
    @else
        <table>
            <thead><tr><th>{{ __('geo.labels.country') }}</th><th>{{ __('locations.requests.proposed') }}</th><th>{{ __('locations.requests.when') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($requests as $r)
                    <tr wire:key="req-{{ $r->id }}">
                        <td>{{ $r->country->iso2 }}</td>
                        <td>{{ $r->proposed_city ?? $r->city?->name }}@if ($r->proposed_locality) › {{ $r->proposed_locality }}@endif
                            <span class="muted">@if ($r->region) · {{ $r->region->name }}@endif @if ($r->subregion) › {{ $r->subregion->name }}@endif</span></td>
                        <td class="muted">{{ $r->created_at?->toFormattedDateString() }}</td>
                        <td><button type="button" wire:click="select({{ $r->id }})">{{ __('admin.moderation.review') }}</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        {{ $requests->links('livewire.admin.pagination') }}
    @endif

    @if ($selected)
        <section class="pane" aria-labelledby="req-title">
            <h2 id="req-title">#{{ $selected->id }} · {{ $selected->country->name }} <span class="muted">({{ __('locations.requests.statuses.'.$selected->status->value) }})</span></h2>
            <p>
                @if ($selected->region){{ $selected->country->label('region') }}: {{ $selected->region->name }}<br>@endif
                @if ($selected->subregion){{ $selected->country->label('subregion') }}: {{ $selected->subregion->name }}<br>@endif
                @if ($selected->city){{ $selected->country->label('city') }}: {{ $selected->city->name }}<br>@endif
                @if ($selected->proposed_city){{ __('locations.requests.new_city') }}: <strong>{{ $selected->proposed_city }}</strong><br>@endif
                @if ($selected->proposed_locality){{ __('locations.requests.new_locality') }}: <strong>{{ $selected->proposed_locality }}</strong><br>@endif
                @if ($selected->user)<span class="muted">{{ $selected->user->name }}</span>@endif
            </p>
            @error('request')<p class="error" role="alert">{{ $message }}</p>@enderror

            @if ($selected->status === \App\Enums\LocationRequestStatus::Pending)
                <label for="req-note">{{ __('locations.requests.note') }}</label>
                <textarea id="req-note" wire:model="note" rows="2" maxlength="500"></textarea>
                @error('note')<p class="error">{{ $message }}</p>@enderror

                <h3>{{ __('locations.requests.map_title') }}</h3>
                <label>{{ __('staff.accounts.search') }} <input type="search" wire:model.live.debounce.400ms="mapSearch" maxlength="80"></label>
                <select wire:model.live="mapCity" aria-label="{{ $selected->country->label('city') }}">
                    <option value=""></option>
                    @if ($mappedCity && ! $cityChoices->contains('id', $mappedCity->id))
                        <option value="{{ $mappedCity->id }}">{{ $mappedCity->name }}</option>
                    @endif
                    @foreach ($cityChoices as $c)
                        <option value="{{ $c->id }}">{{ $c->name }} /{{ $c->slug }}</option>
                    @endforeach
                </select>
                @error('mapCity')<p class="error">{{ $message }}</p>@enderror
                @if ($mappedCity && $mappedCity->localities->isNotEmpty())
                    <select wire:model="mapLocality" aria-label="{{ $selected->country->label('locality') }}">
                        <option value=""></option>
                        @foreach ($mappedCity->localities as $l)
                            <option value="{{ $l->id }}">{{ $l->name }}</option>
                        @endforeach
                    </select>
                    @error('mapLocality')<p class="error">{{ $message }}</p>@enderror
                @endif
                <button type="button" wire:click="map">{{ __('locations.requests.map') }}</button>

                <h3>{{ __('locations.requests.approve_title') }}</h3>
                <label for="req-city">{{ __('locations.requests.new_city') }}</label>
                <input id="req-city" type="text" wire:model="approveCity" maxlength="80">
                @error('approveCity')<p class="error">{{ $message }}</p>@enderror
                <label for="req-locality">{{ __('locations.requests.new_locality') }}</label>
                <input id="req-locality" type="text" wire:model="approveLocality" maxlength="80">
                @error('approveLocality')<p class="error">{{ $message }}</p>@enderror
                <button type="button" wire:click="approve">{{ __('locations.requests.approve') }}</button>

                <h3>{{ __('locations.requests.reject_title') }}</h3>
                <button type="button" wire:click="reject">{{ __('locations.requests.reject') }}</button>
            @elseif ($selected->resolution_note)
                <p>{{ $selected->resolution_note }}</p>
            @endif
            <p><button type="button" wire:click="close">{{ __('admin.catalogue.cancel') }}</button></p>
        </section>
    @endif
</div>
