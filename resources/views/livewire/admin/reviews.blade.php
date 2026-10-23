{{-- Review moderation (G5b): pending comments, reported reviews, removed reviews. --}}
<div>
    <x-admin.page-header :title="__('admin.nav.reviews')" :count="$reviews->total()" :description="__('admin.reviews.help')" />

    @if (session('status'))<x-ui.alert variant="success" role="status" class="mb-4">{{ session('status') }}</x-ui.alert>@endif
    @if (session('error'))<x-ui.alert variant="danger" class="mb-4">{{ session('error') }}</x-ui.alert>@endif

    <x-ui.tabs :label="__('admin.reviews.tabs_label')" :active="$tab" wire-model="tab" class="mb-4"
        :items="collect(\App\Livewire\Admin\ReviewsAdmin::TABS)->mapWithKeys(fn ($t) => [$t => __('admin.reviews.tabs.'.$t)])->all()" />

    @if ($reviews->isEmpty())
        <x-ui.empty-state icon="badge-check" :title="__('admin.reviews.empty.'.$tab)" />
    @else
        <ul class="space-y-3">
            @foreach ($reviews as $review)
                <li wire:key="review-{{ $review->id }}" class="rounded-card border border-line bg-surface p-4">
                    <p class="flex flex-wrap items-baseline gap-x-2 text-ui">
                        <span class="font-semibold text-fg" aria-label="{{ trans_choice('reviews.stars', $review->rating, ['count' => $review->rating]) }}"><span aria-hidden="true">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span></span>
                        <span class="text-fg">{{ __('admin.reviews.by', ['reviewer' => $review->reviewer?->name ?? __('account.deleted_user'), 'seller' => $review->seller?->name]) }}</span>
                        <span class="text-small text-fg-subtle">{{ $review->updated_at->toFormattedDateString() }}</span>
                    </p>
                    @if ($review->comment)
                        <p class="prose-user mt-2 whitespace-pre-line rounded-control bg-surface-muted p-2 text-ui text-fg">{{ $review->comment }}</p>
                    @endif
                    @if ($review->reply)
                        <p class="prose-user mt-2 text-small text-fg-muted">{{ __('reviews.seller_reply') }}: {{ $review->reply }}</p>
                    @endif
                    @if ($review->reports_count > 0)
                        <ul class="mt-2 space-y-1 text-small text-fg-muted">
                            @foreach ($review->reports as $report)
                                <li>{{ __('reviews.report_reasons.'.$report->reason) }}@if ($report->details): <span class="prose-user">{{ $report->details }}</span>@endif</li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($review->status === \App\Models\SellerReview::REMOVED)
                        <p class="mt-2 text-small text-fg-muted">{{ __('admin.reviews.removed_because', ['reason' => $review->removed_reason]) }}</p>
                    @else
                        <div class="mt-3 flex flex-wrap gap-2">
                            @if ($tab === 'comments')
                                <x-ui.button size="sm" icon="check" wire:click="approve({{ $review->id }})">{{ __('admin.reviews.approve') }}</x-ui.button>
                                <x-ui.button size="sm" variant="secondary" wire:click="reject({{ $review->id }})">{{ __('admin.reviews.reject') }}</x-ui.button>
                            @elseif ($tab === 'reported')
                                <x-ui.button size="sm" variant="secondary" wire:click="keep({{ $review->id }})">{{ __('admin.reviews.keep') }}</x-ui.button>
                            @endif
                            <x-ui.button size="sm" variant="ghost" icon="trash-2" class="text-danger" wire:click="startRemove({{ $review->id }})">{{ __('admin.reviews.remove') }}</x-ui.button>
                        </div>
                        @if ($removingId === $review->id)
                            <form wire:submit="remove" class="mt-3 space-y-2 rounded-card bg-surface-muted p-3">
                                <x-ui.textarea :label="__('admin.reviews.reason')" id="remove-reason" wire:model="reason" rows="2" maxlength="500" required :error="$errors->first('reason')" />
                                <div class="flex gap-2">
                                    <x-ui.button type="submit" variant="danger">{{ __('admin.reviews.remove_confirm') }}</x-ui.button>
                                    <x-ui.button type="button" variant="secondary" wire:click="cancelRemove">{{ __('admin.catalogue.cancel') }}</x-ui.button>
                                </div>
                            </form>
                        @endif
                    @endif
                </li>
            @endforeach
        </ul>
        {{ $reviews->links('livewire.admin.pagination') }}
    @endif
</div>
