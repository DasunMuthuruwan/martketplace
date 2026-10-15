@extends('layouts.account')

@section('title', __('account.security.title'))

@section('account')
    <section aria-labelledby="password-title" class="space-y-4">
        <h2 id="password-title" class="text-h2 font-semibold">{{ __('account.security.password_title') }}</h2>
        @php($bag = $errors->getBag('updatePassword'))
        <form method="POST" action="{{ route('user-password.update') }}" novalidate class="space-y-4">
            @csrf
            @method('PUT')
            <x-ui.input type="password" :label="__('account.security.current_password')" name="current_password" :error="$bag->first('current_password') ?: null" required autocomplete="current-password" />
            <x-ui.input type="password" :label="__('account.security.new_password')" name="password" :hint="__('auth.ui.password_hint').' '.__('account.security.other_devices')"
                :error="$bag->first('password') ?: null" required autocomplete="new-password" />
            <x-ui.input type="password" :label="__('auth.ui.password_confirmation')" name="password_confirmation" required autocomplete="new-password" />
            <x-ui.button type="submit">{{ __('account.security.save_password') }}</x-ui.button>
        </form>
    </section>

    @if ($user->hasVerifiedEmail())
        <section aria-labelledby="twofa-title" class="mt-8 space-y-2 border-t border-line pt-6">
            <h2 id="twofa-title" class="text-h2 font-semibold">{{ __('account.security.two_factor') }}</h2>
            <p class="text-ui text-fg-muted">{{ $user->hasConfirmedTwoFactor() ? __('staff.two_factor.status_on') : __('staff.two_factor.status_off') }}</p>
            <x-ui.button variant="secondary" icon="shield-check" :href="route('account.two-factor')">{{ __('account.security.two_factor_link') }}</x-ui.button>
        </section>
    @endif

    <section aria-labelledby="delete-title" class="mt-8 space-y-3 border-t border-line pt-6">
        <h2 id="delete-title" class="text-h2 font-semibold text-danger">{{ __('account.delete.title') }}</h2>
        <p class="text-ui text-fg-muted">{{ __('account.delete.intro', ['days' => config('marketplace.retention.deleted_account_grace')]) }}</p>
        @php($deleteBag = $errors->getBag('deleteAccount'))
        <form method="POST" action="{{ route('account.destroy') }}" novalidate class="space-y-4">
            @csrf
            @method('DELETE')
            <x-ui.input type="password" :label="__('account.delete.password')" name="password" id="f-delete-password" :error="$deleteBag->first('password') ?: null" required autocomplete="current-password" />
            <x-ui.button type="submit" variant="danger">{{ __('account.delete.button') }}</x-ui.button>
        </form>
    </section>
@endsection
