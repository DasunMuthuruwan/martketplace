{{-- Boosts (ADR-026, B3): grant a Bump or Top ad to a live ad, see boosts, end one early. --}}
<div>
    <x-admin.page-header :title="__('admin.nav.boosts')" :count="$boosts->total()" :description="__('admin.boosts.help')" />

    @if (session('status'))
        <x-ui.alert variant="success" role="status" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    <section aria-labelledby="grant-boost" class="mb-6 rounded-card border border-line bg-surface p-4 lg:p-5">
        <h2 id="grant-boost" class="mb-1 text-h3 font-semibold">{{ __('admin.boosts.grant_title') }}</h2>
        <p class="mb-3 text-small text-fg-subtle">{{ __('admin.boosts.grant_help') }}</p>
        <form wire:submit="grant" class="grid gap-3 md:grid-cols-[minmax(0,1fr)_11rem_8rem_auto] md:items-end">
            <x-ui.input :label="__('admin.boosts.ad')" id="g-ad" wire:model="ad" maxlength="300" required :hint="__('admin.boosts.ad_hint')" :error="$errors->first('ad')" />
            <x-ui.select :label="__('admin.boosts.type')" id="g-type" wire:model.live="type" :options="__('admin.boosts.types')" />
            @if ($type === 'top')
                <x-ui.input type="number" :label="__('admin.boosts.days')" id="g-days" wire:model="days" min="1" :max="\App\Services\Boosts\BoostManager::MAX_DAYS" :error="$errors->first('days')" />
            @else
                <div class="hidden md:block"></div>
            @endif
            <x-ui.button type="submit" icon="chevron-up">{{ __('admin.boosts.grant') }}</x-ui.button>
        </form>
    </section>

    <x-ui.tabs :label="__('admin.boosts.states_label')" :active="$state" wire-model="state" class="mb-4"
        :items="collect(\App\Livewire\Admin\BoostsAdmin::STATES)->mapWithKeys(fn ($s) => [$s => __('admin.boosts.states.'.$s)])->all()" />

    @if ($boosts->isEmpty())
        <x-ui.empty-state icon="chevron-up" :title="__('admin.boosts.empty.'.$state)" />
    @else
        <x-ui.table :caption="__('admin.nav.boosts')">
            <thead>
                <tr>
                    <th scope="col">{{ __('admin.boosts.ad') }}</th>
                    <th scope="col">{{ __('admin.boosts.type') }}</th>
                    <th scope="col">{{ __('admin.boosts.period') }}</th>
                    <th scope="col">{{ __('admin.boosts.source') }}</th>
                    <th scope="col"><span class="sr-only">{{ __('admin.boosts.end') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($boosts as $boost)
                    @php($listing = $boost->listing)
                    <tr wire:key="boost-{{ $boost->id }}">
                        <td data-label="{{ __('admin.boosts.ad') }}" class="min-w-0">
                            <a href="{{ $listing->publicUrl() }}" class="prose-user font-medium text-fg hover:underline">{{ $listing->title }}</a>
                            <span class="block text-small text-fg-subtle">#{{ $listing->id }} · {{ $listing->user?->name }}</span>
                        </td>
                        <td data-label="{{ __('admin.boosts.type') }}"><x-ui.badge variant="neutral">{{ __('admin.boosts.types.'.$boost->type->value) }}</x-ui.badge></td>
                        <td data-label="{{ __('admin.boosts.period') }}" class="tabular text-fg-muted">
                            {{ $boost->starts_at->toFormattedDateString() }}@if ($boost->ends_at) – {{ $boost->ends_at->toFormattedDateString() }}@endif
                            @if ($boost->ended_at)<span class="block text-small text-fg-subtle">{{ __('admin.boosts.ended_on', ['date' => $boost->ended_at->toFormattedDateString()]) }}</span>@endif
                        </td>
                        <td data-label="{{ __('admin.boosts.source') }}" class="text-fg-muted">
                            {{ $boost->order_id ? __('admin.boosts.paid', ['order' => $boost->order_id]) : __('admin.boosts.granted_by', ['name' => $boost->grantedBy?->name ?? __('account.deleted_user')]) }}
                        </td>
                        <td class="text-right">
                            @if ($boost->ended_at === null && $boost->ends_at?->isFuture())
                                <x-ui.button size="sm" variant="ghost" icon="x" wire:click="end({{ $boost->id }})" wire:confirm="{{ __('admin.boosts.confirm_end') }}"
                                    :label="__('admin.boosts.end_named', ['title' => $listing->title])" />
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-ui.table>
        {{ $boosts->links('livewire.admin.pagination') }}
    @endif
</div>
