@extends('layouts.account')

@section('title', __('account.contact.title'))

@section('account')

    <p>{{ __('account.contact.intro') }}</p>
    @if ($user->phone === null)
        <p class="status">{{ __('account.contact.no_phone') }} <a href="{{ route('account.settings.profile') }}">{{ __('account.nav.profile') }}</a></p>
    @endif

    @php($bag = $errors->getBag('updateContactSettings'))
    @php($current = old('contact_visibility', $user->contact_visibility->value))
    <form method="POST" action="{{ route('account.settings.contact.update') }}" novalidate>
        @csrf
        @method('PUT')

        <fieldset @if ($bag->has('contact_visibility')) aria-describedby="visibility-error" @endif>
            <legend>{{ __('account.contact.visibility') }}</legend>
            @foreach ($visibilities as $visibility)
                <label><input type="radio" name="contact_visibility" value="{{ $visibility->value }}" @checked($current === $visibility->value)> {{ __('account.contact.visibility_options.'.$visibility->value) }}</label>
            @endforeach
        </fieldset>
        @if ($bag->has('contact_visibility'))<p class="error" id="visibility-error">{{ $bag->first('contact_visibility') }}</p>@endif

        @include('partials.phone-field', [
            'name' => 'whatsapp_phone',
            'countryName' => 'whatsapp_country',
            'label' => __('account.contact.whatsapp_phone'),
            'countryLabel' => __('account.contact.whatsapp_country'),
            'hint' => __('account.contact.whatsapp_hint'),
            'value' => old('whatsapp_phone', $user->whatsapp_phone),
            'countryValue' => old('whatsapp_country', $whatsappCountry),
            'bag' => 'updateContactSettings',
        ])

        <button type="submit">{{ __('account.contact.save') }}</button>
    </form>
@endsection
