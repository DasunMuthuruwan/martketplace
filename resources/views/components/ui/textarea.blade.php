@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
    'rows' => 4,
    'maxlength' => null, // shows a counter "120 / 5000"
])
@php($field = new \App\View\FieldState($attributes, $name, $id, $error, $hint, $errors ?? null))
<x-ui.field :label="$label" :for="$field->id" :hint="$hint" :error="$field->error" :required="$required">
    <textarea id="{{ $field->id }}" rows="{{ $rows }}" @if ($field->name !== '' && ! $attributes->has('name')) name="{{ $field->name }}" @endif
        @if ($maxlength) maxlength="{{ $maxlength }}" data-counter="{{ $field->id }}-count" @endif
        @if ($required) required @endif
        {{ $attributes->merge($field->aria())->class([
            'w-full rounded-control border bg-surface px-3 py-2 text-fg placeholder:text-fg-subtle field-sizing-content min-h-24',
            'border-danger' => $field->error,
            'border-control' => ! $field->error,
        ]) }}>{{ $slot }}</textarea>
    @if ($maxlength)
        {{-- Updated as the person types; announced politely (resources/js/ui/forms.js). --}}
        <p id="{{ $field->id }}-count" class="self-end text-small tabular text-fg-subtle" aria-live="polite" data-max="{{ $maxlength }}">{{ mb_strlen((string) $slot) }} / {{ $maxlength }}</p>
    @endif
</x-ui.field>
