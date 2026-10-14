@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
    'options' => null,      // [value => label]; or pass <option>s in the slot
    'selected' => null,     // plain forms; Livewire uses wire:model
    'placeholder' => null,  // first, empty option
])
{{-- Native select: best for short lists, especially on phones (component doc §2). --}}
@php($field = new \App\View\FieldState($attributes, $name, $id, $error, $hint, $errors ?? null))
<x-ui.field :label="$label" :for="$field->id" :hint="$hint" :error="$field->error" :required="$required">
    <div class="relative">
        <select id="{{ $field->id }}" @if ($field->name !== '' && ! $attributes->has('name')) name="{{ $field->name }}" @endif @if ($required) required @endif
            {{ $attributes->merge($field->aria())->class([
                'h-10 w-full appearance-none rounded-control border bg-surface pl-3 pr-9 text-fg',
                'border-danger' => $field->error,
                'border-control' => ! $field->error,
            ]) }}>
            @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
            @if ($options !== null)
                @foreach ($options as $value => $text)
                    <option value="{{ $value }}" @selected($selected !== null && (string) $selected === (string) $value)>{{ $text }}</option>
                @endforeach
            @endif
            {{ $slot }}
        </select>
        <x-ui.icon name="chevron-down" size="16" class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-fg-muted" />
    </div>
</x-ui.field>
