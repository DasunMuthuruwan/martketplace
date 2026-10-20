@extends('layouts.account')

@section('title', __('account.listings.title'))

@section('account_full')
    @if (session('error'))<x-ui.alert variant="danger" class="mb-4">{{ session('error') }}</x-ui.alert>@endif

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <x-ui.tabs :label="__('account.listings.tabs')" :active="$tab" class="flex-1"
            :items="collect(\App\Http\Controllers\Account\ListingsController::TABS)->mapWithKeys(fn ($t) => [$t => ['label' => __('account.listings.tab.'.$t), 'href' => route('account.listings', ['status' => $t]), 'count' => $counts[$t]]])->all()" />
        @if ($postUrl = \App\View\Navigation::url('post'))
            <x-ui.button :href="$postUrl" icon="plus">{{ __('layout.post_ad') }}</x-ui.button>
        @endif
    </div>

    @if ($listings->isEmpty())
        <x-ui.empty-state icon="list" :title="__('account.listings.empty.'.$tab)" />
    @else
        <ul class="space-y-3">
            @foreach ($listings as $listing)
                <li><x-listing.owner-row :listing="$listing" :present="$present" :lifecycle="$lifecycle" :public="in_array($listing->id, $publicIds, true)" /></li>
            @endforeach
        </ul>
        <x-ui.pagination :paginator="$listings" class="mt-6" />
    @endif
@endsection
