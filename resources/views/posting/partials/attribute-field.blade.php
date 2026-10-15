{{-- One category field from the schema (CategorySchema::form(), component doc §2). Names follow
     the server rules: attributes[key], attributes[key__unit] (area), attributes[key__other]. --}}
@php
    $key = $field['key'];
    $name = 'attributes['.$key.']';
    $id = 'f-attributes-'.$key;
    $value = $values[$key] ?? null;
    $error = $errors->first('attributes.'.$key) ?: null;
    $conditional = ! $field['required'] && ($field['required_for_conditions'] ?? []) !== [];
    $hint = trim(implode(' ', array_filter([
        $field['help'],
        $conditional ? __('posting.details.required_for', ['conditions' => implode(', ', array_map(fn ($c) => __('public.conditions.'.$c), $field['required_for_conditions']))]) : null,
    ]))) ?: null;
    $options = $field['options'] ?? [];
@endphp

@switch($field['type'])
    @case('select')
        @if ($field['depends_on'] !== null)
            @php
                $parentField = collect($fields)->firstWhere('key', $field['depends_on']);
                $parentLabels = array_column($parentField['options'] ?? [], 'label', 'value');
                $groups = collect($options)->groupBy('parent');
            @endphp
            <x-ui.field :label="$field['label']" :for="$id" :hint="$hint" :error="$error" :required="$field['required']">
                <div class="relative">
                <select id="{{ $id }}" name="{{ $name }}" data-depends-on="attributes[{{ $field['depends_on'] }}]" @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
                    class="h-10 w-full appearance-none rounded-control border border-control bg-surface pl-3 pr-9 text-fg" @if ($field['allows_other']) data-other-toggle="{{ $id }}-other" @endif>
                    <option value="">{{ __('posting.location.choose') }}</option>
                    @foreach ($groups as $parent => $items)
                        <optgroup label="{{ $parentLabels[$parent] ?? $parent }}" data-parent-value="{{ $parent }}">
                            @foreach ($items as $option)<option value="{{ $option['value'] }}" @selected((string) $value === $option['value'])>{{ $option['label'] }}</option>@endforeach
                        </optgroup>
                    @endforeach
                    @if ($field['allows_other'])<option value="other" @selected($value === 'other')>{{ __('posting.details.other') }}</option>@endif
                </select>
                <x-ui.icon name="chevron-down" size="16" class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-fg-muted" />
                </div>
            </x-ui.field>
        @elseif (count($options) > 12)
            <x-ui.combobox :label="$field['label']" :name="$name" :id="$id" :hint="$hint" :error="$error" :required="$field['required']"
                :options="array_column($options, 'label', 'value') + ($field['allows_other'] ? ['other' => __('posting.details.other')] : [])"
                :selected="$value" :placeholder="__('posting.details.type_to_find')" />
        @else
            <x-ui.select :label="$field['label']" :name="$name" :id="$id" :hint="$hint" :error="$error" :required="$field['required']" :placeholder="__('posting.location.choose')"
                :options="array_column($options, 'label', 'value') + ($field['allows_other'] ? ['other' => __('posting.details.other')] : [])" :selected="$value"
                :data-other-toggle="$field['allows_other'] ? $id.'-other' : null" />
        @endif
        @if ($field['allows_other'])
            <div id="{{ $id }}-other" @if ($value !== 'other') data-other-hidden @endif>
                <x-ui.input :label="__('posting.details.other_label', ['field' => $field['label']])" :name="'attributes['.$key.'__other]'" :id="$id.'-other-text'"
                    :value="$values[$key.'__other'] ?? ''" maxlength="80" :error="$errors->first('attributes.'.$key.'__other') ?: null" />
            </div>
        @endif
        @break

    @case('multiselect')
        @php($chosen = is_array($value) ? $value : [])
        <fieldset>
            <legend class="mb-1.5 text-ui font-medium text-fg">{{ $field['label'] }}@if ($field['required'])<span aria-hidden="true" class="text-danger"> *</span>@endif</legend>
            <div class="grid gap-1.5 sm:grid-cols-2">
                @foreach ($options as $option)
                    <x-ui.checkbox :label="$option['label']" :name="$name.'[]'" :id="$id.'-'.$loop->index" :value="$option['value']" :checked="in_array($option['value'], $chosen, true)" />
                @endforeach
            </div>
            @if ($hint)<p class="mt-1 text-small text-fg-subtle">{{ $hint }}</p>@endif
            @if ($error)<p class="mt-1 text-small text-danger">{{ $error }}</p>@endif
        </fieldset>
        @break

    @case('boolean')
        <input type="hidden" name="{{ $name }}" value="0">
        <x-ui.switch :label="$field['label']" :name="$name" :id="$id" value="1" :checked="$value === '1' || $value === true" :hint="$hint" :error="$error" />
        @break

    @case('number')
    @case('year')
        @if ($field['dimension'] === 'area' && $field['units'] !== [])
            <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,10rem)] items-start gap-3">
                <x-ui.input :label="$field['label']" :name="$name" :id="$id" :value="$value" inputmode="decimal" :hint="$hint" :error="$error" :required="$field['required']" />
                <x-ui.select :label="__('search.unit')" :name="'attributes['.$key.'__unit]'" :id="$id.'-unit'"
                    :options="array_column($field['units'], 'label', 'code')" :selected="$values[$key.'__unit'] ?? $field['default_unit']" />
            </div>
        @else
            <x-ui.input :label="$field['label']" :name="$name" :id="$id" :value="$value" :inputmode="$field['type'] === 'year' ? 'numeric' : 'decimal'"
                :suffix="$field['unit']" :hint="$hint" :error="$error" :required="$field['required']"
                :min="$field['min']" :max="$field['max']" />
        @endif
        @break

    @default
        <x-ui.input :label="$field['label']" :name="$name" :id="$id" :value="$value" maxlength="120" :hint="$hint" :error="$error" :required="$field['required']" />
@endswitch
