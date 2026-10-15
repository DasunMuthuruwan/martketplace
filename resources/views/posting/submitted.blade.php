@extends('layouts.public')

@section('title', $live ? __('posting.submitted.live_title') : __('posting.submitted.review_title'))

@section('content')
    {{-- After submit (layout doc §3.4): sent for review, or live straight away (moderation mode). --}}
    <div class="mx-auto max-w-lg py-8 text-center">
        <span class="mx-auto mb-4 grid size-14 place-items-center rounded-full bg-surface-muted text-success"><x-ui.icon name="circle-check" size="24" /></span>
        <h1 class="text-h1 font-[650]">{{ $live ? __('posting.submitted.live_title') : __('posting.submitted.review_title') }}</h1>
        <p class="mt-2 text-body text-fg-muted">{{ $live ? __('posting.submitted.live_text') : __('posting.submitted.review_text') }}</p>
        <div class="mt-6 flex flex-wrap justify-center gap-2">
            @if ($live)<x-ui.button :href="$listing->publicUrl()" icon="eye">{{ __('posting.review.view_ad') }}</x-ui.button>@endif
            <x-ui.button variant="secondary" :href="route('account.listings', ['status' => $live ? 'active' : 'pending'])">{{ __('layout.my_ads') }}</x-ui.button>
            <x-ui.button variant="secondary" :href="route('listings.create')" icon="plus">{{ __('posting.submitted.another') }}</x-ui.button>
        </div>
    </div>
@endsection
