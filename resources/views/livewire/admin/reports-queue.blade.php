<div>
    <h1>{{ __('admin.nav.reports') }}</h1>

    @if (session('status'))
        <p class="status" role="status">{{ session('status') }}</p>
    @endif

    <p>
        <label>{{ __('admin.reports.status') }}
            <select wire:model.live="status">
                @foreach (['open', 'actioned', 'dismissed'] as $value)
                    <option value="{{ $value }}">{{ __('admin.reports.statuses.'.$value) }}</option>
                @endforeach
            </select>
        </label>
        <label>{{ __('admin.reports.type') }}
            <select wire:model.live="type">
                <option value="">{{ __('public.any') }}</option>
                <option value="listing">{{ __('admin.reports.types.listing') }}</option>
                <option value="user">{{ __('admin.reports.types.user') }}</option>
            </select>
        </label>
    </p>

    @if ($reports->isEmpty())
        <p>{{ __('admin.reports.empty') }}</p>
    @else
        <table>
            <thead><tr><th>{{ __('admin.reports.type') }}</th><th>{{ __('admin.reports.reason') }}</th><th>{{ __('admin.reports.reporter') }}</th><th>{{ __('admin.moderation.submitted') }}</th><th></th></tr></thead>
            <tbody>
                @foreach ($reports as $report)
                    <tr wire:key="report-{{ $report->id }}">
                        <td>{{ __('admin.reports.types.'.$report->reportable_type) }} #{{ $report->reportable_id }}</td>
                        <td>{{ __('reports.reasons.'.$report->reason->value) }}</td>
                        <td>{{ $report->reporter?->name ?? '–' }}</td>
                        <td class="muted">{{ $report->created_at->diffForHumans() }}</td>
                        <td><button type="button" wire:click="select({{ $report->id }})">{{ __('admin.moderation.review') }}</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        {{ $reports->links('livewire.admin.pagination') }}
    @endif

    @if ($selected)
        <section class="pane" aria-labelledby="report-title">
            <h2 id="report-title">{{ __('reports.reasons.'.$selected->reason->value) }}</h2>
            @if ($selected->details)
                <p>{{ $selected->details }}</p>
            @endif
            <p class="muted">{{ __('admin.reports.reporter') }}: {{ $selected->reporter?->name ?? '–' }} · {{ $selected->created_at->toDayDateTimeString() }}</p>

            @if ($target instanceof \App\Models\Listing)
                <p>{{ __('admin.reports.types.listing') }}:
                    @if ($target->isPublic())<a href="{{ $target->publicUrl() }}">{{ $target->title }}</a>@else {{ $target->title }} @endif
                    <span class="muted">({{ $target->trashed() ? __('admin.reports.deleted') : $target->status->value }})</span>
                </p>
            @elseif ($target instanceof \App\Models\User)
                <p>{{ __('admin.reports.types.user') }}: {{ $target->name }} <span class="muted">({{ $target->trashed() ? __('admin.reports.deleted') : $target->status->value }})</span></p>
            @else
                <p class="muted">{{ __('admin.reports.target_gone') }}</p>
            @endif

            @if ($selected->status === \App\Enums\ReportStatus::Open)
                @error('status')<p class="error" role="alert">{{ $message }}</p>@enderror
                @error('reason')<p class="error" role="alert">{{ $message }}</p>@enderror
                @error('note')<p class="error" role="alert">{{ $message }}</p>@enderror
                <label for="note">{{ __('admin.moderation.note') }}</label>
                <textarea id="note" wire:model="note" rows="3" maxlength="500"></textarea>
                <p>
                    <button type="button" wire:click="dismiss">{{ __('admin.reports.dismiss') }}</button>
                    <button type="button" wire:click="action">{{ __('admin.reports.action') }}</button>
                    @if ($canSuspend && $target instanceof \App\Models\Listing)
                        <button type="button" wire:click="suspendAndAction">{{ __('admin.reports.suspend_listing') }}</button>
                    @endif
                </p>
            @else
                <p class="muted">{{ __('admin.reports.statuses.'.$selected->status->value) }} · {{ $selected->resolution_note }}</p>
            @endif
        </section>
    @endif
</div>
