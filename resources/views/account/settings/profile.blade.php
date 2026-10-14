@extends('layouts.account')

@section('title', __('account.profile.title'))

@section('account')

    @php($bag = $errors->getBag('updateProfileInformation'))
    <form method="POST" action="{{ route('user-profile-information.update') }}" novalidate>
        @csrf
        @method('PUT')

        <label for="name">{{ __('auth.ui.name') }}</label>
        <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="100" autocomplete="name" @if ($bag->has('name')) aria-invalid="true" aria-describedby="name-error" @endif>
        @if ($bag->has('name'))<p class="error" id="name-error">{{ $bag->first('name') }}</p>@endif

        <label for="email">{{ __('auth.ui.email') }}</label>
        <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="191" autocomplete="email" @if ($bag->has('email')) aria-invalid="true" aria-describedby="email-error" @endif>
        @if ($bag->has('email'))<p class="error" id="email-error">{{ $bag->first('email') }}</p>@endif

        <label for="current_password">{{ __('account.profile.current_password') }}</label>
        <input type="password" id="current_password" name="current_password" autocomplete="current-password" aria-describedby="current_password-hint @if ($bag->has('current_password')) current_password-error @endif" @if ($bag->has('current_password')) aria-invalid="true" @endif>
        <p class="hint" id="current_password-hint">{{ __('account.profile.current_password_hint') }}</p>
        @if ($bag->has('current_password'))<p class="error" id="current_password-error">{{ $bag->first('current_password') }}</p>@endif

        <label for="country_id">{{ __('auth.ui.country') }}</label>
        <select id="country_id" name="country_id" required @if ($bag->has('country_id')) aria-invalid="true" aria-describedby="country-error" @endif>
            <option value="">{{ __('auth.ui.choose_country') }}</option>
            @foreach ($countries as $country)
                <option value="{{ $country->id }}" @selected((string) old('country_id', $user->country_id) === (string) $country->id)>{{ $country->name }}</option>
            @endforeach
        </select>
        @if ($bag->has('country_id'))<p class="error" id="country-error">{{ $bag->first('country_id') }}</p>@endif

        @include('partials.phone-field', [
            'name' => 'phone',
            'countryName' => 'phone_country',
            'label' => __('account.profile.phone'),
            'countryLabel' => __('auth.ui.phone_country'),
            'hint' => __('account.profile.phone_hint'),
            'value' => old('phone', $user->phone),
            'countryValue' => old('phone_country', $user->phone_country),
            'bag' => 'updateProfileInformation',
        ])

        <button type="submit">{{ __('account.profile.save') }}</button>
    </form>
@endsection
