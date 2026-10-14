@extends('layouts.auth')

@section('title', __('auth.ui.register_title'))

@section('content')
    @if ($countries->isEmpty())
        <x-ui.alert>{{ __('auth.ui.no_countries') }}</x-ui.alert>
    @else
        <x-ui.form-errors class="mb-4" />
        <form method="POST" action="{{ route('register.store') }}" novalidate class="space-y-4">
            @csrf
            <x-ui.input :label="__('auth.ui.name')" name="name" :value="old('name')" required maxlength="100" autocomplete="name" />
            <x-ui.input type="email" :label="__('auth.ui.email')" name="email" :value="old('email')" required maxlength="191" autocomplete="email" />
            <x-ui.input type="password" :label="__('auth.ui.password')" name="password" :hint="__('auth.ui.password_hint')" required autocomplete="new-password" />
            <x-ui.input type="password" :label="__('auth.ui.password_confirmation')" name="password_confirmation" required autocomplete="new-password" />

            <x-ui.select :label="__('auth.ui.country')" name="country_id" required :placeholder="__('auth.ui.choose_country')"
                :options="$countries->pluck('name', 'id')->all()" :selected="old('country_id')" :hint="__('auth.ui.country_hint')" />

            @include('partials.phone-field', [
                'name' => 'phone',
                'countryName' => 'phone_country',
                'label' => __('auth.ui.phone'),
                'countryLabel' => __('auth.ui.phone_country'),
                'hint' => __('auth.ui.phone_hint'),
                'value' => old('phone'),
                'countryValue' => old('phone_country'),
            ])

            @php($pages = \App\Models\Page::footerLinks())
            <x-ui.checkbox name="terms" :checked="(bool) old('terms')" :label="__('auth.ui.terms')" />
            @if ($pages->has('terms') || $pages->has('privacy'))
                <p class="-mt-2 pl-6 text-small text-fg-subtle">
                    @if ($pages->has('terms'))<a href="{{ route('page.show', 'terms') }}" target="_blank" rel="noopener" class="underline underline-offset-2">{{ $pages['terms'] }}</a>@endif
                    @if ($pages->has('terms') && $pages->has('privacy')) · @endif
                    @if ($pages->has('privacy'))<a href="{{ route('page.show', 'privacy') }}" target="_blank" rel="noopener" class="underline underline-offset-2">{{ $pages['privacy'] }}</a>@endif
                </p>
            @endif

            @include('partials.bot-protection')

            <x-ui.button type="submit" size="lg" block>{{ __('auth.ui.register') }}</x-ui.button>
        </form>
    @endif

    <p class="mt-6 border-t border-line pt-4 text-center text-ui text-fg-muted">
        {{ __('auth.ui.have_account') }} <a href="{{ route('login') }}" class="font-medium text-fg underline underline-offset-2">{{ __('auth.ui.login') }}</a>
    </p>
@endsection
