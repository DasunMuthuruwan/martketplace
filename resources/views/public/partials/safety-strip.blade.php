{{-- Safety reminder (layout doc §3.1), linking to the safety page when it is published. --}}
<aside aria-label="{{ __('public.home.safety_label') }}" class="flex flex-wrap items-center gap-3 rounded-card border border-line bg-surface px-4 py-3 text-ui">
    <x-ui.icon name="shield-check" size="20" class="text-success" />
    <p class="flex-1 text-fg">{{ __('public.home.safety') }}</p>
    @if (\App\Models\Page::footerLinks()->has('safety'))
        <a href="{{ route('page.show', 'safety') }}" class="inline-flex items-center gap-1 font-medium text-fg underline underline-offset-2">{{ __('public.home.safety_link') }}</a>
    @endif
</aside>
