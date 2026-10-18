@props([
    'listing',
    'present',
    'reasons' => [],     // screener flags
    'seller' => [],      // summary from ModerationQueue::sellerSummary()
    'fields' => [],      // label => value
    'templates' => [],
    'decision' => null,  // reject | changes | null
    'own' => false,      // the moderator's own ad: no decision (CD-06)
    'messages',          // the component's error bag
])
{{-- Moderation review (components §5.4 admin.review-pane): photos, fields, flags, seller summary and
     the decision. Reject / Ask for changes open a reason step first; the reason is shown to the seller.
     data-shortcut keys are read by resources/js/ui/admin.js. --}}
<article aria-labelledby="review-title" {{ $attributes->class('space-y-5 rounded-card border border-line bg-surface p-4 lg:p-5') }}>
    <header class="space-y-1">
        <div class="flex flex-wrap items-center gap-2">
            @if ($listing->flagged)<x-ui.badge variant="warning" icon="triangle-alert">{{ __('admin.moderation.flagged') }}</x-ui.badge>@endif
            <x-ui.badge variant="outline">{{ __('public.types.'.$listing->type->value) }}</x-ui.badge>
            <span class="text-small text-fg-subtle">{{ __('admin.moderation.sent', ['when' => $listing->submitted_at?->diffForHumans() ?? '–']) }} · ID {{ $listing->id }}</span>
        </div>
        <h2 id="review-title" class="prose-user text-h2 font-semibold">{{ $listing->title }}</h2>
        <p class="text-ui text-fg-muted">
            {{ $listing->category->parent ? $listing->category->parent->name.' › ' : '' }}{{ $listing->category->name }}
            · {{ $present->location($listing, true) }}
        </p>
        <p class="tabular text-price font-bold text-fg">{{ $present->price($listing) }}</p>
    </header>

    @if ($reasons !== [])
        <x-ui.alert variant="warning" :title="__('admin.moderation.reasons')">
            <ul class="list-disc pl-4">
                @foreach ($reasons as $reason)
                    <li>{{ str_starts_with($reason, 'term:') ? __('admin.moderation.reason_term', ['term' => substr($reason, 5)]) : __('admin.moderation.reason_'.$reason) }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    <section aria-labelledby="review-photos">
        <h3 id="review-photos" class="mb-2 text-h3 font-semibold">{{ trans_choice('admin.moderation.photos', $listing->images->count(), ['count' => $listing->images->count()]) }}</h3>
        @if ($listing->images->isEmpty())
            <p class="text-small text-fg-subtle">{{ __('admin.moderation.no_photos') }}</p>
        @else
            <ul class="flex gap-2 overflow-x-auto pb-1">
                @foreach ($listing->images as $image)
                    <li class="shrink-0">
                        <a href="{{ $image->url('lg') }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-control border border-line">
                            <img src="{{ $image->url('sm') }}" alt="{{ __('posting.photos.photo_n', ['n' => $loop->iteration]) }}" loading="lazy" class="h-28 w-36 bg-surface-muted object-cover">
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @if ($fields !== [] || $listing->condition)
        <section aria-labelledby="review-fields">
            <h3 id="review-fields" class="mb-2 text-h3 font-semibold">{{ __('public.details') }}</h3>
            <dl class="grid gap-x-6 text-small sm:grid-cols-2">
                @if ($listing->condition)
                    <div class="flex justify-between gap-4 border-b border-line py-1.5"><dt class="text-fg-muted">{{ __('public.condition') }}</dt><dd class="text-right font-medium text-fg">{{ __('public.conditions.'.$listing->condition->value) }}</dd></div>
                @endif
                @foreach ($fields as $label => $value)
                    <div class="flex justify-between gap-4 border-b border-line py-1.5"><dt class="text-fg-muted">{{ $label }}</dt><dd class="prose-user text-right font-medium text-fg">{{ $value }}</dd></div>
                @endforeach
            </dl>
        </section>
    @endif

    <section aria-labelledby="review-description">
        <h3 id="review-description" class="mb-2 text-h3 font-semibold">{{ __('public.description') }}</h3>
        <div class="prose-user max-h-72 overflow-y-auto whitespace-pre-line rounded-control bg-surface-muted p-3 text-ui text-fg">{{ $listing->description }}</div>
    </section>

    <section aria-labelledby="review-seller" class="flex items-start gap-3 rounded-control border border-line p-3">
        <x-ui.avatar :name="$seller['name']" size="40" />
        <div class="min-w-0 space-y-0.5 text-small">
            <h3 id="review-seller" class="text-ui font-semibold text-fg">{{ $seller['name'] }}</h3>
            <p class="text-fg-muted">
                {{ __('public.member_since', ['date' => $seller['since']?->format('F Y')]) }}
                · {{ $seller['verified'] ? __('public.email_verified') : __('admin.moderation.unverified') }}
            </p>
            <p class="tabular text-fg-muted">{{ __('admin.moderation.seller_stats', ['published' => $seller['published'], 'rejected' => $seller['rejected'], 'reports' => $seller['open_reports']]) }}</p>
        </div>
    </section>

    @foreach (['status', 'reason'] as $key)
        @if ($messages->has($key))<x-ui.alert variant="danger" role="alert">{{ $messages->first($key) }}</x-ui.alert>@endif
    @endforeach

    @if ($own)
        <x-ui.alert variant="info" :title="__('admin.moderation.own_ad_title')">{{ __('admin.moderation.own_ad') }}</x-ui.alert>
    @elseif ($decision === null)
        <div class="flex flex-wrap items-center gap-2 border-t border-line pt-4">
            <x-ui.button icon="check" wire:click="approve" data-shortcut="a" aria-keyshortcuts="A">
                {{ __('admin.moderation.approve') }}<kbd class="ml-1 rounded border border-current/30 px-1 font-mono text-micro opacity-70">A</kbd>
            </x-ui.button>
            <x-ui.button variant="secondary" icon="rotate-ccw" wire:click="startDecision('changes')" data-focus-after="#decision-reason input">{{ __('admin.moderation.request_changes') }}</x-ui.button>
            <x-ui.button variant="danger" icon="circle-x" wire:click="startDecision('reject')" data-shortcut="r" aria-keyshortcuts="R" data-focus-after="#decision-reason input">
                {{ __('admin.moderation.reject') }}<kbd class="ml-1 rounded border border-current/30 px-1 font-mono text-micro opacity-70">R</kbd>
            </x-ui.button>
        </div>
    @else
        {{-- Reason step: a template and/or the moderator's own words (at least one is required). --}}
        <div class="border-t border-line pt-4">
            <fieldset id="decision-reason" class="space-y-3">
                <legend class="mb-1 text-h3 font-semibold">{{ __('admin.moderation.reason_step_'.$decision) }}</legend>
                <p class="text-small text-fg-muted">{{ __('admin.moderation.reason_shown') }}</p>
                <div class="grid gap-2">
                    @foreach ($templates as $key => $text)
                        <x-ui.checkbox type="radio" name="template" :value="$key" :label="$text" wire:model="template" wire:key="template-{{ $key }}" />
                    @endforeach
                </div>
                <x-ui.textarea :label="__('admin.moderation.note')" id="note" wire:model="note" rows="3" :maxlength="500" />
                <div class="flex flex-wrap gap-2">
                    @if ($decision === 'reject')
                        <x-ui.button variant="danger" icon="circle-x" wire:click="reject">{{ __('admin.moderation.confirm_reject') }}</x-ui.button>
                    @else
                        <x-ui.button icon="rotate-ccw" wire:click="requestChanges">{{ __('admin.moderation.confirm_changes') }}</x-ui.button>
                    @endif
                    <x-ui.button variant="ghost" wire:click="cancelDecision" data-shortcut="escape">{{ __('admin.moderation.cancel') }}</x-ui.button>
                </div>
            </fieldset>
        </div>
    @endif
</article>
