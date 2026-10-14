@props(['href' => null, 'icon' => null, 'danger' => false])
@php($classes = ['flex w-full items-center gap-2 rounded-control px-3 py-2 text-left text-ui hover:bg-surface-hover', $danger ? 'text-danger' : 'text-fg'])
@if ($href !== null)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>@if ($icon)<x-ui.icon :name="$icon" size="16" />@endif{{ $slot }}</a>
@else
    <button type="{{ $attributes->get('type', 'button') }}" {{ $attributes->except('type')->class($classes) }}>@if ($icon)<x-ui.icon :name="$icon" size="16" />@endif{{ $slot }}</button>
@endif
