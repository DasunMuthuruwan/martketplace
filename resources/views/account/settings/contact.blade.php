@extends('layouts.account')

@section('title', __('account.contact.title'))

@section('account')
    <p class="mb-4 text-ui text-fg-muted">{{ __('account.contact.intro') }}</p>
    @if ($user->phone === null)
        <x-ui.alert variant="warning" class="mb-4">
            {{ __('account.contact.no_phone') }}
            <x-slot:actions><x-ui.button size="sm" variant="secondary" :href="route('account.settings.profile')">{{ __('account.nav.profile') }}</x-ui.button></x-slot:actions>
        </x-ui.alert>
    @endif

    @php
        $bag = $errors->getBag('updateContactSettings');
        $current = old('contact_visibility', $user->contact_visibility->value);
    @endphp
    <form method="POST" action="{{ route('account.settings.contact.update') }}" novalidate class="space-y-5">
        @csrf
        @method('PUT')

        <fieldset @if ($bag->has('contact_visibility')) aria-describedby="visibility-error" @endif>
            <legend class="mb-2 text-ui font-medium text-fg">{{ __('account.contact.visibility') }}</legend>
            <div class="space-y-2">
                @foreach ($visibilities as $visibility)
                    <label class="flex cursor-pointer items-start gap-3 rounded-card border border-line p-3 has-[:checked]:border-primary">
                        <input type="radio" name="contact_visibility" value="{{ $visibility->value }}" @checked($current === $visibility->value) class="mt-1 size-4 accent-(--primary)">
                        <span class="text-ui text-fg">{{ __('account.contact.visibility_options.'.$visibility->value) }}</span>
                    </label>
                @endforeach
            </div>
            @if ($bag->has('contact_visibility'))<p class="mt-1.5 text-small text-danger" id="visibility-error">{{ $bag->first('contact_visibility') }}</p>@endif
        </fieldset>

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

        <x-ui.button type="submit">{{ __('account.contact.save') }}</x-ui.button>
    </form>
@endsection
