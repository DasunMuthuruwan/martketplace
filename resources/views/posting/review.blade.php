@extends('layouts.wizard')

@section('step_title', __('posting.review.title'))
@section('step_intro', $canSubmit ? __('posting.review.intro') : __('posting.review.intro_live'))

@section('step')
    {{-- Contact and review (layout doc §3.4): the buttons buyers see, a preview card, a summary of every
         step with links back, then submit (drafts and rejected ads) with Turnstile when configured. --}}
    <x-ui.form-errors class="mb-4" :title="__('posting.review.not_ready')" />

    <section aria-labelledby="contact-title" class="mb-6 space-y-3">
        <h2 id="contact-title" class="text-h3 font-semibold">{{ __('posting.review.contact') }}</h2>
        <p class="text-ui text-fg-muted">
            {{ __('posting.review.visibility', ['who' => __('account.contact.visibility_options.'.$user->contact_visibility->value)]) }}
            <a href="{{ route('account.settings.contact') }}" class="font-medium text-fg underline underline-offset-2">{{ __('posting.change') }}</a>
        </p>
        @unless ($hasPhone)
            <x-ui.alert variant="warning">{{ __('posting.review.no_phone') }}
                <x-slot:actions><x-ui.button size="sm" variant="secondary" :href="route('account.settings.profile')">{{ __('account.nav.profile') }}</x-ui.button></x-slot:actions>
            </x-ui.alert>
        @endunless
        <form method="POST" action="{{ route('listings.contact-options', $listing) }}" class="space-y-2">
            @csrf
            <x-ui.switch :label="__('posting.review.show_call')" name="show_call" id="f-show-call" value="1" :checked="(bool) $listing->show_call" />
            <x-ui.switch :label="__('posting.review.show_whatsapp')" name="show_whatsapp" id="f-show-whatsapp" value="1" :checked="(bool) $listing->show_whatsapp" />
            @error('show_call')<p class="text-small text-danger">{{ $message }}</p>@enderror
            <x-ui.button type="submit" size="sm" variant="secondary">{{ __('posting.review.save_contact') }}</x-ui.button>
        </form>
    </section>

    <section aria-labelledby="preview-title" class="mb-6">
        <h2 id="preview-title" class="mb-3 text-h3 font-semibold">{{ __('posting.review.preview') }}</h2>
        <div class="max-w-xs"><x-listing.card :listing="$listing" :present="$present" /></div>
    </section>

    <section aria-labelledby="summary-title" class="mb-6">
        <h2 id="summary-title" class="mb-2 text-h3 font-semibold">{{ __('posting.review.summary') }}</h2>
        <dl class="divide-y divide-line rounded-card border border-line text-ui">
            @foreach ([
                'category' => $listing->category->parent?->name.' › '.$listing->category->name,
                'location' => $present->location($listing, true),
                'details' => $listing->title,
                'price' => $present->price($listing),
                'photos' => trans_choice('posting.review.photos', $listing->images_count, ['count' => $listing->images_count]),
            ] as $key => $value)
                <div class="flex items-start justify-between gap-3 px-4 py-3">
                    <div class="min-w-0"><dt class="text-small text-fg-subtle">{{ __('posting.steps.'.$key) }}</dt><dd class="truncate text-fg">{{ $value }}</dd></div>
                    <a href="{{ route('listings.edit', [$listing, $key]) }}" class="shrink-0 text-small font-medium text-fg underline underline-offset-2">{{ __('posting.change') }}</a>
                </div>
            @endforeach
        </dl>
    </section>

    @if ($canSubmit)
        <form method="POST" action="{{ route('listings.submit', $listing) }}" class="space-y-4">
            @csrf
            <p class="text-small text-fg-muted">{{ __('posting.review.rules') }}
                @if (\App\Models\Page::footerLinks()->has('prohibited-items'))<a href="{{ route('page.show', 'prohibited-items') }}" target="_blank" rel="noopener" class="underline underline-offset-2">{{ __('posting.review.rules_link') }}</a>@endif
            </p>
            @if ($turnstileSiteKey)
                <div class="cf-turnstile" data-sitekey="{{ $turnstileSiteKey }}"></div>
                @push('head')<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer nonce="{{ Vite::cspNonce() }}"></script>@endpush
            @endif
            <div class="flex justify-between gap-2 border-t border-line pt-4 max-lg:fixed max-lg:inset-x-0 max-lg:bottom-0 max-lg:z-30 max-lg:bg-surface max-lg:px-4 max-lg:pb-[calc(12px+env(safe-area-inset-bottom))] max-lg:pt-3">
                <x-ui.button variant="secondary" :href="route('listings.edit', [$listing, 'photos'])" icon="chevron-left">{{ __('posting.back') }}</x-ui.button>
                <x-ui.button type="submit" icon="check">{{ $listing->status === \App\Enums\ListingStatus::Rejected ? __('posting.review.resubmit') : __('posting.review.submit') }}</x-ui.button>
            </div>
        </form>
    @else
        <div class="flex justify-between gap-2 border-t border-line pt-4">
            <x-ui.button variant="secondary" :href="route('account.listings')" icon="chevron-left">{{ __('layout.my_ads') }}</x-ui.button>
            @if ($listing->isPublic())<x-ui.button :href="$listing->publicUrl()" icon="eye">{{ __('posting.review.view_ad') }}</x-ui.button>@endif
        </div>
    @endif
@endsection
