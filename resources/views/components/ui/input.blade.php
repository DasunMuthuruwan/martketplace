@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'type' => 'text',
    'hint' => null,
    'error' => null,
    'required' => false,
    'prefix' => null,   // e.g. a currency symbol
    'suffix' => null,   // e.g. km
])
@php
    $field = new \App\View\FieldState($attributes, $name, $id, $error, $hint, $errors ?? null);
    $password = $type === 'password';
@endphp
<x-ui.field :label="$label" :for="$field->id" :hint="$hint" :error="$field->error" :required="$required">
    <div @class([
        'flex h-10 items-stretch overflow-hidden rounded-control border bg-surface transition-colors duration-(--duration-fast)',
        'focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-primary',
        'border-danger' => $field->error,
        'border-control' => ! $field->error,
    ]) @if ($password) data-password-field @endif>
        @if ($prefix)<span class="flex items-center border-r border-line bg-surface-muted px-3 text-ui text-fg-muted">{{ $prefix }}</span>@endif
        <input type="{{ $type }}" id="{{ $field->id }}" @if ($field->name !== '' && ! $attributes->has('name')) name="{{ $field->name }}" @endif
            @if ($required) required @endif
            {{ $attributes->merge($field->aria())->class('min-w-0 flex-1 bg-transparent px-3 text-fg placeholder:text-fg-subtle focus:outline-none') }}>
        @if ($suffix)<span class="flex items-center border-l border-line bg-surface-muted px-3 text-ui text-fg-muted">{{ $suffix }}</span>@endif
        @if ($password)
            <button type="button" data-password-toggle aria-pressed="false" aria-controls="{{ $field->id }}" aria-label="{{ __('ui.show_password') }}"
                data-label-show="{{ __('ui.show_password') }}" data-label-hide="{{ __('ui.hide_password') }}"
                class="grid w-10 place-items-center text-fg-muted hover:text-fg">
                <x-ui.icon name="eye" size="20" />
            </button>
        @endif
    </div>
</x-ui.field>
