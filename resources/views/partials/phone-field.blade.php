{{--
    Phone number with a country picker (GLB-17). Stored as E.164; validated per country on the server.
    Params: name, countryName, label, countryLabel, hint, value, countryValue, phoneCountries, bag (error bag, default "default").
--}}
@php($bag = $bag ?? 'default')
@php($errorBag = $errors->getBag($bag))

<label for="{{ $countryName }}">{{ $countryLabel }}</label>
<select id="{{ $countryName }}" name="{{ $countryName }}" autocomplete="tel-country-code" @if ($errorBag->has($countryName)) aria-invalid="true" aria-describedby="{{ $countryName }}-error" @endif>
    <option value=""></option>
    @foreach ($phoneCountries as $country)
        <option value="{{ $country->iso2 }}" @selected($countryValue === $country->iso2)>{{ $country->name }} (+{{ $country->phone_country_code }})</option>
    @endforeach
</select>
@if ($errorBag->has($countryName))<p class="error" id="{{ $countryName }}-error">{{ $errorBag->first($countryName) }}</p>@endif

<label for="{{ $name }}">{{ $label }}</label>
<input type="tel" id="{{ $name }}" name="{{ $name }}" value="{{ $value }}" maxlength="30" autocomplete="tel-national" inputmode="tel" aria-describedby="{{ $name }}-hint @if ($errorBag->has($name)) {{ $name }}-error @endif" @if ($errorBag->has($name)) aria-invalid="true" @endif>
<p class="hint" id="{{ $name }}-hint">{{ $hint }}</p>
@if ($errorBag->has($name))<p class="error" id="{{ $name }}-error">{{ $errorBag->first($name) }}</p>@endif
