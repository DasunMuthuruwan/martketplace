@extends('layouts.account')

@section('title', __('account.favourites.title'))

@section('account_full')
    @if ($favourites->isEmpty())
        <x-ui.empty-state icon="heart" :title="__('account.favourites.empty_title')">
            {{ __('account.favourites.empty_text') }}
            <x-slot:action><x-ui.button variant="secondary" :href="route('home')">{{ __('account.favourites.browse') }}</x-ui.button></x-slot:action>
        </x-ui.empty-state>
    @else
        <div class="listing-grid-area">
            <ul class="listing-grid">
                @foreach ($favourites as $listing)
                    <li class="flex flex-col gap-2">
                        @if ($publicIds->has($listing->id))
                            <x-listing.card :listing="$listing" :present="$present" :with-code="true" class="flex-1" />
                        @else
                            <div class="flex flex-1 flex-col justify-center rounded-card border border-dashed border-line p-4 text-center">
                                <p class="text-ui font-medium text-fg">{{ $listing->title }}</p>
                                <p class="mt-1 text-small text-fg-subtle">{{ __('account.favourites.unavailable') }}</p>
                            </div>
                        @endif
                        <x-ui.button size="sm" variant="ghost" icon="heart" data-favourite-url="{{ route('listings.favourite', $listing) }}" aria-pressed="true"
                            data-label-on="{{ __('listings.detail.saved') }}" data-label-off="{{ __('public.save') }}">
                            <span data-favourite-label>{{ __('listings.detail.saved') }}</span>
                        </x-ui.button>
                    </li>
                @endforeach
            </ul>
        </div>
        <x-ui.pagination :paginator="$favourites" class="mt-6" />
    @endif
@endsection
