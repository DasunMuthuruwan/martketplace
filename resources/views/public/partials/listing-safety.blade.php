{{-- Safety tips (components §5.2), collapsible. --}}
<details class="group rounded-card border border-line bg-surface p-5">
    <summary class="flex cursor-pointer list-none items-center justify-between gap-2 text-ui font-semibold text-fg [&::-webkit-details-marker]:hidden">
        <span class="flex items-center gap-2"><x-ui.icon name="shield-check" size="20" class="text-success" />{{ __('listings.detail.safety_title') }}</span>
        <x-ui.icon name="chevron-down" size="16" class="transition-transform group-open:rotate-180" />
    </summary>
    <ul class="mt-3 list-disc space-y-1 pl-5 text-ui text-fg-muted">
        @foreach (__('listings.detail.safety_tips') as $tip)<li>{{ $tip }}</li>@endforeach
    </ul>
    @if (\App\Models\Page::footerLinks()->has('safety'))
        <a href="{{ route('page.show', 'safety') }}" class="mt-3 inline-block text-ui font-medium text-fg underline underline-offset-2">{{ __('public.home.safety_link') }}</a>
    @endif
</details>
