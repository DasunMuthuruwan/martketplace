@extends('layouts.plain')

@section('title', __('auth.ui.register_title'))

@section('content')
    @if ($countries->isEmpty())
        <p class="status">{{ __('auth.ui.no_countries') }}</p>
    @else
        <form method="POST" action="{{ route('register.store') }}" novalidate>
            @csrf

            <label for="name">{{ __('auth.ui.name') }}</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="name" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
            @error('name')<p class="error" id="name-error">{{ $message }}</p>@enderror

            <label for="email">{{ __('auth.ui.email') }}</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required maxlength="191" autocomplete="email" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email')<p class="error" id="email-error">{{ $message }}</p>@enderror

            <label for="password">{{ __('auth.ui.password') }}</label>
            <input type="password" id="password" name="password" required autocomplete="new-password" aria-describedby="password-hint @error('password') password-error @enderror">
            <p class="hint" id="password-hint">{{ __('auth.ui.password_hint') }}</p>
            @error('password')<p class="error" id="password-error">{{ $message }}</p>@enderror

            <label for="password_confirmation">{{ __('auth.ui.password_confirmation') }}</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">

            <label for="country_id">{{ __('auth.ui.country') }}</label>
            <select id="country_id" name="country_id" required @error('country_id') aria-invalid="true" aria-describedby="country-error" @enderror>
                <option value="">{{ __('auth.ui.choose_country') }}</option>
                @foreach ($countries as $country)
                    <option value="{{ $country->id }}" @selected((string) old('country_id') === (string) $country->id)>{{ $country->name }}</option>
                @endforeach
            </select>
            @error('country_id')<p class="error" id="country-error">{{ $message }}</p>@enderror

            <label for="phone_country">{{ __('auth.ui.phone_country') }}</label>
            <select id="phone_country" name="phone_country" autocomplete="tel-country-code">
                <option value=""></option>
                @foreach ($phoneCountries as $country)
                    <option value="{{ $country->iso2 }}" @selected(old('phone_country') === $country->iso2)>{{ $country->name }} (+{{ $country->phone_country_code }})</option>
                @endforeach
            </select>
            @error('phone_country')<p class="error">{{ $message }}</p>@enderror

            <label for="phone">{{ __('auth.ui.phone') }}</label>
            <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" maxlength="30" autocomplete="tel-national" inputmode="tel" aria-describedby="phone-hint @error('phone') phone-error @enderror">
            <p class="hint" id="phone-hint">{{ __('auth.ui.phone_hint') }}</p>
            @error('phone')<p class="error" id="phone-error">{{ $message }}</p>@enderror

            <label><input type="checkbox" name="terms" value="1" @checked(old('terms'))> {{ __('auth.ui.terms') }}</label>
            @error('terms')<p class="error">{{ $message }}</p>@enderror

            @include('partials.bot-protection')

            <button type="submit">{{ __('auth.ui.register') }}</button>
        </form>
    @endif

    <p>{{ __('auth.ui.have_account') }} <a href="{{ route('login') }}">{{ __('auth.ui.login') }}</a></p>
@endsection
