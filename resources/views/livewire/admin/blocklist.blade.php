{{-- Blocklist (MOD-04): words that flag an ad for review or block it, for all countries or one. --}}
<div>
    <x-admin.page-header :title="__('admin.nav.blocklist')" :count="$terms->total()" :description="__('admin.blocklist.help')" />

    @if (session('status'))
        <x-ui.alert variant="success" role="status" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    <section aria-labelledby="add-term" class="mb-6 rounded-card border border-line bg-surface p-4 lg:p-5">
        <h2 id="add-term" class="mb-3 text-h3 font-semibold">{{ __('admin.blocklist.add_title') }}</h2>
        <form wire:submit="add" class="grid gap-3 md:grid-cols-[minmax(0,1fr)_11rem_11rem_13rem_auto] md:items-end">
            <x-ui.input :label="__('admin.blocklist.term')" id="b-term" wire:model="term" maxlength="100" required :error="$errors->first('term')" />
            <x-ui.select :label="__('admin.blocklist.match')" id="b-match" wire:model="match" :options="__('admin.blocklist.matches')" />
            <x-ui.select :label="__('admin.blocklist.action')" id="b-action" wire:model="action" :options="__('admin.blocklist.actions')" />
            <x-ui.select :label="__('geo.labels.country')" id="b-country" wire:model="countryId" :placeholder="__('admin.blocklist.all_countries')" :error="$errors->first('countryId')"
                :options="$countries->mapWithKeys(fn ($c) => [$c->id => $c->iso2.' · '.$c->name])->all()" />
            <x-ui.button type="submit" icon="plus">{{ __('admin.blocklist.add') }}</x-ui.button>
        </form>
    </section>

    <div class="mb-3 flex flex-wrap items-end gap-3">
        <x-ui.input type="search" :label="__('admin.blocklist.search')" id="b-q" wire:model.live.debounce.400ms="q" maxlength="100" class="min-w-56" />
        <x-ui.select :label="__('geo.labels.country')" id="b-scope" wire:model.live="scope" :placeholder="__('public.any')" class="min-w-44"
            :options="['global' => __('admin.blocklist.all_countries')] + $countries->mapWithKeys(fn ($c) => [$c->id => $c->iso2.' · '.$c->name])->all()" />
    </div>

    @if ($terms->isEmpty())
        <x-ui.empty-state icon="circle-alert" :title="__('admin.blocklist.empty')" />
    @else
        <x-ui.table :caption="__('admin.nav.blocklist')">
            <thead>
                <tr>
                    <th scope="col">{{ __('admin.blocklist.term') }}</th>
                    <th scope="col">{{ __('admin.blocklist.match') }}</th>
                    <th scope="col">{{ __('admin.blocklist.action') }}</th>
                    <th scope="col">{{ __('geo.labels.country') }}</th>
                    <th scope="col"><span class="sr-only">{{ __('admin.catalogue.delete') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($terms as $t)
                    <tr wire:key="term-{{ $t->id }}">
                        <td data-label="{{ __('admin.blocklist.term') }}" class="prose-user font-medium text-fg">{{ $t->term }}</td>
                        <td data-label="{{ __('admin.blocklist.match') }}" class="text-fg-muted">{{ __('admin.blocklist.matches.'.$t->match) }}</td>
                        <td data-label="{{ __('admin.blocklist.action') }}">
                            <x-ui.badge :variant="$t->action === 'block' ? 'danger' : 'warning'">{{ __('admin.blocklist.actions.'.$t->action) }}</x-ui.badge>
                        </td>
                        <td data-label="{{ __('geo.labels.country') }}" class="text-fg-muted">
                            @if ($t->country_id)
                                <span class="inline-flex items-center gap-1.5"><x-ui.flag :code="$countryCodes[$t->country_id] ?? ''" />{{ $countryCodes[$t->country_id] ?? '' }}</span>
                            @else
                                {{ __('admin.blocklist.all_countries') }}
                            @endif
                        </td>
                        <td class="text-right">
                            <x-ui.button size="sm" variant="ghost" icon="trash-2" wire:click="remove({{ $t->id }})" wire:confirm="{{ __('admin.catalogue.confirm_delete') }}"
                                :label="__('admin.blocklist.remove', ['term' => $t->term])" />
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-ui.table>
        {{ $terms->links('livewire.admin.pagination') }}
    @endif
</div>
