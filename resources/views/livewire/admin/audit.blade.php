{{-- Audit log (ADM-07): read-only, newest first, filtered by action (a prefix ending in "." matches a
     group), actor (email or ID), item and dates. --}}
<div>
    <x-admin.page-header :title="__('admin.nav.audit')" :description="__('admin.audit.intro')">
        <x-slot:filters>
            <x-ui.input :label="__('admin.audit.action')" id="audit-action" wire:model.live.debounce.500ms="action" maxlength="60" placeholder="listing." class="w-40" />
            <x-ui.input :label="__('admin.audit.actor')" id="audit-actor" wire:model.live.debounce.500ms="actor" maxlength="191" :placeholder="__('admin.audit.actor_hint')" class="w-56" />
            <x-ui.select :label="__('admin.audit.subject')" id="audit-subject" wire:model.live="subjectType" :placeholder="__('public.any')" class="w-40"
                :options="$subjectTypes->mapWithKeys(fn ($type) => [$type => class_basename($type)])->all()" />
            <x-ui.input :label="__('admin.audit.subject_id')" id="audit-subject-id" inputmode="numeric" wire:model.live.debounce.500ms="subjectId" maxlength="20" class="w-28" />
            <x-ui.input type="date" :label="__('admin.audit.from')" id="audit-from" wire:model.live="from" class="w-40" />
            <x-ui.input type="date" :label="__('admin.audit.to')" id="audit-to" wire:model.live="to" class="w-40" />
            <x-ui.button variant="ghost" icon="x" wire:click="clear">{{ __('admin.audit.clear') }}</x-ui.button>
        </x-slot:filters>
    </x-admin.page-header>

    @if ($entries->isEmpty())
        <x-ui.empty-state icon="eye" :title="__('admin.audit.empty')" />
    @else
        <x-ui.table :caption="__('admin.nav.audit')">
            <thead>
                <tr>
                    <th scope="col">{{ __('admin.changes.when') }}</th>
                    <th scope="col">{{ __('admin.audit.actor') }}</th>
                    <th scope="col">{{ __('admin.audit.action') }}</th>
                    <th scope="col">{{ __('admin.audit.subject') }}</th>
                    <th scope="col">{{ __('admin.audit.details') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($entries as $entry)
                    <x-admin.audit-entry :entry="$entry" wire:key="audit-{{ $entry->id }}" />
                @endforeach
            </tbody>
        </x-ui.table>
        {{ $entries->links('livewire.admin.pagination') }}
    @endif
</div>
