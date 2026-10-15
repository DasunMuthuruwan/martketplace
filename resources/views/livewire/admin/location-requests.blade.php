@use('App\Enums\LocationRequestStatus', 'LRS')
{{-- Place requests (GLB-15): towns and localities sellers could not find. Staff map a request to an existing
     place, add the place (approve), or decline; the seller's ad keeps the nearest place until then. --}}
<div data-shortcuts>
    <x-admin.page-header :title="__('admin.nav.location_requests')" :count="$requests->total()" :description="__('admin.locations.requests_intro')">
        @include('livewire.admin.partials.shortcuts', ['keys' => ['j' => 'next', 'k' => 'previous']])
    </x-admin.page-header>
    @include('livewire.admin.partials.locations-tabs', ['active' => 'admin.location-requests'])

    @if (session('status'))
        <x-ui.alert variant="success" role="status" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    <div class="grid items-start gap-4 lg:grid-cols-[minmax(260px,340px)_minmax(0,1fr)]">
        <div @class(['min-w-0 space-y-3', 'max-lg:hidden' => $selected])>
            <x-ui.select :label="__('admin.reports.status')" id="req-status" wire:model.live="status" :placeholder="__('public.any')" :selected="$status"
                :options="collect($statuses)->mapWithKeys(fn ($s) => [$s->value => __('locations.requests.statuses.'.$s->value)])->all()" />
            @if ($requests->isEmpty())
                <x-ui.empty-state icon="map-pin" :title="__('locations.requests.empty')" />
            @else
                <ul class="divide-y divide-line overflow-hidden rounded-card border border-line bg-surface" aria-label="{{ __('admin.nav.location_requests') }}">
                    @foreach ($requests as $r)
                        @php($current = $selected?->id === $r->id)
                        <li wire:key="req-{{ $r->id }}">
                            <button type="button" wire:click="select({{ $r->id }})" data-queue-item @if ($current) aria-current="true" @endif
                                @class([
                                    'flex w-full items-start gap-3 px-3 py-2.5 text-left hover:bg-surface-hover',
                                    'bg-surface-muted shadow-[inset_3px_0_0_var(--fg)]' => $current,
                                ])>
                                <x-ui.flag :code="$r->country->iso2" class="mt-1" />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-ui font-medium text-fg">{{ $r->proposed_city ?? $r->city?->name }}@if ($r->proposed_locality) › {{ $r->proposed_locality }}@endif</span>
                                    <span class="block truncate text-small text-fg-muted">{{ collect([$r->region?->name, $r->subregion?->name])->filter()->implode(' › ') ?: $r->country->name }}</span>
                                    <span class="block text-small text-fg-subtle">{{ $r->created_at?->diffForHumans(short: true) }}</span>
                                </span>
                                @unless ($r->status === LRS::Pending)<x-ui.badge variant="outline">{{ __('locations.requests.statuses.'.$r->status->value) }}</x-ui.badge>@endunless
                            </button>
                        </li>
                    @endforeach
                </ul>
                {{ $requests->links('livewire.admin.pagination') }}
            @endif
        </div>

        <div class="min-w-0">
            @if ($selected)
                @php($country = $selected->country)
                <x-ui.button variant="ghost" size="sm" icon="chevron-left" wire:click="close" class="mb-2 lg:hidden">{{ __('admin.locations.back_requests') }}</x-ui.button>
                <article aria-labelledby="req-title" class="space-y-5 rounded-card border border-line bg-surface p-4 lg:p-5" wire:key="req-pane-{{ $selected->id }}">
                    <header class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.badge :variant="$selected->status === LRS::Pending ? 'warning' : 'outline'">{{ __('locations.requests.statuses.'.$selected->status->value) }}</x-ui.badge>
                            <span class="text-small text-fg-subtle">{{ $selected->created_at?->toFormattedDateString() }} · ID {{ $selected->id }}</span>
                        </div>
                        <h2 id="req-title" class="flex items-center gap-2 text-h2 font-semibold"><x-ui.flag :code="$country->iso2" />{{ $selected->proposed_city ?? $selected->city?->name }}@if ($selected->proposed_locality) › {{ $selected->proposed_locality }}@endif</h2>
                        @if ($selected->user)<p class="text-small text-fg-muted">{{ __('admin.locations.asked_by', ['name' => $selected->user->name]) }}</p>@endif
                    </header>

                    <dl class="grid gap-x-6 text-small sm:grid-cols-2">
                        <div class="flex justify-between gap-4 border-b border-line py-1.5"><dt class="text-fg-muted">{{ __('geo.labels.country') }}</dt><dd class="font-medium text-fg">{{ $country->name }}</dd></div>
                        @if ($selected->region)<div class="flex justify-between gap-4 border-b border-line py-1.5"><dt class="text-fg-muted">{{ $country->label('region') }}</dt><dd class="font-medium text-fg">{{ $selected->region->name }}</dd></div>@endif
                        @if ($selected->subregion)<div class="flex justify-between gap-4 border-b border-line py-1.5"><dt class="text-fg-muted">{{ $country->label('subregion') }}</dt><dd class="font-medium text-fg">{{ $selected->subregion->name }}</dd></div>@endif
                        @if ($selected->city)<div class="flex justify-between gap-4 border-b border-line py-1.5"><dt class="text-fg-muted">{{ $country->label('city') }}</dt><dd class="font-medium text-fg">{{ $selected->city->name }}</dd></div>@endif
                        @if ($selected->proposed_city)<div class="flex justify-between gap-4 border-b border-line py-1.5"><dt class="text-fg-muted">{{ __('locations.requests.new_city') }}</dt><dd class="font-semibold text-fg">{{ $selected->proposed_city }}</dd></div>@endif
                        @if ($selected->proposed_locality)<div class="flex justify-between gap-4 border-b border-line py-1.5"><dt class="text-fg-muted">{{ __('locations.requests.new_locality') }}</dt><dd class="font-semibold text-fg">{{ $selected->proposed_locality }}</dd></div>@endif
                    </dl>

                    @error('request')<x-ui.alert variant="danger" role="alert">{{ $message }}</x-ui.alert>@enderror

                    @if ($selected->status === LRS::Pending)
                        {{-- 1. Already exists: map to it --}}
                        <section aria-labelledby="req-map" class="space-y-3 rounded-control border border-line p-3">
                            <h3 id="req-map" class="text-ui font-semibold">{{ __('locations.requests.map_title') }}</h3>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-ui.input type="search" :label="__('admin.locations.search_places', ['label' => $country->label('city')])" id="req-map-q" wire:model.live.debounce.400ms="mapSearch" maxlength="80" />
                                <x-ui.select :label="$country->label('city')" id="req-map-city" wire:model.live="mapCity" placeholder="" :selected="$mapCity" :error="$errors->first('mapCity')"
                                    :options="($mappedCity && ! $cityChoices->contains('id', $mappedCity->id) ? [$mappedCity->id => $mappedCity->name] : []) + $cityChoices->mapWithKeys(fn ($c) => [$c->id => $c->name.' /'.$c->slug])->all()" />
                                @if ($mappedCity && $mappedCity->localities->isNotEmpty())
                                    <x-ui.select :label="$country->label('locality')" id="req-map-locality" wire:model="mapLocality" placeholder="" :selected="$mapLocality" :error="$errors->first('mapLocality')"
                                        :options="$mappedCity->localities->mapWithKeys(fn ($l) => [$l->id => $l->name])->all()" />
                                @endif
                            </div>
                            <x-ui.button size="sm" variant="secondary" icon="map-pin" wire:click="map">{{ __('locations.requests.map') }}</x-ui.button>
                        </section>

                        {{-- 2. New place: add it --}}
                        <section aria-labelledby="req-approve" class="space-y-3 rounded-control border border-line p-3">
                            <h3 id="req-approve" class="text-ui font-semibold">{{ __('locations.requests.approve_title') }}</h3>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-ui.input :label="__('locations.requests.new_city')" id="req-city" wire:model="approveCity" maxlength="80" :error="$errors->first('approveCity')" />
                                <x-ui.input :label="__('locations.requests.new_locality')" id="req-locality" wire:model="approveLocality" maxlength="80" :error="$errors->first('approveLocality')" />
                            </div>
                            <x-ui.button size="sm" icon="plus" wire:click="approve">{{ __('locations.requests.approve') }}</x-ui.button>
                        </section>

                        {{-- 3. Decline, with an optional note --}}
                        <section aria-labelledby="req-reject" class="space-y-3 border-t border-line pt-4">
                            <h3 id="req-reject" class="text-ui font-semibold">{{ __('locations.requests.reject_title') }}</h3>
                            <x-ui.textarea :label="__('locations.requests.note')" id="req-note" wire:model="note" rows="2" :maxlength="500" :error="$errors->first('note')" />
                            <x-ui.button size="sm" variant="ghost" icon="x" class="text-danger" wire:click="reject">{{ __('locations.requests.reject') }}</x-ui.button>
                        </section>
                    @elseif ($selected->resolution_note)
                        <p class="rounded-control bg-surface-muted p-3 text-small text-fg">{{ $selected->resolution_note }}</p>
                    @endif
                </article>
            @else
                <x-ui.empty-state icon="map-pin" :title="__('admin.locations.choose_request')" class="max-lg:hidden">{{ __('admin.moderation.choose_text') }}</x-ui.empty-state>
            @endif
        </div>
    </div>
</div>
