@props(['code', 'label' => null, 'square' => false])
{{-- Country flag from flag-icons (bundled, G-11). Decorative unless a label (the country name) is given. --}}
@php($iso = strtolower(preg_replace('/[^A-Za-z]/', '', (string) $code)))
<span @if ($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" @endif {{ $attributes->class(['fi', 'fi-'.$iso, 'fis' => $square, 'shrink-0 rounded-[2px]']) }}></span>
