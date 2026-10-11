@extends('layouts.plain')

@section('title', __('staff.two_factor.challenge_title'))

@section('content')
    <p>{{ __('staff.two_factor.challenge_intro') }}</p>
    <form method="POST" action="{{ route('two-factor.login.store') }}" novalidate>
        @csrf
        <label for="code">{{ __('staff.two_factor.code') }}</label>
        <input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus>
        @error('code')<p class="error" role="alert">{{ $message }}</p>@enderror

        <details>
            <summary>{{ __('staff.two_factor.use_recovery') }}</summary>
            <label for="recovery_code">{{ __('staff.two_factor.recovery_code') }}</label>
            <input type="text" id="recovery_code" name="recovery_code" autocomplete="off">
            @error('recovery_code')<p class="error" role="alert">{{ $message }}</p>@enderror
        </details>

        <button type="submit">{{ __('staff.two_factor.continue') }}</button>
    </form>
@endsection
