{{--
    Phone number with a country picker (GLB-17). Stored as E.164; validated per country on the server.
    The country list is long, so it is a searchable combobox (component doc §2).
    Params: name, countryName, label, countryLabel, hint, value, countryValue, phoneCountries, bag (error bag, default "default").
--}}
@php
    $errorBag = $errors->getBag($bag ?? 'default');
    $options = $phoneCountries->mapWithKeys(fn ($country) => [$country->iso2 => $country->name.' (+'.$country->phone_country_code.')'])->all();
@endphp
{{-- Side by side only when the form itself is wide (container query), stacked in the narrow sign-up card. --}}
<div class="@container"><div class="grid gap-4 @lg:grid-cols-[minmax(0,14rem)_minmax(0,1fr)]">
    <x-ui.combobox :label="$countryLabel" :name="$countryName" :id="'f-'.$countryName" :options="$options" :selected="$countryValue"
        :error="$errorBag->first($countryName) ?: null" :placeholder="__('auth.ui.phone_country_search')" autocomplete="off" />
    <x-ui.input type="tel" :label="$label" :name="$name" :id="'f-'.$name" :value="$value" :hint="$hint" :error="$errorBag->first($name) ?: null"
        maxlength="30" autocomplete="tel-national" inputmode="tel" />
</div></div>
