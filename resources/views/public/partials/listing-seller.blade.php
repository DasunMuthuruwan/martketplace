{{-- Seller card (components §5.2): no contact details here; those stay behind the contact buttons. --}}
<section aria-label="{{ __('public.seller') }}" class="rounded-card border border-line bg-surface p-5">
    <a href="{{ $seller->sellerUrl() }}" class="flex items-center gap-3 rounded-control">
        <x-ui.avatar :name="$seller->name" size="40" />
        <span class="min-w-0">
            <span class="block truncate text-ui font-semibold text-fg">{{ $seller->name }}</span>
            <span class="block text-small text-fg-subtle">{{ __('public.member_since', ['date' => $seller->created_at?->translatedFormat('F Y')]) }}</span>
        </span>
    </a>
    <ul class="mt-3 space-y-1 text-small text-fg-muted">
        @if (($reviewSummary['count'] ?? 0) > 0)
            <li>@include('public.partials.rating-summary', ['summary' => $reviewSummary])</li>
        @endif
        @if ($seller->hasVerifiedEmail())
            <li class="flex items-center gap-1.5"><x-ui.icon name="badge-check" size="16" class="text-success" />{{ __('public.email_verified') }}</li>
        @endif
        <li><a href="{{ $seller->sellerUrl() }}" class="inline-flex items-center gap-1 hover:text-fg">{{ trans_choice('listings.detail.seller_ads', $sellerAds, ['count' => number_format($sellerAds)]) }}<x-ui.icon name="chevron-right" size="16" /></a></li>
    </ul>
</section>
