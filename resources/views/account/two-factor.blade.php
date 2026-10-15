@extends('layouts.account')

@section('title', __('staff.two_factor.title'))

@section('account')
    {{-- 2-step login (2FA doc §3): enable → scan the QR code or type the key → confirm with a code →
         recovery codes shown once; regenerate or turn off later. Fortify does the work. --}}
    @php($user = auth()->user())
    <p class="mb-4 text-ui text-fg-muted">{{ __('staff.two_factor.intro') }}</p>

    @if (! $user->two_factor_secret)
        <x-ui.badge variant="neutral" class="mb-4">{{ __('staff.two_factor.status_off') }}</x-ui.badge>
        <form method="POST" action="{{ route('two-factor.enable') }}">
            @csrf
            <x-ui.button type="submit" icon="shield-check">{{ __('staff.two_factor.enable') }}</x-ui.button>
        </form>
    @elseif (! $user->two_factor_confirmed_at)
        <x-ui.badge variant="warning" class="mb-4">{{ __('staff.two_factor.status_pending') }}</x-ui.badge>
        <ol class="space-y-4">
            <li class="space-y-2">
                <p class="text-ui font-medium text-fg">1. {{ __('staff.two_factor.scan') }}</p>
                {{-- The QR code always on white, so phone cameras read it in dark mode too. --}}
                <div class="inline-block rounded-card border border-line bg-white p-3" aria-hidden="true">{!! $user->twoFactorQrCodeSvg() !!}</div>
                <p class="text-small text-fg-muted">{{ __('staff.two_factor.setup_key') }}: <code class="rounded bg-surface-muted px-1.5 py-0.5 font-mono text-fg">{{ decrypt($user->two_factor_secret) }}</code></p>
            </li>
            <li>
                <form method="POST" action="{{ route('two-factor.confirm') }}" novalidate class="space-y-3">
                    @csrf
                    <x-ui.input :label="'2. '.__('staff.two_factor.code')" name="code" inputmode="numeric" autocomplete="one-time-code" required maxlength="6"
                        :error="$errors->getBag('confirmTwoFactorAuthentication')->first('code') ?: null" class="tabular tracking-[0.3em]" />
                    <x-ui.button type="submit">{{ __('staff.two_factor.confirm') }}</x-ui.button>
                </form>
            </li>
        </ol>
    @else
        <x-ui.badge variant="success" icon="circle-check" class="mb-4">{{ __('staff.two_factor.status_on') }}</x-ui.badge>

        @if (in_array(session('status'), ['two-factor-authentication-confirmed', 'recovery-codes-generated'], true))
            <x-ui.alert variant="warning" :title="__('staff.two_factor.recovery_title')" class="mb-4">
                {{ __('staff.two_factor.recovery_intro') }}
                <ul class="mt-3 grid grid-cols-2 gap-2 font-mono text-small text-fg">
                    @foreach ($user->recoveryCodes() as $code)
                        <li class="rounded-control bg-surface-muted px-2 py-1">{{ $code }}</li>
                    @endforeach
                </ul>
            </x-ui.alert>
        @endif

        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">
                @csrf
                <x-ui.button type="submit" variant="secondary">{{ __('staff.two_factor.regenerate') }}</x-ui.button>
            </form>
            <form method="POST" action="{{ route('two-factor.disable') }}">
                @csrf
                @method('DELETE')
                <x-ui.button type="submit" variant="ghost" class="text-danger">{{ __('staff.two_factor.disable') }}</x-ui.button>
            </form>
        </div>
    @endif
@endsection
