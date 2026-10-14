<div>
    <h1>{{ __('admin.nav.audit') }}</h1>

    <div class="filters">
        <label>{{ __('admin.audit.action') }} <input type="text" wire:model.live.debounce.500ms="action" maxlength="60" placeholder="listing."></label>
        <label>{{ __('admin.audit.actor') }} <input type="text" wire:model.live.debounce.500ms="actor" maxlength="191"></label>
        <label>{{ __('admin.audit.subject') }}
            <select wire:model.live="subjectType">
                <option value="">{{ __('public.any') }}</option>
                @foreach ($subjectTypes as $type)
                    <option value="{{ $type }}">{{ class_basename($type) }}</option>
                @endforeach
            </select>
        </label>
        <label>#<input type="text" inputmode="numeric" wire:model.live.debounce.500ms="subjectId" maxlength="20" aria-label="{{ __('admin.audit.subject_id') }}"></label>
        <label>{{ __('admin.audit.from') }} <input type="date" wire:model.live="from"></label>
        <label>{{ __('admin.audit.to') }} <input type="date" wire:model.live="to"></label>
        <button type="button" wire:click="clear">{{ __('admin.audit.clear') }}</button>
    </div>

    @if ($entries->isEmpty())
        <p class="muted">{{ __('admin.audit.empty') }}</p>
    @else
        <table>
            <thead><tr><th>{{ __('admin.changes.when') }}</th><th>{{ __('admin.audit.actor') }}</th><th>{{ __('admin.audit.action') }}</th><th>{{ __('admin.audit.subject') }}</th><th>{{ __('admin.audit.details') }}</th></tr></thead>
            <tbody>
                @foreach ($entries as $entry)
                    <tr wire:key="audit-{{ $entry->id }}">
                        <td class="muted">{{ $entry->created_at?->toDateTimeString() }}</td>
                        <td>{{ $entry->actor?->name ?? __('admin.audit.system') }}@if ($entry->actor)<br><span class="muted">{{ $entry->actor->email }}</span>@endif</td>
                        <td><code>{{ $entry->action }}</code></td>
                        <td>@if ($entry->subject_type){{ class_basename($entry->subject_type) }} #{{ $entry->subject_id }}@endif</td>
                        <td>
                            @if ($entry->reason)<p>{{ $entry->reason }}</p>@endif
                            @if ($entry->changes)<pre>{{ json_encode($entry->changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>@endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        {{ $entries->links('livewire.admin.pagination') }}
    @endif
</div>
