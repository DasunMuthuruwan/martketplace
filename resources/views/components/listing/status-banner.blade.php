@props(['listing'])
{{-- Owner's view of their own ad (components §5.2): its state and the next useful action. --}}
@use('App\Enums\ListingStatus', 'S')
@php
    $status = $listing->status;
    $expired = $status === S::Expired || ($status === S::Active && $listing->expires_at?->isPast());
    $edit = route('listings.edit', $listing);
    [$variant, $title, $text, $action, $url] = match (true) {
        $expired => ['warning', __('posting.banner.expired'), __('posting.banner.expired_text'), __('account.listings.renew'), route('account.listings', ['status' => 'expired'])],
        $status === S::Active => ['info', __('posting.banner.live'), __('posting.banner.live_text', ['when' => $listing->expires_at?->diffForHumans()]), __('account.listings.edit'), $edit],
        $status === S::PendingReview => ['warning', __('posting.banner.pending'), __('account.listings.pending_note'), __('account.listings.edit'), $edit],
        $status === S::Draft => ['info', __('posting.banner.draft'), __('posting.banner.draft_text'), __('posting.banner.finish'), $edit],
        $status === S::Rejected => ['danger', __('posting.banner.rejected'), $listing->moderation_note ? __('account.listings.reason', ['reason' => $listing->moderation_note]) : '', __('account.listings.edit_resubmit'), $edit],
        $status === S::Suspended => ['danger', __('posting.banner.suspended'), $listing->moderation_note ? __('account.listings.reason', ['reason' => $listing->moderation_note]) : '', null, null],
        $status === S::Sold => ['neutral', __('posting.banner.sold'), '', null, null],
        default => ['info', $status->value, '', null, null],
    };
@endphp
<x-ui.alert :variant="$variant === 'neutral' ? 'info' : $variant" :title="$title" {{ $attributes->class('mb-4') }}>
    {{ $text }}
    @if ($action)
        <x-slot:actions><x-ui.button size="sm" variant="secondary" :href="$url">{{ $action }}</x-ui.button></x-slot:actions>
    @endif
</x-ui.alert>
