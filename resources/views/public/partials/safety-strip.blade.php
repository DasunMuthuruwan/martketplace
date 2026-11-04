{{-- Safety reminder (layout doc §3.1, U2): practical tips, linking to the safety page when it is published. --}}
@php($tipIcons = ['map-pin', 'eye', 'ban', 'flag'])
<aside aria-labelledby="safety-strip-title" class="rounded-sheet border border-line bg-surface p-5 sm:p-6">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 id="safety-strip-title" class="flex items-center gap-2 text-h3 font-semibold text-fg"><x-ui.icon name="shield-check" size="24" class="text-success" />{{ __('public.home.safety_title') }}</h2>
        @if (\App\Models\Page::footerLinks()->has('safety'))
            <a href="{{ route('page.show', 'safety') }}" class="inline-flex items-center gap-1 text-ui font-medium text-primary hover:underline">{{ __('public.home.safety_link') }}<x-ui.icon name="chevron-right" size="16" /></a>
        @endif
    </div>
    <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach (__('public.home.safety_tips') as $tip)
            <li class="flex items-start gap-3 rounded-card bg-surface-muted p-3 text-ui text-fg">
                <span class="grid size-8 shrink-0 place-items-center rounded-full bg-surface text-fg-muted"><x-ui.icon :name="$tipIcons[$loop->index] ?? 'shield-check'" size="16" /></span>{{ $tip }}
            </li>
        @endforeach
    </ul>
</aside>
