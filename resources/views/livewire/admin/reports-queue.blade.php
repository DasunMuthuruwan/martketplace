@use('App\Enums\ReportStatus', 'RS')
{{-- Reports queue (MOD-01/02): the list on the left, the report on the right (list or report on phones). --}}
<div data-shortcuts>
    <x-admin.page-header :title="__('admin.nav.reports')" :count="$reports->total()" :description="__('admin.reports.intro')">
        @include('livewire.admin.partials.shortcuts', ['keys' => ['j' => 'next', 'k' => 'previous']])
        <x-slot:filters>
            <x-ui.select :label="__('admin.reports.status')" id="filter-status" wire:model.live="status" class="min-w-40"
                :options="__('admin.reports.statuses')" />
            <x-ui.select :label="__('admin.reports.type')" id="filter-type" wire:model.live="type" :placeholder="__('public.any')" class="min-w-40"
                :options="__('admin.reports.types')" />
        </x-slot:filters>
    </x-admin.page-header>

    @if (session('status'))
        <x-ui.alert variant="success" role="status" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    @if ($reports->isEmpty() && ! $selected)
        <x-ui.empty-state icon="flag" :title="__('admin.reports.empty')">{{ __('admin.reports.empty_text') }}</x-ui.empty-state>
    @else
        <div class="grid items-start gap-4 lg:grid-cols-[minmax(260px,340px)_minmax(0,1fr)]">
            <div @class(['min-w-0', 'max-lg:hidden' => $selected])>
                <ul class="divide-y divide-line overflow-hidden rounded-card border border-line bg-surface" aria-label="{{ __('admin.nav.reports') }}">
                    @foreach ($reports as $report)
                        @php($current = $selected?->id === $report->id)
                        <li wire:key="report-{{ $report->id }}">
                            <button type="button" wire:click="select({{ $report->id }})" data-queue-item @if ($current) aria-current="true" @endif
                                @class([
                                    'flex w-full items-start gap-3 px-3 py-2.5 text-left hover:bg-surface-hover',
                                    'bg-surface-muted shadow-[inset_3px_0_0_var(--fg)]' => $current,
                                ])>
                                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-surface-muted text-fg-muted">
                                    <x-ui.icon :name="$report->reportable_type === 'user' ? 'user' : 'package'" size="16" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-ui font-medium text-fg">{{ __('reports.reasons.'.$report->reason->value) }}</span>
                                    <span class="block truncate text-small text-fg-muted">{{ __('admin.reports.types.'.$report->reportable_type) }} #{{ $report->reportable_id }} · {{ $report->created_at->diffForHumans(short: true) }}</span>
                                    <span class="block truncate text-small text-fg-subtle">{{ $report->reporter?->name ?? __('admin.reports.anonymous') }}</span>
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>
                {{ $reports->links('livewire.admin.pagination') }}
            </div>

            <div class="min-w-0">
                @if ($selected)
                    <x-ui.button variant="ghost" size="sm" icon="chevron-left" wire:click="close" class="mb-2 lg:hidden">{{ __('admin.reports.back') }}</x-ui.button>
                    <article aria-labelledby="report-title" class="space-y-5 rounded-card border border-line bg-surface p-4 lg:p-5" wire:key="report-pane-{{ $selected->id }}">
                        <header class="space-y-1">
                            <x-ui.badge :variant="$selected->status === RS::Open ? 'warning' : 'outline'">{{ __('admin.reports.statuses.'.$selected->status->value) }}</x-ui.badge>
                            <h2 id="report-title" class="text-h2 font-semibold">{{ __('reports.reasons.'.$selected->reason->value) }}</h2>
                            <p class="text-small text-fg-muted">{{ __('admin.reports.reporter') }}: {{ $selected->reporter?->name ?? __('admin.reports.anonymous') }} · {{ $selected->created_at->toDayDateTimeString() }}</p>
                        </header>

                        @if ($selected->details)
                            <blockquote class="prose-user whitespace-pre-line rounded-control bg-surface-muted p-3 text-ui text-fg">{{ $selected->details }}</blockquote>
                        @endif

                        <section aria-labelledby="report-target" class="rounded-control border border-line p-3">
                            <h3 id="report-target" class="mb-1 text-small text-fg-muted">{{ __('admin.reports.reported_item') }}</h3>
                            @if ($target instanceof \App\Models\Listing)
                                <p class="flex flex-wrap items-center gap-2 text-ui">
                                    <x-ui.icon name="package" size="16" class="text-fg-muted" />
                                    @if ($target->isPublic())
                                        <a href="{{ $target->publicUrl() }}" target="_blank" rel="noopener" class="font-medium text-fg underline underline-offset-2">{{ $target->title }}</a>
                                    @else
                                        <span class="font-medium text-fg">{{ $target->title }}</span>
                                    @endif
                                    <x-ui.badge variant="outline">{{ $target->trashed() ? __('admin.reports.deleted') : __('admin.listing_statuses.'.$target->status->value) }}</x-ui.badge>
                                </p>
                            @elseif ($target instanceof \App\Models\User)
                                <p class="flex flex-wrap items-center gap-2 text-ui">
                                    <x-ui.icon name="user" size="16" class="text-fg-muted" />
                                    <span class="font-medium text-fg">{{ $target->name }}</span>
                                    <x-ui.badge variant="outline">{{ $target->trashed() ? __('admin.reports.deleted') : __('staff.accounts.statuses.'.$target->status->value) }}</x-ui.badge>
                                </p>
                            @else
                                <p class="text-ui text-fg-subtle">{{ __('admin.reports.target_gone') }}</p>
                            @endif
                        </section>

                        @if ($selected->status === RS::Open)
                            @foreach (['status', 'reason', 'note'] as $key)
                                @if ($errors->has($key))<x-ui.alert variant="danger" role="alert">{{ $errors->first($key) }}</x-ui.alert>@endif
                            @endforeach
                            <x-ui.textarea :label="__('admin.reports.note')" id="note" wire:model="note" rows="3" :maxlength="500" :hint="__('admin.reports.note_hint')" />
                            <div class="flex flex-wrap gap-2 border-t border-line pt-4">
                                <x-ui.button icon="check" wire:click="action">{{ __('admin.reports.action') }}</x-ui.button>
                                @if ($canSuspend && $target instanceof \App\Models\Listing)
                                    <x-ui.button variant="danger" icon="ban" wire:click="suspendAndAction">{{ __('admin.reports.suspend_listing') }}</x-ui.button>
                                @endif
                                <x-ui.button variant="secondary" icon="x" wire:click="dismiss">{{ __('admin.reports.dismiss') }}</x-ui.button>
                            </div>
                        @else
                            <p class="text-small text-fg-muted">{{ __('admin.reports.closed', ['when' => $selected->handled_at?->diffForHumans() ?? '–']) }}@if ($selected->resolution_note) · {{ $selected->resolution_note }}@endif</p>
                        @endif
                    </article>
                @else
                    <x-ui.empty-state icon="flag" :title="__('admin.reports.choose')" class="max-lg:hidden">{{ __('admin.moderation.choose_text') }}</x-ui.empty-state>
                @endif
            </div>
        </div>
    @endif
</div>
