@props([
    'label' => null,
    'name' => null,
    'id' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
    'options' => [],        // [value => label]
    'selected' => null,
    'placeholder' => null,
    'empty' => null,        // text when nothing matches
])
{{-- Searchable select for long or dependent lists (ARIA 1.2 combobox with a listbox popup,
     component doc §2). The chosen value lives in a hidden input carrying name / wire:model, so
     plain forms and Livewire both work. Behaviour: resources/js/ui/combobox.js. --}}
@php
    $field = new \App\View\FieldState($attributes, $name, $id, $error, $hint, $errors ?? null);
    $selectedLabel = $selected !== null ? ($options[$selected] ?? '') : '';
    $listId = $field->id.'-list';
@endphp
<x-ui.field :label="$label" :for="$field->id" :hint="$hint" :error="$field->error" :required="$required">
    <div class="relative" data-combobox>
        <input type="hidden" data-combobox-value value="{{ $selected }}" @if ($field->name !== '' && ! $attributes->has('name') && ! $attributes->wire('model')->value()) name="{{ $field->name }}" @endif {{ $attributes->whereStartsWith(['wire:model', 'name']) }}>
        <input type="text" id="{{ $field->id }}" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="{{ $listId }}"
            autocomplete="off" value="{{ $selectedLabel }}" placeholder="{{ $placeholder }}" @if ($required) required @endif
            data-combobox-input
            {{ $attributes->whereDoesntStartWith(['wire:model', 'name'])->merge($field->aria())->class([
                'h-10 w-full rounded-control border bg-surface pl-3 pr-9 text-fg placeholder:text-fg-subtle',
                'border-danger' => $field->error,
                'border-control' => ! $field->error,
            ]) }}>
        <x-ui.icon name="chevron-down" size="16" class="pointer-events-none absolute right-3 top-3 text-fg-muted" />
        <ul id="{{ $listId }}" role="listbox" hidden data-combobox-list
            class="absolute z-40 mt-1 max-h-64 w-full overflow-auto rounded-card border border-line bg-surface p-1 shadow-overlay">
            @foreach ($options as $value => $text)
                <li role="option" id="{{ $listId }}-{{ $loop->index }}" data-value="{{ $value }}" aria-selected="{{ (string) $selected === (string) $value ? 'true' : 'false' }}"
                    class="cursor-pointer rounded-control px-3 py-2 text-ui text-fg aria-selected:font-semibold data-[active]:bg-surface-hover">{{ $text }}</li>
            @endforeach
            <li role="presentation" hidden data-combobox-empty class="px-3 py-2 text-ui text-fg-subtle">{{ $empty ?? __('ui.no_matches') }}</li>
        </ul>
    </div>
</x-ui.field>
