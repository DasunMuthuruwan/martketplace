@props([
    'label' => null,
    'for' => null,       // id of the control
    'hint' => null,
    'error' => null,     // message (controls pass the error-bag message)
    'required' => false,
    'inline' => false,   // checkbox/radio/switch: control before the label
])
{{-- Label always visible; hint and error linked to the control by id (`{for}-hint`, `{for}-error`, component doc §2). --}}
<div {{ $attributes->class('flex flex-col gap-1.5') }}>
    @if ($label && ! $inline)
        <label @if ($for) for="{{ $for }}" @endif class="text-ui font-medium text-fg">
            {{ $label }}@if ($required)<span aria-hidden="true" class="text-danger"> *</span><span class="sr-only"> ({{ __('ui.required') }})</span>@endif
        </label>
    @endif
    {{ $slot }}
    @if ($hint)<p @if ($for) id="{{ $for }}-hint" @endif class="text-small text-fg-subtle">{{ $hint }}</p>@endif
    @if ($error)
        <p @if ($for) id="{{ $for }}-error" @endif class="flex items-start gap-1.5 text-small text-danger">
            <x-ui.icon name="circle-alert" size="16" class="mt-0.5" />{{ $error }}
        </p>
    @endif
</div>
