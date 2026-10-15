{{-- Staff (Owner only): roles, two-factor status and reset, and adding a member by email. Every change
     asks for the password again; the actions enforce the hierarchy and keep at least one Owner. --}}
<div>
    <x-admin.page-header :title="__('admin.nav.staff')" :count="$staff->count()" :description="__('admin.staff.intro')" />

    @if (session('status'))
        <x-ui.alert variant="success" role="status" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif
    @php($roleOptions = collect($roles)->mapWithKeys(fn ($r) => [$r->value => __('staff.manage.roles.'.$r->value)])->all() + ['none' => __('staff.manage.remove')])
    @error('role')<x-ui.alert variant="danger" role="alert" class="mb-4">{{ $message }}</x-ui.alert>@enderror

    <x-ui.table :caption="__('admin.nav.staff')">
        <thead>
            <tr>
                <th scope="col">{{ __('auth.ui.name') }}</th>
                <th scope="col">{{ __('staff.manage.role') }}</th>
                <th scope="col">{{ __('staff.two_factor.title') }}</th>
                <th scope="col"><span class="sr-only">{{ __('admin.staff.actions') }}</span></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($staff as $member)
                @php($self = $member->is(auth()->user()))
                <tr wire:key="staff-{{ $member->id }}">
                    <td data-label="{{ __('auth.ui.name') }}">
                        <div class="flex min-w-0 items-center gap-2.5">
                            <x-ui.avatar :name="$member->name" size="32" />
                            <div class="min-w-0">
                                <p class="truncate font-medium text-fg">{{ $member->name }}@if ($self) <span class="font-normal text-fg-subtle">({{ __('admin.staff.you') }})</span>@endif</p>
                                <p class="truncate text-fg-subtle">{{ $member->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td data-label="{{ __('staff.manage.role') }}">
                        <div class="flex items-center gap-2">
                            <x-ui.select id="role-{{ $member->id }}" wire:model="roleFor.{{ $member->id }}" class="min-w-40"
                                aria-label="{{ __('staff.manage.role') }}: {{ $member->name }}"
                                :options="$roleOptions" :selected="$roleFor[$member->id] ?? null" />
                            <x-ui.button size="sm" variant="secondary" wire:click="changeRole({{ $member->id }})">{{ __('staff.manage.save') }}</x-ui.button>
                        </div>
                    </td>
                    <td data-label="{{ __('staff.two_factor.title') }}">
                        @if ($member->hasConfirmedTwoFactor())
                            <x-ui.badge variant="success" icon="shield-check">{{ __('admin.staff.two_factor_on') }}</x-ui.badge>
                        @else
                            <x-ui.badge variant="warning" icon="triangle-alert">{{ __('admin.staff.two_factor_off') }}</x-ui.badge>
                        @endif
                    </td>
                    <td class="text-right">
                        @if ($member->hasConfirmedTwoFactor() && ! $self)
                            <x-ui.button size="sm" variant="ghost" icon="rotate-ccw" wire:click="resetTwoFactor({{ $member->id }})"
                                wire:confirm="{{ __('admin.staff.confirm_reset', ['name' => $member->name]) }}">{{ __('staff.manage.reset_two_factor') }}</x-ui.button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </x-ui.table>

    <section aria-labelledby="appoint-title" class="mt-6 max-w-2xl rounded-card border border-line bg-surface p-4 lg:p-5">
        <h2 id="appoint-title" class="text-h3 font-semibold">{{ __('staff.manage.appoint') }}</h2>
        <p class="mt-1 mb-4 text-small text-fg-muted">{{ __('staff.manage.appoint_help') }}</p>
        <form wire:submit="appoint" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_12rem_auto] sm:items-end">
            <x-ui.input type="email" :label="__('auth.ui.email')" id="staff-email" wire:model="email" maxlength="191" autocomplete="off" :error="$errors->first('email')" />
            <x-ui.select :label="__('staff.manage.role')" id="staff-role" wire:model="role" placeholder=""
                :options="collect($roles)->mapWithKeys(fn ($r) => [$r->value => __('staff.manage.roles.'.$r->value)])->all()" />
            <x-ui.button type="submit" icon="plus">{{ __('admin.staff.add') }}</x-ui.button>
        </form>
    </section>
</div>
