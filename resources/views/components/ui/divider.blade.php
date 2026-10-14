@props(['label' => null])
@if ($label)
    <div role="separator" aria-label="{{ $label }}" {{ $attributes->class('flex items-center gap-3 text-small text-fg-subtle') }}>
        <span class="h-px flex-1 bg-line"></span>{{ $label }}<span class="h-px flex-1 bg-line"></span>
    </div>
@else
    <hr {{ $attributes->class('border-0 border-t border-line') }}>
@endif
