{{-- Rating summary (G5): "4.6 ★ · 12 reviews"; the average only from enough reviews. Needs $summary. --}}
@if ($summary['count'] > 0)
    <span class="inline-flex items-center gap-1 text-fg-muted" data-rating-summary>
        @if ($summary['average'] !== null)
            <span class="font-semibold text-fg">{{ number_format($summary['average'], 1) }}</span>
            <span aria-hidden="true">★</span>
            <span class="sr-only">{{ __('reviews.out_of_five') }}</span>
            <span aria-hidden="true">·</span>
        @endif
        <span>{{ trans_choice('reviews.count', $summary['count'], ['count' => number_format($summary['count'])]) }}</span>
    </span>
@endif
