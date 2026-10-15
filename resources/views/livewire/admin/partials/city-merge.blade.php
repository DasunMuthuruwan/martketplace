{{-- Merge a duplicate town (ADM-03): find the town to keep, see what will happen, then confirm. --}}
<section aria-labelledby="merge-title" class="space-y-3 rounded-card border border-warning/50 bg-surface p-4">
    <div class="flex items-start justify-between gap-2">
        <div>
            <h3 id="merge-title" class="text-h3 font-semibold">{{ __('locations.merge_title', ['name' => $mergeFrom->name]) }}</h3>
            <p class="text-small text-fg-muted">{{ __('locations.merge_help') }}</p>
        </div>
        <x-ui.button size="sm" variant="ghost" icon="x" wire:click="cancelMerge" :label="__('admin.catalogue.cancel')" />
    </div>
    @error('mergeInto')<x-ui.alert variant="danger" role="alert">{{ $message }}</x-ui.alert>@enderror

    @if ($mergePreview === null)
        <x-ui.input type="search" :label="__('locations.merge_search')" id="merge-q" wire:model.live.debounce.400ms="mergeSearch" maxlength="80" :hint="__('admin.locations.merge_search_hint')" />
        @if ($mergeTargets->isNotEmpty())
            <ul class="divide-y divide-line rounded-control border border-line">
                @foreach ($mergeTargets as $target)
                    <li wire:key="merge-{{ $target->id }}" class="flex items-center gap-3 px-3 py-2">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-ui text-fg">{{ $target->name }}</p>
                            <p class="truncate text-small text-fg-subtle">{{ $regionNames[$target->region_id] ?? '' }} · <span class="font-mono">/{{ $target->slug }}</span></p>
                        </div>
                        <x-ui.button size="sm" variant="secondary" wire:click="previewMerge({{ $target->id }})">{{ __('admin.locations.preview_merge') }}</x-ui.button>
                    </li>
                @endforeach
            </ul>
        @elseif (mb_strlen($mergeSearch) >= 2)
            <p class="text-small text-fg-subtle">{{ __('admin.locations.no_match') }}</p>
        @endif
    @else
        @php($into = $mergePreview['into'])
        <div class="grid items-center gap-3 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]">
            <div class="rounded-control border border-line p-3">
                <p class="text-micro font-medium uppercase tracking-wide text-fg-subtle">{{ __('admin.locations.duplicate') }}</p>
                <p class="text-ui font-medium text-fg">{{ $mergeFrom->name }}</p>
                <p class="text-small text-fg-subtle">{{ $regionNames[$mergeFrom->region_id] ?? '' }} · {{ trans_choice('locations.listings', $mergePreview['listings'], ['count' => $mergePreview['listings']]) }}</p>
            </div>
            <x-ui.icon name="chevron-right" size="24" class="mx-auto text-fg-subtle max-sm:rotate-90" />
            <div class="rounded-control border border-fg/40 p-3">
                <p class="text-micro font-medium uppercase tracking-wide text-fg-subtle">{{ __('admin.locations.keep') }}</p>
                <p class="text-ui font-medium text-fg">{{ $into->name }}</p>
                <p class="text-small text-fg-subtle">{{ $regionNames[$into->region_id] ?? '' }} · {{ trans_choice('locations.listings', $mergePreview['intoListings'], ['count' => $mergePreview['intoListings']]) }}</p>
            </div>
        </div>
        <ul class="list-disc space-y-1 pl-5 text-small text-fg">
            <li>{{ trans_choice('admin.locations.will_move_ads', $mergePreview['listings'], ['count' => $mergePreview['listings'], 'to' => $into->name]) }}</li>
            @if ($mergePreview['localities'] > 0)
                <li>{{ trans_choice('admin.locations.will_move_localities', $mergePreview['localities'], ['count' => $mergePreview['localities'], 'label' => mb_strtolower($country->label('locality')), 'combined' => $mergePreview['combined']]) }}</li>
            @endif
            @if ($mergePreview['requests'] > 0)
                <li>{{ trans_choice('admin.locations.will_move_requests', $mergePreview['requests'], ['count' => $mergePreview['requests']]) }}</li>
            @endif
            <li>{{ $mergePreview['deleted'] ? __('admin.locations.will_delete', ['name' => $mergeFrom->name]) : __('admin.locations.will_switch_off', ['name' => $mergeFrom->name]) }}</li>
        </ul>
        <div class="flex flex-wrap gap-2 border-t border-line pt-3">
            <x-ui.button icon="check" wire:click="mergeInto({{ $into->id }})">{{ __('admin.locations.confirm_merge', ['from' => $mergeFrom->name, 'to' => $into->name]) }}</x-ui.button>
            <x-ui.button variant="ghost" wire:click="clearMergeTarget">{{ __('admin.locations.choose_another') }}</x-ui.button>
        </div>
    @endif
</section>
