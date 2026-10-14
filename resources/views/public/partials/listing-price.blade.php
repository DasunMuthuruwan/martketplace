{{-- Price block (components §5.2): full price in the listing's currency (Q-UX-4), suffix, land unit
     price, title, place · posted · ID. --}}
<div class="space-y-1">
    <p class="flex flex-wrap items-baseline gap-x-2">
        <span class="tabular text-price-xl font-bold text-fg">{{ $price['amount'] }}</span>
        @if ($price['suffix'] !== '')<span class="text-ui text-fg-muted">{{ $price['suffix'] }}</span>@endif
    </p>
    @if ($unit)<p class="tabular text-ui text-fg-muted">{{ $unit }}</p>@endif
    <h1 class="pt-1 text-h1 font-[650] prose-user">{{ $listing->title }}</h1>
    <p class="text-small text-fg-subtle">
        @if ($listing->published_at)<time datetime="{{ $listing->published_at->toIso8601String() }}">{{ __('listings.detail.posted_ago', ['ago' => $listing->published_at->diffForHumans()]) }}</time> · @endif
        {{ __('listings.detail.id', ['id' => $listing->id]) }}
    </p>
</div>
