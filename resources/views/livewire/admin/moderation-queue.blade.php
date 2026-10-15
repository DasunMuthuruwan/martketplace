{{-- Moderation queue (layout doc §3.6): the queue on the left, the review pane on the right. On phones the
     list or the pane is shown, with "Back to the queue". Shortcuts: resources/js/ui/admin.js. --}}
<div data-shortcuts>
    <x-admin.page-header :title="__('admin.nav.moderation')" :count="$queue->total()" :description="__('admin.moderation.intro')">
        <x-ui.button variant="ghost" size="sm" icon="clock" :href="route('admin.changes')">{{ __('admin.nav.changes') }}</x-ui.button>
        @include('livewire.admin.partials.shortcuts', ['keys' => ['a' => 'approve', 'r' => 'reject', 'j' => 'next', 'k' => 'previous', 'esc' => 'cancel']])
        <x-slot:filters>
            <x-ui.select :label="__('auth.ui.country')" id="filter-country" wire:model.live="country" :placeholder="__('public.any')" class="min-w-44"
                :options="$countries->mapWithKeys(fn ($c) => [strtolower($c->iso2) => $c->name])->all()" />
            <x-ui.checkbox type="switch" :label="__('admin.moderation.flagged_only')" id="filter-flagged" wire:model.live="flaggedOnly" class="pb-2.5" />
        </x-slot:filters>
    </x-admin.page-header>

    @if (session('status'))
        <x-ui.alert variant="success" role="status" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    @if ($queue->isEmpty() && ! $selected)
        <x-ui.empty-state icon="inbox" :title="__('admin.moderation.empty')">{{ __('admin.moderation.empty_text') }}</x-ui.empty-state>
    @else
        <div class="grid items-start gap-4 lg:grid-cols-[minmax(260px,340px)_minmax(0,1fr)]">
            <div @class(['min-w-0', 'max-lg:hidden' => $selected])>
                <ul class="divide-y divide-line overflow-hidden rounded-card border border-line bg-surface" aria-label="{{ __('admin.moderation.queue') }}">
                    @foreach ($queue as $item)
                        @php($current = $selected?->id === $item->id)
                        <li wire:key="queue-{{ $item->id }}">
                            <button type="button" wire:click="select({{ $item->id }})" data-queue-item @if ($current) aria-current="true" @endif
                                @class([
                                    'flex w-full items-start gap-3 px-3 py-2.5 text-left hover:bg-surface-hover',
                                    'bg-surface-muted shadow-[inset_3px_0_0_var(--fg)]' => $current,
                                ])>
                                @if ($item->coverImage)
                                    <img src="{{ $item->coverImage->url('sm') }}" alt="" loading="lazy" class="size-12 shrink-0 rounded-control bg-surface-muted object-cover">
                                @else
                                    <span class="grid size-12 shrink-0 place-items-center rounded-control bg-surface-muted text-fg-subtle"><x-ui.icon name="image" size="20" /></span>
                                @endif
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-ui font-medium text-fg">{{ $item->title }}</span>
                                    <span class="block truncate text-small text-fg-muted">{{ $item->category->name }} · {{ $item->country->iso2 }} · {{ $item->submitted_at?->diffForHumans(short: true) }}</span>
                                    <span class="block truncate text-small text-fg-subtle">{{ $item->user->name }}</span>
                                </span>
                                @if ($item->flagged)
                                    <x-ui.icon name="triangle-alert" size="16" class="mt-0.5 text-warning" /><span class="sr-only">{{ __('admin.moderation.flagged') }}</span>
                                @endif
                            </button>
                        </li>
                    @endforeach
                </ul>
                {{ $queue->links('livewire.admin.pagination') }}
            </div>

            <div class="min-w-0">
                @if ($selected)
                    <x-ui.button variant="ghost" size="sm" icon="chevron-left" wire:click="close" class="mb-2 lg:hidden">{{ __('admin.moderation.back') }}</x-ui.button>
                    <x-admin.review-pane :listing="$selected" :present="$present" :reasons="$reasons" :seller="$seller" :fields="$fields"
                        :templates="$templates" :decision="$decision" :messages="$errors" wire:key="pane-{{ $selected->id }}" />
                @else
                    <x-ui.empty-state icon="shield-check" :title="__('admin.moderation.choose')" class="max-lg:hidden">{{ __('admin.moderation.choose_text') }}</x-ui.empty-state>
                @endif
            </div>
        </div>
    @endif
</div>
