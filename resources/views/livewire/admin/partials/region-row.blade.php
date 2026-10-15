{{-- One region or subregion in the admin list. --}}
<div class="flex items-center gap-2 px-4 py-2">
    <div class="min-w-0 flex-1">
        <p @class(['truncate text-ui', 'font-medium' => ! $sub, 'text-fg' => $region->is_active, 'text-fg-subtle line-through' => ! $region->is_active])>{{ $region->name }}</p>
        <p class="truncate font-mono text-micro text-fg-subtle">/{{ $region->slug }}@if ($region->code) · {{ $region->code }}@endif</p>
    </div>
    <div class="flex shrink-0 items-center gap-0.5">
        <x-ui.button size="sm" variant="ghost" icon="pencil" wire:click="editRegion({{ $region->id }})" :label="__('admin.catalogue.edit_named', ['name' => $region->name])" />
        @if (! $sub && $country->region_levels >= 2)
            <x-ui.button size="sm" variant="ghost" icon="plus" wire:click="createRegion({{ $region->id }})" :label="__('locations.add', ['label' => $country->label('subregion')]).': '.$region->name" />
        @endif
        <x-ui.button size="sm" variant="ghost" icon="trash-2" wire:click="deleteRegion({{ $region->id }})" wire:confirm="{{ __('admin.catalogue.confirm_delete') }}" :label="__('admin.catalogue.delete_named', ['name' => $region->name])" />
    </div>
</div>
