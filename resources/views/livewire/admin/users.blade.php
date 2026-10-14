<div>
    <h1>{{ __('admin.nav.users') }}</h1>

    @if (session('status'))
        <p class="status" role="status">{{ session('status') }}</p>
    @endif

    <p>
        <label>{{ __('staff.accounts.search') }}
            <input type="search" wire:model.live.debounce.400ms="q" maxlength="100">
        </label>
        <label>{{ __('admin.reports.status') }}
            <select wire:model.live="status">
                <option value="">{{ __('public.any') }}</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s->value }}">{{ __('staff.accounts.statuses.'.$s->value) }}</option>
                @endforeach
            </select>
        </label>
    </p>

    <table>
        <thead><tr><th>{{ __('auth.ui.name') }}</th><th>{{ __('admin.reports.status') }}</th><th>{{ __('staff.accounts.joined') }}</th><th></th></tr></thead>
        <tbody>
            @foreach ($users as $user)
                <tr wire:key="user-{{ $user->id }}">
                    <td>{{ $user->name }} @if ($user->staffRole())<span class="muted">({{ $user->staffRole()->value }})</span>@endif</td>
                    <td>{{ __('staff.accounts.statuses.'.$user->status->value) }}</td>
                    <td class="muted">{{ $user->created_at?->toFormattedDateString() }}</td>
                    <td><button type="button" wire:click="select({{ $user->id }})">{{ __('admin.moderation.review') }}</button></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    {{ $users->links('livewire.admin.pagination') }}

    @if ($selected)
        <section class="pane" aria-labelledby="user-title">
            <h2 id="user-title">{{ $selected->name }} @if ($selected->trashed())<span class="muted">({{ __('admin.reports.deleted') }})</span>@endif</h2>
            <p class="muted">
                #{{ $selected->id }} · {{ __('staff.accounts.statuses.'.$selected->status->value) }}
                @if ($selected->status_reason) · {{ $selected->status_reason }} @endif
                @if ($selected->suspended_until) · {{ __('staff.accounts.until', ['date' => $selected->suspended_until->toFormattedDayDateString()]) }} @endif
                · {{ $selected->hasVerifiedEmail() ? __('public.email_verified') : __('admin.moderation.unverified') }}
                @if ($details['role']) · {{ $details['role'] }} @endif
            </p>
            @if ($details['contact'])
                <p>{{ $selected->email }} @if ($selected->phone) · {{ $selected->phone }} @endif</p>
            @endif
            <p class="muted">
                @foreach ($details['listings'] as $status => $total) {{ $status }}: {{ $total }} · @endforeach
                {{ __('staff.accounts.reports', ['count' => $details['reports']]) }}
            </p>

            @error('status')<p class="error" role="alert">{{ $message }}</p>@enderror
            @error('reason')<p class="error" role="alert">{{ $message }}</p>@enderror
            @error('until')<p class="error" role="alert">{{ $message }}</p>@enderror

            @if (in_array(true, $details['can'], true))
                <label for="reason">{{ __('staff.accounts.reason') }}</label>
                <textarea id="reason" wire:model="reason" rows="2" maxlength="500"></textarea>
                @if ($details['can']['suspend'])
                    <label for="until">{{ __('staff.accounts.until_label') }}</label>
                    <input type="date" id="until" wire:model="until">
                @endif
                <p>
                    @if ($details['can']['suspend'])<button type="button" wire:click="suspend">{{ __('staff.accounts.suspend') }}</button>@endif
                    @if ($details['can']['ban'])<button type="button" wire:click="ban">{{ __('staff.accounts.ban') }}</button>@endif
                    @if ($details['can']['reinstate'])<button type="button" wire:click="reinstate">{{ __('staff.accounts.reinstate') }}</button>@endif
                    @if ($details['can']['verify'])<button type="button" wire:click="verifyEmail">{{ __('staff.accounts.verify') }}</button>@endif
                    @if ($details['can']['delete'])<button type="button" wire:click="delete">{{ __('staff.accounts.delete') }}</button>@endif
                </p>
            @endif

            @if ($details['legalHold'])
                <p>
                    @if ($selected->legal_hold)<strong>{{ __('staff.accounts.on_hold') }}</strong>@endif
                    <button type="button" wire:click="toggleLegalHold">{{ $selected->legal_hold ? __('staff.accounts.lift_hold') : __('staff.accounts.set_hold') }}</button>
                    <span class="muted">{{ __('staff.accounts.hold_help') }}</span>
                </p>
                @unless (in_array(true, $details['can'], true))
                    <label for="hold-reason">{{ __('staff.accounts.reason') }}</label>
                    <textarea id="hold-reason" wire:model="reason" rows="2" maxlength="500"></textarea>
                @endunless
            @endif
        </section>
    @endif
</div>
