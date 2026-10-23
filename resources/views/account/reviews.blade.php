@extends('layouts.account')

@section('title', __('reviews.mine_title'))

{{-- Reviews the seller received (G5): comments once approved, and one reply each. --}}
@section('account_full')
    @if (session('status'))<x-ui.alert variant="success" role="status" class="mb-4">{{ session('status') }}</x-ui.alert>@endif
    <p class="mb-4 text-ui text-fg-muted">{{ __('reviews.mine_intro') }} @include('public.partials.rating-summary', ['summary' => $summary])</p>

    @if ($reviews->isEmpty())
        <x-ui.empty-state icon="badge-check" :title="__('reviews.mine_none')" />
    @else
        <ul class="space-y-3">
            @foreach ($reviews as $review)
                <li class="rounded-card border border-line bg-surface p-4">
                    <p class="flex flex-wrap items-baseline gap-x-2 text-ui">
                        <span class="font-semibold text-fg" aria-label="{{ trans_choice('reviews.stars', $review->rating, ['count' => $review->rating]) }}"><span aria-hidden="true">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span></span>
                        <span class="text-fg">{{ $review->reviewerName() }}</span>
                        <span class="text-small text-fg-subtle">{{ $review->created_at->toFormattedDateString() }}</span>
                    </p>
                    @if ($review->commentShown())
                        <p class="prose-user mt-1 whitespace-pre-line text-ui text-fg">{{ $review->comment }}</p>
                    @elseif ($review->comment_status === \App\Models\SellerReview::COMMENT_PENDING)
                        <p class="mt-1 text-small text-fg-subtle">{{ __('reviews.comment_pending') }}</p>
                    @endif

                    @if ($review->reply)
                        <div class="mt-2 border-l-2 border-line pl-3">
                            <p class="text-small font-medium text-fg-muted">{{ __('reviews.your_reply') }}</p>
                            <p class="prose-user whitespace-pre-line text-ui text-fg">{{ $review->reply }}</p>
                        </div>
                    @else
                        <form method="POST" action="{{ route('account.reviews.reply', $review) }}" class="mt-3 space-y-2">
                            @csrf
                            <x-ui.textarea :label="__('reviews.reply')" id="reply-{{ $review->id }}" name="reply" rows="2" :maxlength="\App\Services\Reviews\SellerReviews::REPLY_MAX"
                                :hint="__('reviews.reply_hint')" :error="$errors->{'reply-'.$review->id}->first('reply')">{{ old('reply') }}</x-ui.textarea>
                            <x-ui.button type="submit" size="sm" variant="secondary">{{ __('reviews.reply_submit') }}</x-ui.button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
        <x-ui.pagination :paginator="$reviews" class="mt-6" />
    @endif
@endsection
