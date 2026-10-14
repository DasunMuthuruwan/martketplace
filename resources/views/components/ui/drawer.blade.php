@props([
    'id',
    'title',
    'side' => 'left',  // left | right | bottom (sheet)
])
{{-- Mobile filters (full height) and sort sheets (bottom). Same focus rules as the modal
     (component doc §4); opened and closed with data-modal-open / data-modal-close. --}}
@php
    $placement = [
        'left' => 'mr-auto ml-0 h-dvh max-h-dvh w-[min(24rem,100%)] rounded-none',
        'right' => 'ml-auto mr-0 h-dvh max-h-dvh w-[min(24rem,100%)] rounded-none',
        'bottom' => 'mb-0 mt-auto w-full max-w-none rounded-t-sheet pb-[env(safe-area-inset-bottom)]',
    ][$side] ?? '';
@endphp
<dialog id="{{ $id }}" aria-labelledby="{{ $id }}-title"
    {{ $attributes->class(['flex-col border border-line bg-surface p-0 text-fg shadow-overlay backdrop:bg-overlay open:flex', $placement]) }}>
    <div class="flex items-center justify-between gap-4 border-b border-line px-4 py-3">
        <h2 id="{{ $id }}-title" class="text-h3 font-semibold">{{ $title }}</h2>
        <x-ui.button variant="ghost" size="sm" icon="x" :label="__('ui.close')" data-modal-close />
    </div>
    <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4">{{ $slot }}</div>
    @isset($footer)<div class="flex gap-2 border-t border-line px-4 py-3">{{ $footer }}</div>@endisset
</dialog>
