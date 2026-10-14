@props([
    'id',
    'title',
    'size' => 'md',    // sm | md | lg
    'open' => false,   // open on page load (e.g. after a failed submit)
])
{{-- Native <dialog> (component doc §4): focus moves in and is trapped, Esc closes, focus returns to
     the trigger. Open with any element carrying data-modal-open="{id}"; close with data-modal-close.
     Behaviour: resources/js/ui/overlays.js. --}}
@php($widths = ['sm' => 'max-w-sm', 'md' => 'max-w-lg', 'lg' => 'max-w-2xl'])
<dialog id="{{ $id }}" aria-labelledby="{{ $id }}-title" @if ($open) data-open @endif
    {{ $attributes->class(['m-auto w-[calc(100%-2rem)] rounded-sheet border border-line bg-surface p-0 text-fg shadow-overlay backdrop:bg-overlay', $widths[$size] ?? $widths['md']]) }}>
    <div class="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
        <h2 id="{{ $id }}-title" class="text-h2 font-semibold">{{ $title }}</h2>
        <x-ui.button variant="ghost" size="sm" icon="x" :label="__('ui.close')" data-modal-close />
    </div>
    <div class="px-5 py-4">{{ $slot }}</div>
    @isset($footer)<div class="flex flex-wrap justify-end gap-2 border-t border-line px-5 py-4">{{ $footer }}</div>@endisset
</dialog>
