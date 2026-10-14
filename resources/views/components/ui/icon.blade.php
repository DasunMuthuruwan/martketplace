@props(['name', 'size' => 20])
{{-- Decorative Lucide icon (design system §7). Give the surrounding button or link an accessible name. --}}
{!! \App\Support\Icons::svg($name, (int) $size, (string) $attributes->get('class', '')) !!}
