@props(['entry'])
{{-- Audit entry (components §5.4): time, actor, action, item, reason and the recorded changes (folded). --}}
<tr {{ $attributes }}>
    <td data-label="{{ __('admin.changes.when') }}" class="whitespace-nowrap">
        <time datetime="{{ $entry->created_at?->toIso8601String() }}" class="tabular text-fg">{{ $entry->created_at?->format('Y-m-d H:i') }}</time>
        <span class="block text-fg-subtle">{{ $entry->created_at?->diffForHumans(short: true) }}</span>
    </td>
    <td data-label="{{ __('admin.audit.actor') }}" class="min-w-0">
        <span class="font-medium text-fg">{{ $entry->actor?->name ?? __('admin.audit.system') }}</span>
        @if ($entry->actor)<span class="block truncate text-fg-subtle">{{ $entry->actor->email }}</span>@endif
    </td>
    <td data-label="{{ __('admin.audit.action') }}"><code class="rounded bg-surface-muted px-1.5 py-0.5 font-mono text-micro text-fg">{{ $entry->action }}</code></td>
    <td data-label="{{ __('admin.audit.subject') }}" class="whitespace-nowrap text-fg-muted">
        @if ($entry->subject_type){{ class_basename($entry->subject_type) }} <span class="tabular">#{{ $entry->subject_id }}</span>@else – @endif
    </td>
    <td data-label="{{ __('admin.audit.details') }}" class="min-w-0">
        @if ($entry->reason)<p class="prose-user text-fg">{{ $entry->reason }}</p>@endif
        @if ($entry->changes)
            <details class="group mt-1">
                <summary class="cursor-pointer list-none text-fg-muted hover:text-fg [&::-webkit-details-marker]:hidden">
                    <span class="inline-flex items-center gap-1">{{ __('admin.audit.show_changes') }}<x-ui.icon name="chevron-down" size="16" class="transition-transform group-open:rotate-180" /></span>
                </summary>
                <pre class="mt-1 max-h-64 max-w-md overflow-auto rounded-control bg-surface-muted p-2 font-mono text-micro text-fg">{{ json_encode($entry->changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
            </details>
        @endif
        @if (! $entry->reason && ! $entry->changes)<span class="text-fg-subtle">–</span>@endif
    </td>
</tr>
