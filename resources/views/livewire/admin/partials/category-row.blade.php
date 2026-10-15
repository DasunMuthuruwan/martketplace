{{-- One category in the admin tree: icon, name, slug, status and scope, then order and actions. --}}
<div class="flex flex-wrap items-center gap-x-3 gap-y-2 px-3 py-2.5">
    <span @class(['grid shrink-0 place-items-center rounded-control bg-surface-muted text-fg-muted', 'size-9' => $top, 'size-7' => ! $top])>
        <x-ui.icon :name="\App\Support\Icons::exists((string) $category->icon) ? $category->icon : ($top ? 'package' : 'list')" :size="$top ? 20 : 16" />
    </span>
    <div class="min-w-0 flex-1">
        <p @class(['truncate text-fg', 'text-ui font-semibold' => $top, 'text-ui' => ! $top])>{{ $category->name }}</p>
        <p class="flex flex-wrap items-center gap-x-2 text-small text-fg-subtle">
            <span class="font-mono">/{{ $category->slug }}</span>
            @if ($top && $category->children->isNotEmpty())<span>{{ trans_choice('admin.catalogue.subcategories', $category->children->count(), ['count' => $category->children->count()]) }}</span>@endif
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-1.5">
        @unless ($category->is_active)<x-ui.badge variant="warning">{{ __('admin.catalogue.inactive') }}</x-ui.badge>@endunless
        @if ($category->scope_countries)<x-ui.badge variant="outline" icon="globe">{{ implode(', ', $category->scope_countries) }}</x-ui.badge>@endif
    </div>
    <div class="flex items-center gap-0.5">
        <x-ui.button size="sm" variant="ghost" icon="chevron-up" wire:click="move({{ $category->id }}, 'up')" :label="__('admin.catalogue.up_named', ['name' => $category->name])" />
        <x-ui.button size="sm" variant="ghost" icon="chevron-down" wire:click="move({{ $category->id }}, 'down')" :label="__('admin.catalogue.down_named', ['name' => $category->name])" />
        <x-ui.button size="sm" variant="ghost" icon="pencil" wire:click="edit({{ $category->id }})" :label="__('admin.catalogue.edit_named', ['name' => $category->name])" />
        @if ($top)
            <x-ui.button size="sm" variant="ghost" icon="plus" wire:click="create({{ $category->id }})" :label="__('admin.catalogue.new_sub_named', ['name' => $category->name])" />
        @endif
        @if (! $top || $category->children->isEmpty())
            <x-ui.button size="sm" variant="ghost" icon="trash-2" wire:click="delete({{ $category->id }})" wire:confirm="{{ __('admin.catalogue.confirm_delete') }}" :label="__('admin.catalogue.delete_named', ['name' => $category->name])" />
        @endif
    </div>
</div>
