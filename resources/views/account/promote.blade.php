@extends('layouts.account')

@section('title', __('boosts.promote.title'))

{{-- Promote an ad (B5, ADR-026): choose a promotion, order it, then pay by bank transfer. --}}
@section('account')
    <p class="mb-4 text-ui text-fg-muted">{{ __('boosts.promote.intro', ['title' => $listing->title]) }}</p>

    @if (! $open)
        <x-ui.alert>{{ __('boosts.promote.not_open') }}</x-ui.alert>
    @else
        <x-ui.form-errors class="mb-4" />
        <form method="POST" action="{{ route('account.listings.promote.store', $listing) }}" class="space-y-4">
            @csrf
            <fieldset class="space-y-2">
                <legend class="mb-2 text-ui font-semibold text-fg">{{ __('boosts.promote.choose') }}</legend>
                @foreach ($offers as $offer)
                    <label class="flex cursor-pointer items-start gap-3 rounded-card border border-line bg-surface p-3 hover:bg-surface-hover has-[:checked]:border-primary">
                        <input type="radio" name="price" value="{{ $offer->id }}" class="mt-1" @checked((string) old('price', $loop->first ? $offer->id : '') === (string) $offer->id) required>
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-baseline justify-between gap-2">
                                <span class="text-ui font-medium text-fg">{{ $offer->product->name() }}</span>
                                <x-ui.money :amount="$offer->amount" :currency="$offer->currencyModel" :country="$listing->country" size="inherit" class="text-ui font-semibold" />
                            </span>
                            <span class="block text-small text-fg-subtle">{{ __('boosts.product_help.'.$offer->product->type->value) }}</span>
                        </span>
                    </label>
                @endforeach
            </fieldset>

            <section aria-labelledby="promote-rules" class="rounded-card bg-surface-muted p-3">
                <h2 id="promote-rules" class="mb-1 text-small font-semibold text-fg">{{ __('boosts.promote.rules_title') }}</h2>
                <ul class="list-disc space-y-1 pl-4 text-small text-fg-muted">
                    @foreach (__('boosts.promote.rules') as $rule)<li>{{ $rule }}</li>@endforeach
                </ul>
                @if (\App\Models\Page::footerLinks()->has('promotions'))
                    <p class="mt-2 text-small"><a href="{{ route('page.show', 'promotions') }}" class="text-fg underline underline-offset-2">{{ __('boosts.promote.full_rules') }}</a></p>
                @endif
            </section>

            <x-ui.button type="submit" icon="chevron-right">{{ __('boosts.promote.order') }}</x-ui.button>
        </form>
    @endif
@endsection
