@extends('layouts.wizard')

@section('step_title', __('posting.price.title'))
@section('step_intro', __('posting.price.intro'))

@section('step')
    {{-- Price (global UX §3): the currency is the country's (shown as its code, never chosen here);
         decimals up to the currency's minor units; nothing is converted. --}}
    @php
        $type = old('price_type', $listing->price_type?->value ?? ($priceTypes[0]->value ?? null));
        $amountTypes = ['fixed', 'negotiable'];
        // Shown grouped in the country's format ("1,250,000"); the server accepts it back as typed.
        $formatter = new \NumberFormatter($listing->country->number_locale, \NumberFormatter::DECIMAL);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $currency->minor_units);
        $amount = old('price', $listing->price === null ? '' : $formatter->format((float) $listing->price));
    @endphp

    <x-ui.form-errors class="mb-4" />

    <form method="POST" action="{{ route('listings.update', [$listing, 'price']) }}" novalidate class="space-y-5" data-price-form>
        @csrf

        @if ($priceTypes === [])
            <x-ui.alert>{{ __('posting.price.none') }}</x-ui.alert>
        @else
            <fieldset>
                <legend class="mb-2 text-ui font-medium text-fg">{{ __('posting.price.type') }}<span aria-hidden="true" class="text-danger"> *</span></legend>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach ($priceTypes as $t)
                        <label class="flex cursor-pointer items-center justify-center rounded-card border border-line p-3 text-center text-ui text-fg has-[:checked]:border-primary has-[:checked]:bg-surface-muted has-[:checked]:font-semibold">
                            <input type="radio" name="price_type" value="{{ $t->value }}" @checked($type === $t->value) class="sr-only" data-price-type>
                            {{ __('posting.price.types.'.$t->value) }}
                        </label>
                    @endforeach
                </div>
                @error('price_type')<p class="mt-1 text-small text-danger">{{ $message }}</p>@enderror
            </fieldset>

            <div data-price-amount @if (! in_array($type, $amountTypes, true)) data-amount-hidden @endif>
                <x-ui.input :label="__('posting.price.amount')" name="price" :value="$amount" inputmode="decimal" autocomplete="off"
                    :prefix="$currency->code" class="tabular"
                    :hint="__('posting.price.currency_hint', ['name' => $currency->name, 'decimals' => $currency->minor_units === 0 ? __('posting.price.no_decimals') : trans_choice('posting.price.decimals', $currency->minor_units, ['n' => $currency->minor_units])])" />
            </div>

            @if ($rent)
                <x-ui.select :label="__('posting.price.rent_period')" name="rent_period" required :placeholder="__('posting.location.choose')"
                    :options="collect($rentPeriods)->mapWithKeys(fn ($p) => [$p->value => __('posting.price.periods.'.$p->value)])->all()"
                    :selected="old('rent_period', $listing->rent_period?->value)" />
            @endif

            @if ($unitPrice)
                <p class="flex items-center gap-2 rounded-control bg-surface-muted px-3 py-2 text-ui text-fg">
                    <x-ui.icon name="info" size="16" class="text-info" />{{ __('posting.price.unit_price', ['price' => $unitPrice]) }}
                </p>
            @endif
        @endif

        @include('posting.partials.actions', ['back' => route('listings.edit', [$listing, 'details'])])
    </form>
@endsection
