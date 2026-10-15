@extends('layouts.account')

@section('title', __('account.profile.title'))

@section('account')
    @php($bag = $errors->getBag('updateProfileInformation'))
    <form method="POST" action="{{ route('user-profile-information.update') }}" novalidate class="space-y-4">
        @csrf
        @method('PUT')
        <x-ui.input :label="__('auth.ui.name')" name="name" :value="old('name', $user->name)" :error="$bag->first('name') ?: null" required maxlength="100" autocomplete="name" />
        <x-ui.input type="email" :label="__('auth.ui.email')" name="email" :value="old('email', $user->email)" :error="$bag->first('email') ?: null" required maxlength="191" autocomplete="email" />
        <x-ui.input type="password" :label="__('account.profile.current_password')" name="current_password" :hint="__('account.profile.current_password_hint')"
            :error="$bag->first('current_password') ?: null" autocomplete="current-password" />
        <x-ui.select :label="__('auth.ui.country')" name="country_id" required :placeholder="__('auth.ui.choose_country')"
            :options="$countries->pluck('name', 'id')->all()" :selected="old('country_id', $user->country_id)" :error="$bag->first('country_id') ?: null" />

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

        <x-ui.button type="submit">{{ __('account.profile.save') }}</x-ui.button>
    </form>
@endsection
