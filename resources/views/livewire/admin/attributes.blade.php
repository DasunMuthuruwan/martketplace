<div>
    <h1>{{ __('admin.nav.attributes') }}</h1>

    @if (session('status'))
        <p class="status" role="status">{{ session('status') }}</p>
    @endif

    <p>
        <label>{{ __('staff.accounts.search') }}
            <input type="search" wire:model.live.debounce.400ms="q" maxlength="100">
        </label>
        <button type="button" wire:click="create">{{ __('admin.catalogue.new_attribute') }}</button>
    </p>

    <table>
        <thead><tr><th>{{ __('admin.catalogue.key') }}</th><th>{{ __('admin.catalogue.label') }}</th><th>{{ __('admin.catalogue.type') }}</th><th>{{ __('admin.catalogue.options') }}</th><th></th></tr></thead>
        <tbody>
            @foreach ($attributeList as $attribute)
                <tr wire:key="attr-{{ $attribute->id }}">
                    <td><code>{{ $attribute->key }}</code></td>
                    <td>{{ $attribute->label }} @unless ($attribute->is_active)<span class="muted">· {{ __('admin.catalogue.inactive') }}</span>@endunless</td>
                    <td>{{ __('admin.catalogue.types_attr.'.$attribute->type->value) }}</td>
                    <td>{{ $attribute->type->hasOptions() ? $attribute->options_count : '' }}</td>
                    <td><button type="button" wire:click="select({{ $attribute->id }})">{{ __('admin.catalogue.edit') }}</button></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    {{ $attributeList->links('livewire.admin.pagination') }}

    @if ($editing)
        <section class="pane" aria-labelledby="attr-form">
            <h2 id="attr-form">{{ $selected ? $selected->key : __('admin.catalogue.new_attribute') }}</h2>
            @if ($inUse)<p class="muted">{{ __('admin.catalogue.in_use') }}</p>@endif
            @error('form')<p class="error" role="alert">{{ $message }}</p>@enderror

            <form wire:submit="save">
                @unless ($selected)
                    <label for="a-key">{{ __('admin.catalogue.key') }}</label>
                    <input id="a-key" type="text" wire:model="key" maxlength="60" required>
                    <p class="muted">{{ __('admin.catalogue.key_help') }}</p>
                    @error('key')<p class="error">{{ $message }}</p>@enderror
                @endunless

                <label for="a-label">{{ __('admin.catalogue.label') }}</label>
                <input id="a-label" type="text" wire:model="label" maxlength="80" required>
                @error('label')<p class="error">{{ $message }}</p>@enderror

                <label for="a-type">{{ __('admin.catalogue.type') }}</label>
                <select id="a-type" wire:model="type" @disabled($inUse)>
                    @foreach ($types as $t)
                        <option value="{{ $t->value }}">{{ __('admin.catalogue.types_attr.'.$t->value) }}</option>
                    @endforeach
                </select>
                @error('type')<p class="error">{{ $message }}</p>@enderror

                <label for="a-depends">{{ __('admin.catalogue.depends_on') }}</label>
                <select id="a-depends" wire:model="dependsOn">
                    <option value="">{{ __('admin.catalogue.none') }}</option>
                    @foreach ($selectAttributes as $s)
                        <option value="{{ $s->id }}">{{ $s->key }}</option>
                    @endforeach
                </select>
                <p class="muted">{{ __('admin.catalogue.depends_help') }}</p>
                @error('dependsOn')<p class="error">{{ $message }}</p>@enderror

                <label for="a-unit">{{ __('admin.catalogue.unit') }}</label>
                <input id="a-unit" type="text" wire:model="unit" maxlength="20">

                <label for="a-dimension">{{ __('admin.catalogue.dimension') }}</label>
                <select id="a-dimension" wire:model="dimension">
                    <option value="">{{ __('admin.catalogue.none') }}</option>
                    <option value="{{ \App\Models\MeasurementUnit::AREA }}">{{ __('admin.catalogue.area') }}</option>
                </select>
                @error('dimension')<p class="error">{{ $message }}</p>@enderror

                <fieldset>
                    <legend>{{ __('admin.catalogue.rules') }}</legend>
                    <label for="a-min">{{ __('admin.catalogue.rule_min') }}</label>
                    <input id="a-min" type="text" inputmode="decimal" wire:model="ruleMin">
                    @error('ruleMin')<p class="error">{{ $message }}</p>@enderror
                    <label for="a-max">{{ __('admin.catalogue.rule_max') }}</label>
                    <input id="a-max" type="text" inputmode="decimal" wire:model="ruleMax">
                    @error('ruleMax')<p class="error">{{ $message }}</p>@enderror
                    <label for="a-step">{{ __('admin.catalogue.rule_step') }}</label>
                    <input id="a-step" type="text" inputmode="decimal" wire:model="ruleStep">
                    @error('ruleStep')<p class="error">{{ $message }}</p>@enderror
                    <label for="a-maxlength">{{ __('admin.catalogue.rule_maxlength') }}</label>
                    <input id="a-maxlength" type="number" wire:model="ruleMaxlength" min="1" max="2000">
                    @error('ruleMaxlength')<p class="error">{{ $message }}</p>@enderror
                    <label for="a-years">{{ __('admin.catalogue.rule_years_ahead') }}</label>
                    <input id="a-years" type="number" wire:model="ruleYearsAhead" min="0" max="5">
                    @error('ruleYearsAhead')<p class="error">{{ $message }}</p>@enderror
                    <label for="a-units">{{ __('admin.catalogue.rule_units') }}</label>
                    <input id="a-units" type="text" wire:model="ruleUnits" maxlength="200">
                    @error('ruleUnits')<p class="error">{{ $message }}</p>@enderror
                </fieldset>

                <label><input type="checkbox" wire:model="allowsOther"> {{ __('admin.catalogue.allows_other') }}</label>

                <label for="a-help">{{ __('admin.catalogue.help_text') }}</label>
                <input id="a-help" type="text" wire:model="helpText" maxlength="200">

                <label><input type="checkbox" wire:model="isActive"> {{ __('admin.catalogue.active') }}</label>

                <p>
                    <button type="submit">{{ __('staff.manage.save') }}</button>
                    <button type="button" wire:click="cancel">{{ __('admin.catalogue.cancel') }}</button>
                    @if ($selected && ! $inUse)
                        <button type="button" wire:click="deleteAttribute" wire:confirm="{{ __('admin.catalogue.confirm_delete') }}">{{ __('admin.catalogue.delete') }}</button>
                    @endif
                </p>
            </form>

            @if ($options !== null)
                <h3>{{ __('admin.catalogue.options') }}</h3>
                @error('options')<p class="error" role="alert">{{ $message }}</p>@enderror
                <p>
                    <label>{{ __('staff.accounts.search') }} <input type="search" wire:model.live.debounce.400ms="optionSearch" maxlength="80"></label>
                    @if ($parentOptions->isNotEmpty())
                        <label>{{ $selected->dependsOn?->label }}
                            <select wire:model.live="optionParentFilter">
                                <option value="">{{ __('public.any') }}</option>
                                @foreach ($parentOptions as $p)
                                    <option value="{{ $p->id }}">{{ $p->label }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                </p>
                @if ($options->isEmpty())
                    <p class="muted">{{ __('admin.catalogue.no_options_yet') }}</p>
                @else
                    <table>
                        <tbody>
                            @foreach ($options as $option)
                                <tr wire:key="opt-{{ $option->id }}">
                                    <td>{{ $option->label }} <span class="muted"><code>{{ $option->value }}</code>
                                        @if ($option->parentOption) · {{ $option->parentOption->label }} @endif
                                        @if ($option->scope_countries) · {{ implode(', ', $option->scope_countries) }} @endif
                                        @unless ($option->is_active) · {{ __('admin.catalogue.inactive') }} @endunless</span></td>
                                    <td>
                                        <button type="button" wire:click="editOption({{ $option->id }})">{{ __('admin.catalogue.edit') }}</button>
                                        <button type="button" wire:click="toggleOption({{ $option->id }})">{{ $option->is_active ? __('admin.catalogue.deactivate') : __('admin.catalogue.activate') }}</button>
                                        <button type="button" wire:click="deleteOption({{ $option->id }})" wire:confirm="{{ __('admin.catalogue.confirm_delete') }}">{{ __('admin.catalogue.delete') }}</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    {{ $options->links('livewire.admin.pagination') }}
                @endif

                <form wire:submit="saveOption">
                    <h4>{{ $optionId ? __('admin.catalogue.edit_option') : __('admin.catalogue.new_option') }}</h4>
                    <label for="o-label">{{ __('admin.catalogue.label') }}</label>
                    <input id="o-label" type="text" wire:model="optionLabel" maxlength="80" required>
                    @error('optionLabel')<p class="error">{{ $message }}</p>@enderror
                    <label for="o-value">{{ __('admin.catalogue.value') }}</label>
                    <input id="o-value" type="text" wire:model="optionValue" maxlength="80">
                    <p class="muted">{{ __('admin.catalogue.value_help') }}</p>
                    @error('optionValue')<p class="error">{{ $message }}</p>@enderror
                    @if ($parentOptions->isNotEmpty() || $selected->depends_on_attribute_id)
                        <label for="o-parent">{{ $selected->dependsOn?->label }}</label>
                        <select id="o-parent" wire:model="optionParent" required>
                            <option value=""></option>
                            @foreach ($parentOptions as $p)
                                <option value="{{ $p->id }}">{{ $p->label }}</option>
                            @endforeach
                        </select>
                        @error('optionParent')<p class="error">{{ $message }}</p>@enderror
                    @endif
                    <label for="o-scope">{{ __('admin.catalogue.scope') }}</label>
                    <input id="o-scope" type="text" wire:model="optionScope" maxlength="300">
                    <p class="muted">{{ __('admin.catalogue.scope_help') }}</p>
                    @error('optionScope')<p class="error">{{ $message }}</p>@enderror
                    <p>
                        <button type="submit">{{ __('staff.manage.save') }}</button>
                        @if ($optionId)<button type="button" wire:click="cancelOption">{{ __('admin.catalogue.cancel') }}</button>@endif
                    </p>
                </form>
            @endif

            @if ($selected)
                <h3>{{ __('admin.catalogue.categories_using') }}</h3>
                @if ($selected->categories->isEmpty())
                    <p class="muted">{{ __('admin.catalogue.no_links') }}</p>
                @else
                    <table>
                        <tbody>
                            @foreach ($selected->categories as $category)
                                <tr wire:key="link-{{ $category->id }}">
                                    <td>@if ($category->parent){{ $category->parent->name }} › @endif{{ $category->name }}
                                        <span class="muted">
                                            @if ($category->pivot->is_required) · {{ __('admin.catalogue.required') }}@if ($category->pivot->required_for_conditions) ({{ implode(', ', $category->pivot->required_for_conditions) }})@endif @endif
                                            @if ($category->pivot->is_filterable) · {{ __('admin.catalogue.filterable') }} @endif
                                            @if ($category->pivot->show_on_card) · {{ __('admin.catalogue.on_card') }} @endif
                                            @if ($category->pivot->scope_countries) · {{ implode(', ', $category->pivot->scope_countries) }} @endif
                                        </span></td>
                                    <td>
                                        <button type="button" wire:click="editLink({{ $category->id }})">{{ __('admin.catalogue.edit') }}</button>
                                        <button type="button" wire:click="detach({{ $category->id }})" wire:confirm="{{ __('admin.catalogue.confirm_detach') }}">{{ __('admin.catalogue.detach') }}</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                <form wire:submit="saveLink">
                    <h4>{{ __('admin.catalogue.link') }}</h4>
                    <label for="l-category">{{ __('admin.catalogue.category') }}</label>
                    <select id="l-category" wire:model="linkCategory" required>
                        <option value=""></option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">@if ($category->parent){{ $category->parent->name }} › @endif{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('linkCategory')<p class="error">{{ $message }}</p>@enderror
                    <label><input type="checkbox" wire:model="linkRequired"> {{ __('admin.catalogue.required') }}</label>
                    <fieldset>
                        <legend>{{ __('admin.catalogue.required_for') }}</legend>
                        @foreach ($conditions as $condition)
                            <label><input type="checkbox" wire:model="linkConditions" value="{{ $condition->value }}"> {{ $condition->value }}</label>
                        @endforeach
                    </fieldset>
                    <label><input type="checkbox" wire:model="linkFilterable"> {{ __('admin.catalogue.filterable') }}</label>
                    <label><input type="checkbox" wire:model="linkCard"> {{ __('admin.catalogue.on_card') }}</label>
                    <label for="l-group">{{ __('admin.catalogue.group') }}</label>
                    <input id="l-group" type="text" wire:model="linkGroup" maxlength="40">
                    <label for="l-sort">{{ __('admin.catalogue.sort') }}</label>
                    <input id="l-sort" type="number" wire:model="linkSort" min="0" max="9999">
                    <label for="l-scope">{{ __('admin.catalogue.scope') }}</label>
                    <input id="l-scope" type="text" wire:model="linkScope" maxlength="300">
                    @error('linkScope')<p class="error">{{ $message }}</p>@enderror
                    <p>
                        <button type="submit">{{ __('staff.manage.save') }}</button>
                        <button type="button" wire:click="cancelLink">{{ __('admin.catalogue.cancel') }}</button>
                    </p>
                </form>
            @endif
        </section>
    @endif
</div>
