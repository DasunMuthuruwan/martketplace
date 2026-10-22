@extends('layouts.account')

@section('title', __('account.stats.title'))

{{-- Statistics for one ad (B3, ADR-026): views per day (one per visitor and day), Top ad days marked,
     and the boost comparison. Bars are <progress> elements: accessible, and no inline styles (CSP). --}}
@section('account_full')
    <p class="-mt-2 mb-4 text-ui text-fg-muted">
        <a href="{{ route('account.listings') }}" class="hover:underline">{{ __('layout.my_ads') }}</a> ·
        <span class="prose-user text-fg">{{ $listing->title }}</span>
    </p>

    <div class="mb-6 grid gap-3 sm:grid-cols-3">
        <div class="rounded-card border border-line bg-surface p-4">
            <p class="text-small text-fg-muted">{{ __('account.stats.views_period', ['days' => \App\Http\Controllers\Account\ListingStatsController::DAYS]) }}</p>
            <p class="tabular text-h1 font-semibold text-fg">{{ number_format($total) }}</p>
        </div>
        <div class="rounded-card border border-line bg-surface p-4">
            <p class="text-small text-fg-muted">{{ __('account.stats.views_all') }}</p>
            <p class="tabular text-h1 font-semibold text-fg">{{ number_format($listing->view_count) }}</p>
        </div>
        @if ($comparison)
            <div class="rounded-card border border-line bg-surface p-4" data-boost-comparison>
                <p class="text-small text-fg-muted">{{ __('account.stats.top_effect') }}</p>
                <p class="tabular text-h1 font-semibold text-fg">{{ $comparison['before'] }} → {{ $comparison['during'] }}</p>
                <p class="text-small text-fg-subtle">{{ __('account.stats.top_effect_help', ['days' => $comparison['days']]) }}</p>
            </div>
        @endif
    </div>

    <section aria-labelledby="per-day" class="mb-6 rounded-card border border-line bg-surface p-4 lg:p-5">
        <h2 id="per-day" class="mb-1 text-h3 font-semibold">{{ __('account.stats.per_day') }}</h2>
        <p class="mb-3 text-small text-fg-subtle">{{ __('account.stats.per_day_help') }}</p>
        <table class="w-full text-ui">
            <caption class="sr-only">{{ __('account.stats.per_day') }}</caption>
            <thead class="sr-only">
                <tr><th scope="col">{{ __('account.stats.date') }}</th><th scope="col">{{ __('account.stats.views') }}</th><th scope="col">{{ __('account.stats.chart') }}</th></tr>
            </thead>
            <tbody>
                @foreach ($days as $day)
                    <tr @if ($day['top']) data-top-day @endif>
                        <th scope="row" class="w-28 py-0.5 pr-3 text-left text-small font-normal text-fg-muted">
                            {{ $day['date']->toFormattedDayDateString() }}
                            @if ($day['top'])<span class="sr-only"> ({{ __('boosts.top.badge') }})</span>@endif
                        </th>
                        <td class="w-12 py-0.5 pr-3 text-right tabular text-fg">{{ $day['views'] }}</td>
                        <td class="py-0.5">
                            <span class="flex items-center gap-2">
                                <progress class="stat-bar @if ($day['top']) stat-bar-top @endif" max="{{ $max }}" value="{{ $day['views'] }}" aria-hidden="true"></progress>
                                @if ($day['top'])<x-ui.badge variant="neutral" class="shrink-0" aria-hidden="true">{{ __('boosts.top.badge') }}</x-ui.badge>@endif
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <section aria-labelledby="ad-boosts" class="rounded-card border border-line bg-surface p-4 lg:p-5">
        <h2 id="ad-boosts" class="mb-3 text-h3 font-semibold">{{ __('account.stats.boosts') }}</h2>
        @if ($boosts->isEmpty())
            <p class="text-ui text-fg-muted">{{ __('account.stats.no_boosts') }}</p>
        @else
            <ul class="space-y-2">
                @foreach ($boosts as $boost)
                    <li class="flex flex-wrap items-baseline gap-x-2 text-ui">
                        <x-ui.badge variant="neutral">{{ __('admin.boosts.types.'.$boost->type->value) }}</x-ui.badge>
                        <span class="tabular text-fg">{{ $boost->starts_at->toFormattedDateString() }}@if ($boost->ends_at) – {{ $boost->ends_at->toFormattedDateString() }}@endif</span>
                        @if ($boost->ended_at)<span class="text-small text-fg-subtle">{{ __('admin.boosts.ended_on', ['date' => $boost->ended_at->toFormattedDateString()]) }}</span>@endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
