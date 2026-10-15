@use('App\Enums\UserStatus', 'US')
{{-- Users (ADM-04): search and status filter, the list on the left, the account on the right (list or
     account on phones). Actions appear only when the policy allows them; ban and delete ask for the
     password again (RequiresPermission::hasRecentPassword). --}}
<div data-shortcuts>
    <x-admin.page-header :title="__('admin.nav.users')" :count="$users->total()" :description="__('admin.users.intro')">
        @include('livewire.admin.partials.shortcuts', ['keys' => ['j' => 'next', 'k' => 'previous']])
        <x-slot:filters>
            <x-ui.input type="search" :label="__('staff.accounts.search')" id="filter-q" wire:model.live.debounce.400ms="q" maxlength="100" class="min-w-64" />
            <x-ui.select :label="__('admin.reports.status')" id="filter-status" wire:model.live="status" :placeholder="__('public.any')" class="min-w-40"
                :options="collect($statuses)->mapWithKeys(fn ($s) => [$s->value => __('staff.accounts.statuses.'.$s->value)])->all()" />
        </x-slot:filters>
    </x-admin.page-header>

    @if (session('status'))
        <x-ui.alert variant="success" role="status" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    @if ($users->isEmpty() && ! $selected)
        <x-ui.empty-state icon="users" :title="__('admin.users.empty')">{{ __('admin.users.empty_text') }}</x-ui.empty-state>
    @else
        <div class="grid items-start gap-4 lg:grid-cols-[minmax(260px,340px)_minmax(0,1fr)]">
            <div @class(['min-w-0', 'max-lg:hidden' => $selected])>
                <ul class="divide-y divide-line overflow-hidden rounded-card border border-line bg-surface" aria-label="{{ __('admin.nav.users') }}">
                    @foreach ($users as $user)
                        @php($current = $selected?->id === $user->id)
                        <li wire:key="user-{{ $user->id }}">
                            <button type="button" wire:click="select({{ $user->id }})" data-queue-item @if ($current) aria-current="true" @endif
                                @class([
                                    'flex w-full items-center gap-3 px-3 py-2.5 text-left hover:bg-surface-hover',
                                    'bg-surface-muted shadow-[inset_3px_0_0_var(--fg)]' => $current,
                                ])>
                                <x-ui.avatar :name="$user->name" size="32" />
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-ui font-medium text-fg">{{ $user->name }}</span>
                                    <span class="block truncate text-small text-fg-subtle">{{ __('staff.accounts.joined') }} {{ $user->created_at?->toFormattedDateString() }}</span>
                                </span>
                                <span class="flex shrink-0 flex-col items-end gap-1">
                                    @if ($user->status !== US::Active)
                                        <x-ui.badge :variant="$user->status === US::Banned ? 'danger' : 'warning'">{{ __('staff.accounts.statuses.'.$user->status->value) }}</x-ui.badge>
                                    @endif
                                    @if ($user->staffRole())<x-ui.badge variant="outline">{{ __('staff.manage.roles.'.$user->staffRole()->value) }}</x-ui.badge>@endif
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>
                {{ $users->links('livewire.admin.pagination') }}
            </div>

            <div class="min-w-0">
                @if ($selected)
                    <x-ui.button variant="ghost" size="sm" icon="chevron-left" wire:click="close" class="mb-2 lg:hidden">{{ __('admin.users.back') }}</x-ui.button>
                    <article aria-labelledby="user-title" class="space-y-5 rounded-card border border-line bg-surface p-4 lg:p-5" wire:key="user-pane-{{ $selected->id }}">
                        <header class="flex items-start gap-3">
                            <x-ui.avatar :name="$selected->name" size="64" />
                            <div class="min-w-0 space-y-1">
                                <h2 id="user-title" class="prose-user text-h2 font-semibold">{{ $selected->name }}</h2>
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($selected->trashed())
                                        <x-ui.badge variant="danger">{{ __('admin.reports.deleted') }}</x-ui.badge>
                                    @else
                                        <x-ui.badge :variant="match ($selected->status) { US::Active => 'success', US::Banned => 'danger', default => 'warning' }">{{ __('staff.accounts.statuses.'.$selected->status->value) }}</x-ui.badge>
                                    @endif
                                    @if ($details['role'])<x-ui.badge variant="outline">{{ __('staff.manage.roles.'.$details['role']) }}</x-ui.badge>@endif
                                    @if ($selected->legal_hold)<x-ui.badge variant="info">{{ __('staff.accounts.on_hold') }}</x-ui.badge>@endif
                                    <span class="text-small text-fg-subtle">ID {{ $selected->id }}</span>
                                </div>
                            </div>
                        </header>

                        @if ($selected->status_reason || $selected->suspended_until)
                            <x-ui.alert variant="warning">
                                {{ $selected->status_reason }}
                                @if ($selected->suspended_until) · {{ __('staff.accounts.until', ['date' => $selected->suspended_until->toFormattedDayDateString()]) }}@endif
                            </x-ui.alert>
                        @endif

                        <dl class="grid gap-x-6 text-small sm:grid-cols-2">
                            <div class="flex justify-between gap-4 border-b border-line py-1.5"><dt class="text-fg-muted">{{ __('staff.accounts.joined') }}</dt><dd class="font-medium text-fg">{{ $selected->created_at?->toFormattedDateString() }}</dd></div>
                            <div class="flex justify-between gap-4 border-b border-line py-1.5"><dt class="text-fg-muted">{{ __('admin.users.email_status') }}</dt><dd class="font-medium text-fg">{{ $selected->hasVerifiedEmail() ? __('public.email_verified') : __('admin.moderation.unverified') }}</dd></div>
                            @if ($details['contact'])
                                <div class="flex justify-between gap-4 border-b border-line py-1.5"><dt class="text-fg-muted">{{ __('admin.users.email_address') }}</dt><dd class="prose-user text-right font-medium text-fg">{{ $selected->email }}</dd></div>
                                <div class="flex justify-between gap-4 border-b border-line py-1.5"><dt class="text-fg-muted">{{ __('admin.users.phone') }}</dt><dd class="tabular font-medium text-fg">{{ $selected->phone ?: '–' }}</dd></div>
                            @endif
                            <div class="flex justify-between gap-4 border-b border-line py-1.5"><dt class="text-fg-muted">{{ __('admin.users.reports') }}</dt><dd class="tabular font-medium text-fg">{{ number_format($details['reports']) }}</dd></div>
                        </dl>

                        <section aria-labelledby="user-ads">
                            <h3 id="user-ads" class="mb-2 text-h3 font-semibold">{{ __('admin.users.ads') }}</h3>
                            @if ($details['listings'] === [])
                                <p class="text-small text-fg-subtle">{{ __('admin.users.no_ads') }}</p>
                            @else
                                <ul class="flex flex-wrap gap-2">
                                    @foreach ($details['listings'] as $status => $total)
                                        <li><x-ui.chip>{{ __('admin.listing_statuses.'.$status) }} · {{ number_format($total) }}</x-ui.chip></li>
                                    @endforeach
                                </ul>
                            @endif
                        </section>

                        @foreach (['status', 'reason', 'until'] as $key)
                            @if ($errors->has($key))<x-ui.alert variant="danger" role="alert">{{ $errors->first($key) }}</x-ui.alert>@endif
                        @endforeach

                        @if (in_array(true, $details['can'], true) || $details['legalHold'])
                            <section aria-labelledby="user-actions" class="space-y-3 border-t border-line pt-4">
                                <h3 id="user-actions" class="text-h3 font-semibold">{{ __('admin.users.actions') }}</h3>
                                <x-ui.textarea :label="__('staff.accounts.reason')" id="reason" wire:model="reason" rows="2" :maxlength="500" :hint="__('admin.users.reason_hint')" />
                                @if ($details['can']['suspend'])
                                    <div class="max-w-56"><x-ui.input type="date" :label="__('staff.accounts.until_label')" id="until" wire:model="until" /></div>
                                @endif
                                <div class="flex flex-wrap gap-2">
                                    @if ($details['can']['reinstate'])<x-ui.button icon="circle-check" wire:click="reinstate">{{ __('staff.accounts.reinstate') }}</x-ui.button>@endif
                                    @if ($details['can']['suspend'])<x-ui.button variant="secondary" icon="clock" wire:click="suspend">{{ __('staff.accounts.suspend') }}</x-ui.button>@endif
                                    @if ($details['can']['verify'])<x-ui.button variant="secondary" icon="badge-check" wire:click="verifyEmail">{{ __('staff.accounts.verify') }}</x-ui.button>@endif
                                </div>

                                @if ($details['can']['ban'] || $details['can']['delete'])
                                    <div class="space-y-2 rounded-control border border-danger/40 p-3">
                                        <p class="text-small text-fg-muted">{{ __('admin.users.danger_help') }}</p>
                                        <div class="flex flex-wrap gap-2">
                                            @if ($details['can']['ban'])<x-ui.button variant="danger" icon="ban" wire:click="ban">{{ __('staff.accounts.ban') }}</x-ui.button>@endif
                                            @if ($details['can']['delete'])<x-ui.button variant="danger" icon="trash-2" wire:click="delete" wire:confirm="{{ __('admin.users.confirm_delete', ['name' => $selected->name]) }}">{{ __('staff.accounts.delete') }}</x-ui.button>@endif
                                        </div>
                                    </div>
                                @endif

                                @if ($details['legalHold'])
                                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-control bg-surface-muted p-3">
                                        <p class="text-small text-fg-muted">{{ __('staff.accounts.hold_help') }}</p>
                                        <x-ui.button size="sm" variant="secondary" icon="shield-check" wire:click="toggleLegalHold">{{ $selected->legal_hold ? __('staff.accounts.lift_hold') : __('staff.accounts.set_hold') }}</x-ui.button>
                                    </div>
                                @endif
                            </section>
                        @endif
                    </article>
                @else
                    <x-ui.empty-state icon="user" :title="__('admin.users.choose')" class="max-lg:hidden">{{ __('admin.moderation.choose_text') }}</x-ui.empty-state>
                @endif
            </div>
        </div>
    @endif
</div>
