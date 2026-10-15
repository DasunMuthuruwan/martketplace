{{-- Keyboard shortcuts help (components §5.4): key => action name in admin.shortcuts. --}}
<x-ui.dropdown :label="__('admin.shortcuts.title')" icon="keyboard" :icon-only="true">
    <dl class="grid grid-cols-[auto_1fr] items-center gap-x-3 gap-y-1.5 px-2 py-1.5 text-small">
        @foreach ($keys as $key => $action)
            <dt><kbd class="rounded border border-line bg-surface-muted px-1.5 font-mono text-micro text-fg">{{ __('admin.shortcuts.keys.'.$key) }}</kbd></dt>
            <dd class="text-fg-muted">{{ __('admin.shortcuts.'.$action) }}</dd>
        @endforeach
    </dl>
</x-ui.dropdown>
