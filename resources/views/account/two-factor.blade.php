@extends('layouts.account')

@section('title', __('staff.two_factor.title'))

@section('account')
    @php($user = auth()->user())
    <p>{{ __('staff.two_factor.intro') }}</p>

    @if (! $user->two_factor_secret)
        <p>{{ __('staff.two_factor.status_off') }}</p>
        <form method="POST" action="{{ route('two-factor.enable') }}">
            @csrf
            <button type="submit">{{ __('staff.two_factor.enable') }}</button>
        </form>
    @elseif (! $user->two_factor_confirmed_at)
        <p>{{ __('staff.two_factor.status_pending') }}</p>
        <div aria-hidden="true">{!! $user->twoFactorQrCodeSvg() !!}</div>
        <p>{{ __('staff.two_factor.setup_key') }}: <code>{{ decrypt($user->two_factor_secret) }}</code></p>
        <form method="POST" action="{{ route('two-factor.confirm') }}" novalidate>
            @csrf
            <label for="code">{{ __('staff.two_factor.code') }}</label>
            <input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required maxlength="6">
            @error('code', 'confirmTwoFactorAuthentication')<p class="error" role="alert">{{ $message }}</p>@enderror
            <button type="submit">{{ __('staff.two_factor.confirm') }}</button>
        </form>
    @else
        <p>{{ __('staff.two_factor.status_on') }}</p>

        @if (in_array(session('status'), ['two-factor-authentication-confirmed', 'recovery-codes-generated'], true))
            <h2>{{ __('staff.two_factor.recovery_title') }}</h2>
            <p>{{ __('staff.two_factor.recovery_intro') }}</p>
            <ul>
                @foreach ($user->recoveryCodes() as $code)
                    <li><code>{{ $code }}</code></li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">
            @csrf
            <button type="submit">{{ __('staff.two_factor.regenerate') }}</button>
        </form>
        <form method="POST" action="{{ route('two-factor.disable') }}">
            @csrf
            @method('DELETE')
            <button type="submit">{{ __('staff.two_factor.disable') }}</button>
        </form>
    @endif
@endsection
