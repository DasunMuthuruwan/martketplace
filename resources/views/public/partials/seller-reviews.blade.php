{{-- Reviews of a seller (G5, ADR-027): published ratings, approved comments, the seller's reply, and
     the rating form for buyers who contacted this seller. Text only: every value is escaped. --}}
<section aria-labelledby="reviews-title" id="reviews" class="mt-10" data-seller-reviews>
    <div class="mb-3 flex flex-wrap items-baseline justify-between gap-x-4">
        <h2 id="reviews-title" class="text-h2 font-semibold">{{ __('reviews.title') }}</h2>
        @include('public.partials.rating-summary', ['summary' => $reviewSummary])
    </div>

    @if (session('status'))
        <x-ui.alert variant="success" role="status" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    @if ($canReview)
        <form method="POST" action="{{ route('seller.reviews.store', $seller->id) }}" id="rate" class="mb-6 space-y-3 rounded-card border border-line bg-surface p-4" data-review-form>
            @csrf
            <h3 class="text-h3 font-semibold">{{ $myReview ? __('reviews.update_title') : __('reviews.form_title', ['name' => $seller->name]) }}</h3>
            @if ($errors->review->any())
                <x-ui.alert variant="danger" :title="__('reviews.not_saved')">
                    <ul class="list-disc pl-4">@foreach ($errors->review->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
                </x-ui.alert>
            @endif
            <fieldset>
                <legend class="mb-1 text-ui font-medium text-fg">{{ __('reviews.rating') }}</legend>
                <div class="flex flex-wrap gap-2">
                    @foreach (range(5, 1) as $stars)
                        <label class="inline-flex h-10 cursor-pointer items-center gap-1 rounded-control border border-control px-3 text-ui has-[:checked]:border-primary has-[:checked]:bg-surface-muted">
                            <input type="radio" name="rating" value="{{ $stars }}" required @checked((int) old('rating', $myReview?->rating) === $stars)>
                            {{ trans_choice('reviews.stars', $stars, ['count' => $stars]) }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <fieldset>
                <legend class="mb-1 text-ui font-medium text-fg">{{ __('reviews.deal') }}</legend>
                <div class="flex flex-wrap gap-3 text-ui">
                    @foreach (['yes', 'no'] as $deal)
                        <label class="inline-flex items-center gap-1.5"><input type="radio" name="deal" value="{{ $deal }}" @checked(old('deal', $myReview?->deal) === $deal)>{{ __('reviews.deal_'.$deal) }}</label>
                    @endforeach
                </div>
            </fieldset>
            <x-ui.textarea :label="__('reviews.comment')" id="review-comment" name="comment" rows="3" :maxlength="\App\Services\Reviews\SellerReviews::COMMENT_MAX"
                :hint="__('reviews.comment_hint')" :error="$errors->review->first('comment')">{{ old('comment', $myReview?->comment) }}</x-ui.textarea>
            <x-ui.button type="submit" icon="check">{{ $myReview ? __('reviews.update') : __('reviews.submit') }}</x-ui.button>
        </form>
    @endif

    @if ($reviews->isEmpty())
        <p class="text-ui text-fg-muted">{{ __('reviews.none') }}</p>
    @else
        <ul class="space-y-3">
            @foreach ($reviews as $review)
                <li class="rounded-card border border-line bg-surface p-4" data-review>
                    <p class="flex flex-wrap items-baseline gap-x-2 text-ui">
                        <span class="font-semibold text-fg" aria-label="{{ trans_choice('reviews.stars', $review->rating, ['count' => $review->rating]) }}"><span aria-hidden="true">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span></span>
                        <span class="text-fg">{{ $review->reviewerName() }}</span>
                        <span class="text-small text-fg-subtle">{{ $review->created_at->toFormattedDateString() }}</span>
                        @if ($review->deal === 'yes')<x-ui.badge variant="neutral">{{ __('reviews.dealt') }}</x-ui.badge>@endif
                    </p>
                    @if ($review->commentShown())
                        <p class="prose-user mt-1 whitespace-pre-line text-ui text-fg">{{ $review->comment }}</p>
                    @endif
                    @if ($review->reply)
                        <div class="mt-2 border-l-2 border-line pl-3">
                            <p class="text-small font-medium text-fg-muted">{{ __('reviews.seller_reply') }}</p>
                            <p class="prose-user whitespace-pre-line text-ui text-fg">{{ $review->reply }}</p>
                        </div>
                    @endif
                    {{-- Report a review (G5b): any signed-in user except its author; staff decide. --}}
                    @auth
                        @if (auth()->id() !== $review->reviewer_id)
                            <details class="mt-2 text-small" @if ($errors->{'report-'.$review->id}->any()) open @endif>
                                <summary class="cursor-pointer text-fg-subtle hover:text-fg">{{ __('reviews.report') }}</summary>
                                <form method="POST" action="{{ route('reviews.report', $review) }}" class="mt-2 space-y-2">
                                    @csrf
                                    <x-ui.select :label="__('reviews.report_reason')" id="report-reason-{{ $review->id }}" name="reason" required :error="$errors->{'report-'.$review->id}->first('reason')"
                                        :options="__('reviews.report_reasons')" :placeholder="__('reviews.report_choose')" />
                                    <x-ui.textarea :label="__('reviews.report_details')" id="report-details-{{ $review->id }}" name="details" rows="2" maxlength="500" />
                                    <x-ui.button type="submit" size="sm" variant="secondary">{{ __('reviews.report_send') }}</x-ui.button>
                                </form>
                            </details>
                        @endif
                    @endauth
                </li>
            @endforeach
        </ul>
        <x-ui.pagination :paginator="$reviews" class="mt-4" />
    @endif
</section>
