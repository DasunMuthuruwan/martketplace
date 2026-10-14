@props(['name', 'src' => null, 'size' => 40]) {{-- 24 | 32 | 40 | 64 --}}
@php
    $initials = collect(preg_split('/\s+/u', trim((string) $name)) ?: [])->filter()->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    // Classes, not inline styles: the CSP does not allow style attributes.
    $box = [24 => 'size-6 text-micro', 32 => 'size-8 text-micro', 40 => 'size-10 text-ui', 64 => 'size-16 text-h3'][(int) $size] ?? 'size-10 text-ui';
@endphp
@if ($src)
    <img src="{{ $src }}" alt="{{ $name }}" width="{{ (int) $size }}" height="{{ (int) $size }}" loading="lazy" {{ $attributes->class([$box, 'shrink-0 rounded-full border border-line object-cover']) }}>
@else
    <span role="img" aria-label="{{ $name }}" {{ $attributes->class([$box, 'inline-grid shrink-0 place-items-center rounded-full border border-line bg-surface-muted font-semibold text-fg']) }}>{{ $initials !== '' ? $initials : '?' }}</span>
@endif
