@props([
    'label',
    'name' => null,
    'id' => null,
    'hint' => null,
    'error' => null,
    'value' => '1',
    'checked' => false,
    'type' => 'checkbox', // checkbox | radio | switch
])
@php
    $field = new \App\View\FieldState($attributes, $name, $id, $error, $hint, $errors ?? null);
    // Radios share a name, so each needs its own id.
    $controlId = $id ?? ($type === 'radio' ? $field->id.'-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $value) : $field->id);
    $switch = $type === 'switch';
@endphp
<x-ui.field :for="$controlId" :hint="$hint" :error="$field->error" inline>
    <label for="{{ $controlId }}" class="inline-flex min-h-6 cursor-pointer items-start gap-2.5 text-ui text-fg">
        <input type="{{ $type === 'radio' ? 'radio' : 'checkbox' }}" id="{{ $controlId }}" value="{{ $value }}"
            @if ($field->name !== '' && ! $attributes->has('name')) name="{{ $field->name }}" @endif
            @if ($switch) role="switch" @endif
            @checked($checked)
            {{ $attributes->merge($field->aria())->class([
                'peer mt-0.5 shrink-0 cursor-pointer accent-(--primary)',
                'size-4' => ! $switch,
                'sr-only' => $switch,
            ]) }}>
        @if ($switch)
            {{-- Visual track; the real control is the visually hidden checkbox with role="switch". --}}
            <span aria-hidden="true" class="relative mt-0.5 inline-flex h-5 w-9 shrink-0 rounded-full border border-control bg-surface-muted transition-colors duration-(--duration-fast)
                peer-checked:border-primary peer-checked:bg-primary peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-primary
                after:absolute after:left-0.5 after:top-0.5 after:size-3.5 after:rounded-full after:bg-fg-muted after:transition-transform after:duration-(--duration-fast)
                peer-checked:after:translate-x-4 peer-checked:after:bg-primary-fg"></span>
        @endif
        <span>{{ $label }}</span>
    </label>
</x-ui.field>
