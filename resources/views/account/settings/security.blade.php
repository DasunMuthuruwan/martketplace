@extends('layouts.plain')

@section('title', __('account.security.title'))

@section('content')
    @include('account.settings._nav')

    <h2>{{ __('account.security.password_title') }}</h2>
    @php($bag = $errors->getBag('updatePassword'))
    <form method="POST" action="{{ route('user-password.update') }}" novalidate>
        @csrf
        @method('PUT')

        <label for="current_password">{{ __('account.security.current_password') }}</label>
        <input type="password" id="current_password" name="current_password" required autocomplete="current-password" @if ($bag->has('current_password')) aria-invalid="true" aria-describedby="current_password-error" @endif>
        @if ($bag->has('current_password'))<p class="error" id="current_password-error">{{ $bag->first('current_password') }}</p>@endif

        <label for="password">{{ __('account.security.new_password') }}</label>
        <input type="password" id="password" name="password" required autocomplete="new-password" aria-describedby="password-hint @if ($bag->has('password')) password-error @endif" @if ($bag->has('password')) aria-invalid="true" @endif>
        <p class="hint" id="password-hint">{{ __('auth.ui.password_hint') }} {{ __('account.security.other_devices') }}</p>
        @if ($bag->has('password'))<p class="error" id="password-error">{{ $bag->first('password') }}</p>@endif

        <label for="password_confirmation">{{ __('auth.ui.password_confirmation') }}</label>
        <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">

        <button type="submit">{{ __('account.security.save_password') }}</button>
    </form>

    @if ($user->hasVerifiedEmail())
        <h2>{{ __('account.security.two_factor') }}</h2>
        <p><a href="{{ route('account.two-factor') }}">{{ __('account.security.two_factor_link') }}</a></p>
    @endif

    <h2>{{ __('account.delete.title') }}</h2>
    <p>{{ __('account.delete.intro', ['days' => config('marketplace.retention.deleted_account_grace')]) }}</p>
    @php($deleteBag = $errors->getBag('deleteAccount'))
    <form method="POST" action="{{ route('account.destroy') }}" novalidate>
        @csrf
        @method('DELETE')

        <label for="delete_password">{{ __('account.delete.password') }}</label>
        <input type="password" id="delete_password" name="password" required autocomplete="current-password" @if ($deleteBag->has('password')) aria-invalid="true" aria-describedby="delete_password-error" @endif>
        @if ($deleteBag->has('password'))<p class="error" id="delete_password-error">{{ $deleteBag->first('password') }}</p>@endif

        <button type="submit">{{ __('account.delete.button') }}</button>
    </form>
@endsection
