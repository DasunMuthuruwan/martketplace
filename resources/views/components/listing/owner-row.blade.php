@props(['listing', 'present', 'lifecycle', 'public' => null]) {{-- public: known by the page (one query for all rows); else asked here --}}
{{-- A seller's own ad in My listings (layout doc §3.5): photo, title, price, status, what happens next,
     the moderation note when rejected or suspended, and the actions that are possible now. --}}
@use('App\Enums\ListingStatus', 'S')
@php
    $status = $listing->status;
    $expired = $status === S::Expired || ($status === S::Active && $listing->expires_at?->isPast());
    $badge = match (true) {
        $expired => ['warning', __('account.listings.status.expired')],
        $status === S::Active => ['success', __('account.listings.status.active')],
        $status === S::PendingReview => ['warning', __('account.listings.status.pending')],
        $status === S::Draft => ['neutral', __('account.listings.status.draft')],
        $status === S::Rejected => ['danger', __('account.listings.status.rejected')],
        $status === S::Suspended => ['danger', __('account.listings.status.suspended')],
        $status === S::Sold => ['outline', __('account.listings.status.sold')],
        default => ['neutral', $status->value],
    };
    $price = $present->priceParts($listing);
    $cover = $present->coverUrl($listing);
    $editUrl = \Illuminate\Support\Facades\Route::has('listings.edit') ? route('listings.edit', $listing) : null;
    $canRenew = $lifecycle->renewable($listing);
    $top = $listing->relationLoaded('boosts') ? $listing->boosts->first() : null;
    $hasStats = $listing->published_at !== null;
@endphp
<article {{ $attributes->class('flex gap-3 rounded-card border border-line bg-surface p-3 sm:gap-4') }}>
    <div class="aspect-[4/3] w-24 shrink-0 overflow-hidden rounded-control bg-surface-muted sm:w-32">
        @if ($cover)
            <img src="{{ $cover }}" alt="" width="128" height="96" loading="lazy" class="size-full object-cover">
        @else
            <div class="grid size-full place-items-center text-fg-subtle"><x-ui.icon name="image" size="20" /></div>
        @endif
    </div>
    <div class="flex min-w-0 flex-1 flex-col gap-1">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <h3 class="min-w-0 text-ui font-semibold text-fg">
                @if ($public ?? $listing->isPublic())
                    <a href="{{ $listing->publicUrl() }}" class="hover:underline">{{ $listing->title ?? __('account.listings.untitled') }}</a>
                @else
                    {{ $listing->title ?? __('account.listings.untitled') }}
                @endif
            </h3>
            <x-ui.badge :variant="$badge[0]">{{ $badge[1] }}</x-ui.badge>
        </div>
        @if ($price['amount'] !== '')
            <p class="tabular text-ui font-bold text-fg">{{ $price['amount'] }} @if ($price['suffix'] !== '')<span class="font-normal text-fg-subtle">{{ $price['suffix'] }}</span>@endif</p>
        @endif
        <p class="text-small text-fg-subtle">
            @if ($status === S::Active && ! $expired && $listing->expires_at)
                {{ __('account.listings.expires_in', ['when' => $listing->expires_at->diffForHumans()]) }}
            @elseif ($expired && $listing->expires_at)
                {{ __('account.listings.expired_on', ['date' => $listing->expires_at->toFormattedDateString()]) }}
            @elseif ($status === S::PendingReview)
                {{ __('account.listings.pending_note') }}
            @elseif ($status === S::Draft)
                {{ __('account.listings.draft_note', ['when' => $listing->updated_at?->diffForHumans()]) }}
            @elseif ($status === S::Sold && $listing->sold_at)
                {{ __('account.listings.sold_on', ['date' => $listing->sold_at->toFormattedDateString()]) }}
            @endif
        </p>
        @if ($top)
            <p class="flex items-center gap-1.5 text-small text-fg" data-owner-top-ad>
                <x-ui.badge variant="neutral">{{ __('boosts.top.badge') }}</x-ui.badge>
                {{ $top->starts_at->isPast() ? __('account.listings.top_until', ['date' => $top->ends_at->toFormattedDateString()]) : __('account.listings.top_from', ['date' => $top->starts_at->toFormattedDateString()]) }}
            </p>
        @endif
        @if (in_array($status, [S::Rejected, S::Suspended], true) && $listing->moderation_note)
            <p class="rounded-control bg-surface-muted px-3 py-2 text-small text-fg">{{ __('account.listings.reason', ['reason' => $listing->moderation_note]) }}</p>
        @endif

        <div class="mt-1 flex flex-wrap gap-2">
            @if ($canRenew)
                <form method="POST" action="{{ route('account.listings.renew', $listing) }}">@csrf<x-ui.button type="submit" size="sm" icon="clock">{{ __('account.listings.renew') }}</x-ui.button></form>
            @endif
            @if ($status === S::Active && ! $expired)
                <form method="POST" action="{{ route('account.listings.sold', $listing) }}">@csrf<x-ui.button type="submit" size="sm" variant="secondary" icon="circle-check">{{ __('account.listings.mark_sold') }}</x-ui.button></form>
            @endif
            @if ($editUrl && $status !== S::Sold)
                <x-ui.button size="sm" variant="secondary" icon="pencil" :href="$editUrl">{{ $status === S::Rejected ? __('account.listings.edit_resubmit') : __('account.listings.edit') }}</x-ui.button>
            @endif
            @if ($hasStats)
                <x-ui.button size="sm" variant="secondary" icon="eye" :href="route('account.listings.stats', $listing)">{{ __('account.listings.stats') }}</x-ui.button>
            @endif
            <x-ui.button size="sm" variant="ghost" icon="trash-2" data-modal-open="delete-{{ $listing->id }}" class="text-danger">{{ __('account.listings.delete') }}</x-ui.button>
        </div>
    </div>

    <x-ui.modal id="delete-{{ $listing->id }}" :title="__('account.listings.delete_title')" size="sm">
        <p class="text-ui text-fg-muted">{{ __('account.listings.delete_text', ['title' => $listing->title ?? __('account.listings.untitled')]) }}</p>
        <x-slot:footer>
            <x-ui.button variant="secondary" data-modal-close>{{ __('admin.catalogue.cancel') }}</x-ui.button>
            <form method="POST" action="{{ route('account.listings.destroy', $listing) }}">@csrf @method('DELETE')<x-ui.button type="submit" variant="danger">{{ __('account.listings.delete') }}</x-ui.button></form>
        </x-slot:footer>
    </x-ui.modal>
</article>
