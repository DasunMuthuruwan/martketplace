@props([
    'title',
    'count' => null,       // e.g. items waiting, shown as a badge
    'description' => null,
])
{{-- Admin screen header: title (+ count), short description, actions on the right (slot), filters below. --}}
<div {{ $attributes->class('mb-5 space-y-3') }}>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h1 class="flex items-center gap-2 text-h1 font-semibold">{{ $title }}@if ($count !== null)<x-ui.badge>{{ number_format($count) }}</x-ui.badge>@endif</h1>
            @if ($description)<p class="mt-1 text-ui text-fg-muted">{{ $description }}</p>@endif
        </div>
        @if ($slot->isNotEmpty())<div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>@endif
    </div>
    @isset($filters)<div class="flex flex-wrap items-end gap-3">{{ $filters }}</div>@endisset
</div>
