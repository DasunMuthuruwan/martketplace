<div>
    <h1>{{ __('admin.nav.staff') }}</h1>

    @if (session('status'))
        <p class="status" role="status">{{ session('status') }}</p>
    @endif
    @error('role')<p class="error" role="alert">{{ $message }}</p>@enderror

    <table>
        <thead><tr><th>{{ __('auth.ui.name') }}</th><th>{{ __('staff.manage.role') }}</th><th>{{ __('staff.two_factor.title') }}</th><th></th></tr></thead>
        <tbody>
            @foreach ($staff as $member)
                <tr wire:key="staff-{{ $member->id }}">
                    <td>{{ $member->name }}<br><span class="muted">{{ $member->email }}</span></td>
                    <td>
                        <select wire:model="roleFor.{{ $member->id }}" aria-label="{{ __('staff.manage.role') }}: {{ $member->name }}">
                            @foreach ($roles as $role)
                                <option value="{{ $role->value }}">{{ __('staff.manage.roles.'.$role->value) }}</option>
                            @endforeach
                            <option value="none">{{ __('staff.manage.remove') }}</option>
                        </select>
                        <button type="button" wire:click="changeRole({{ $member->id }})">{{ __('staff.manage.save') }}</button>
                    </td>
                    <td>{{ $member->hasConfirmedTwoFactor() ? __('staff.two_factor.status_on') : __('staff.two_factor.status_off') }}</td>
                    <td>
                        @if ($member->hasConfirmedTwoFactor() && ! $member->is(auth()->user()))
                            <button type="button" wire:click="resetTwoFactor({{ $member->id }})">{{ __('staff.manage.reset_two_factor') }}</button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>{{ __('staff.manage.appoint') }}</h2>
    @error('email')<p class="error" role="alert">{{ $message }}</p>@enderror
    <label for="staff-email">{{ __('auth.ui.email') }}</label>
    <input type="email" id="staff-email" wire:model="email" maxlength="191">
    <label for="staff-role">{{ __('staff.manage.role') }}</label>
    <select id="staff-role" wire:model="role">
        <option value=""></option>
        @foreach ($roles as $role)
            <option value="{{ $role->value }}">{{ __('staff.manage.roles.'.$role->value) }}</option>
        @endforeach
    </select>
    <button type="button" wire:click="appoint">{{ __('staff.manage.appoint') }}</button>
    <p class="muted">{{ __('staff.manage.appoint_help') }}</p>
</div>
