@props(['icon' => 'search', 'title'])
<div {{ $attributes->class('flex flex-col items-center gap-3 rounded-card border border-dashed border-line px-6 py-10 text-center') }}>
    <span class="grid size-12 place-items-center rounded-full bg-surface-muted text-fg-muted"><x-ui.icon :name="$icon" size="24" /></span>
    <p class="text-h3 font-semibold text-fg">{{ $title }}</p>
    @if ($slot->isNotEmpty())<p class="max-w-md text-ui text-fg-muted">{{ $slot }}</p>@endif
    @isset($action)<div class="mt-1">{{ $action }}</div>@endisset
</div>
