{{-- Fields / attributes (ADM-03): the list, or one field's editor in three cards (the field, its options,
     the categories that use it). The editor replaces the list so each card has room. --}}
<div>
    @if (! $editing)
        <x-admin.page-header :title="__('admin.nav.attributes')" :count="$attributeList->total()" :description="__('admin.catalogue.attributes_intro')">
            <x-ui.button size="sm" icon="plus" wire:click="create">{{ __('admin.catalogue.new_attribute') }}</x-ui.button>
            <x-slot:filters>
                <x-ui.input type="search" :label="__('admin.catalogue.search_fields')" id="a-q" wire:model.live.debounce.400ms="q" maxlength="100" class="min-w-64" />
            </x-slot:filters>
        </x-admin.page-header>

        @if (session('status'))
            <x-ui.alert variant="success" role="status" class="mb-4">{{ session('status') }}</x-ui.alert>
        @endif

        @if ($attributeList->isEmpty())
            <x-ui.empty-state icon="sliders-horizontal" :title="__('admin.catalogue.no_fields')" />
        @else
            <x-ui.table :caption="__('admin.nav.attributes')">
                <thead>
                    <tr>
                        <th scope="col">{{ __('admin.catalogue.label') }}</th>
                        <th scope="col">{{ __('admin.catalogue.key') }}</th>
                        <th scope="col">{{ __('admin.catalogue.type') }}</th>
                        <th scope="col" class="num">{{ __('admin.catalogue.options') }}</th>
                        <th scope="col"><span class="sr-only">{{ __('admin.catalogue.edit') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($attributeList as $attribute)
                        <tr wire:key="attr-{{ $attribute->id }}">
                            <td data-label="{{ __('admin.catalogue.label') }}">
                                <div>
                                    <span class="font-medium text-fg">{{ $attribute->label }}</span>
                                    @unless ($attribute->is_active)<x-ui.badge variant="warning" class="ml-1">{{ __('admin.catalogue.inactive') }}</x-ui.badge>@endunless
                                </div>
                            </td>
                            <td data-label="{{ __('admin.catalogue.key') }}"><code class="font-mono text-micro text-fg-muted">{{ $attribute->key }}</code></td>
                            <td data-label="{{ __('admin.catalogue.type') }}" class="text-fg-muted">{{ __('admin.catalogue.types_attr.'.$attribute->type->value) }}</td>
                            <td data-label="{{ __('admin.catalogue.options') }}" class="num text-fg-muted">{{ $attribute->type->hasOptions() ? number_format($attribute->options_count) : '–' }}</td>
                            <td class="text-right">
                                <x-ui.button size="sm" variant="secondary" icon="pencil" wire:click="select({{ $attribute->id }})">{{ __('admin.catalogue.edit') }}</x-ui.button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.table>
            {{ $attributeList->links('livewire.admin.pagination') }}
        @endif
    @else
        <x-ui.button variant="ghost" size="sm" icon="chevron-left" wire:click="cancel" class="mb-2">{{ __('admin.catalogue.back_to_fields') }}</x-ui.button>
        <x-admin.page-header :title="$selected ? $selected->label : __('admin.catalogue.new_attribute')"
            :description="$selected ? __('admin.catalogue.field_key', ['key' => $selected->key]) : __('admin.catalogue.key_help')" />

        @if (session('status'))
            <x-ui.alert variant="success" role="status" class="mb-4">{{ session('status') }}</x-ui.alert>
        @endif

        <div class="grid items-start gap-4 xl:grid-cols-2">
            {{-- 1. The field --}}
            <section aria-labelledby="attr-form" class="rounded-card border border-line bg-surface p-4 lg:p-5 xl:row-span-2">
                <h2 id="attr-form" class="mb-3 text-h3 font-semibold">{{ __('admin.catalogue.section_field') }}</h2>
                @if ($inUse)<x-ui.alert variant="info" class="mb-3">{{ __('admin.catalogue.in_use') }}</x-ui.alert>@endif
                @error('form')<x-ui.alert variant="danger" role="alert" class="mb-3">{{ $message }}</x-ui.alert>@enderror

                <form wire:submit="save" class="space-y-4">
                    @unless ($selected)
                        <x-ui.input :label="__('admin.catalogue.key')" id="a-key" wire:model="key" maxlength="60" required :hint="__('admin.catalogue.key_help')" :error="$errors->first('key')" />
                    @endunless
                    <x-ui.input :label="__('admin.catalogue.label')" id="a-label" wire:model="label" maxlength="80" required :error="$errors->first('label')" />
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-ui.select :label="__('admin.catalogue.type')" id="a-type" wire:model="type" :disabled="$inUse" :selected="$type" :error="$errors->first('type')"
                            :options="collect($types)->mapWithKeys(fn ($t) => [$t->value => __('admin.catalogue.types_attr.'.$t->value)])->all()" />
                        <x-ui.select :label="__('admin.catalogue.depends_on')" id="a-depends" wire:model="dependsOn" :placeholder="__('admin.catalogue.none')" :selected="$dependsOn"
                            :hint="__('admin.catalogue.depends_help')" :error="$errors->first('dependsOn')"
                            :options="$selectAttributes->mapWithKeys(fn ($s) => [$s->id => $s->key])->all()" />
                        <x-ui.input :label="__('admin.catalogue.unit')" id="a-unit" wire:model="unit" maxlength="20" />
                        <x-ui.select :label="__('admin.catalogue.dimension')" id="a-dimension" wire:model="dimension" :placeholder="__('admin.catalogue.none')" :selected="$dimension"
                            :error="$errors->first('dimension')" :options="[\App\Models\MeasurementUnit::AREA => __('admin.catalogue.area')]" />
                    </div>

                    <fieldset class="space-y-2 rounded-control border border-line p-3">
                        <legend class="px-1 text-ui font-medium text-fg">{{ __('admin.catalogue.rules') }}</legend>
                        <p class="text-small text-fg-subtle">{{ __('admin.catalogue.rules_help') }}</p>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <x-ui.input :label="__('admin.catalogue.rule_min')" id="a-min" inputmode="decimal" wire:model="ruleMin" :error="$errors->first('ruleMin')" />
                            <x-ui.input :label="__('admin.catalogue.rule_max')" id="a-max" inputmode="decimal" wire:model="ruleMax" :error="$errors->first('ruleMax')" />
                            <x-ui.input :label="__('admin.catalogue.rule_step')" id="a-step" inputmode="decimal" wire:model="ruleStep" :error="$errors->first('ruleStep')" />
                            <x-ui.input type="number" :label="__('admin.catalogue.rule_maxlength')" id="a-maxlength" wire:model="ruleMaxlength" min="1" max="2000" :error="$errors->first('ruleMaxlength')" />
                            <x-ui.input type="number" :label="__('admin.catalogue.rule_years_ahead')" id="a-years" wire:model="ruleYearsAhead" min="0" max="5" :error="$errors->first('ruleYearsAhead')" />
                            <x-ui.input :label="__('admin.catalogue.rule_units')" id="a-units" wire:model="ruleUnits" maxlength="200" :error="$errors->first('ruleUnits')" />
                        </div>
                    </fieldset>

                    <x-ui.input :label="__('admin.catalogue.help_text')" id="a-help" wire:model="helpText" maxlength="200" />
                    <div class="flex flex-wrap gap-x-6 gap-y-2">
                        <x-ui.checkbox type="switch" :label="__('admin.catalogue.allows_other')" id="a-other" wire:model="allowsOther" :checked="$allowsOther" />
                        <x-ui.checkbox type="switch" :label="__('admin.catalogue.active')" id="a-active" wire:model="isActive" :checked="$isActive" />
                    </div>

                    <div class="flex flex-wrap gap-2 border-t border-line pt-4">
                        <x-ui.button type="submit" icon="check">{{ __('staff.manage.save') }}</x-ui.button>
                        <x-ui.button variant="ghost" wire:click="cancel">{{ __('admin.catalogue.cancel') }}</x-ui.button>
                        @if ($selected && ! $inUse)
                            <x-ui.button variant="ghost" icon="trash-2" class="ml-auto text-danger" wire:click="deleteAttribute" wire:confirm="{{ __('admin.catalogue.confirm_delete') }}">{{ __('admin.catalogue.delete') }}</x-ui.button>
                        @endif
                    </div>
                </form>
            </section>

            {{-- 2. Options (choice fields) --}}
            @if ($options !== null)
                <section aria-labelledby="attr-options" class="rounded-card border border-line bg-surface p-4 lg:p-5">
                    <h2 id="attr-options" class="mb-3 text-h3 font-semibold">{{ __('admin.catalogue.options') }} <span class="font-normal text-fg-subtle">· {{ number_format($options->total()) }}</span></h2>
                    @error('options')<x-ui.alert variant="danger" role="alert" class="mb-3">{{ $message }}</x-ui.alert>@enderror
                    <div class="mb-3 flex flex-wrap items-end gap-3">
                        <x-ui.input type="search" :label="__('admin.catalogue.search_options')" id="o-q" wire:model.live.debounce.400ms="optionSearch" maxlength="80" class="min-w-48" />
                        @if ($parentOptions->isNotEmpty())
                            <x-ui.select :label="$selected->dependsOn?->label" id="o-filter" wire:model.live="optionParentFilter" :placeholder="__('public.any')" class="min-w-44"
                                :options="$parentOptions->mapWithKeys(fn ($p) => [$p->id => $p->label])->all()" />
                        @endif
                    </div>

                    @if ($options->isEmpty())
                        <p class="mb-3 text-small text-fg-subtle">{{ __('admin.catalogue.no_options_yet') }}</p>
                    @else
                        <ul class="mb-3 divide-y divide-line rounded-control border border-line">
                            @foreach ($options as $option)
                                <li wire:key="opt-{{ $option->id }}" class="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2">
                                    <div class="min-w-0 flex-1">
                                        <p @class(['text-ui', 'text-fg' => $option->is_active, 'text-fg-subtle line-through' => ! $option->is_active])>{{ $option->label }}</p>
                                        <p class="flex flex-wrap gap-x-2 text-small text-fg-subtle">
                                            <code class="font-mono">{{ $option->value }}</code>
                                            @if ($option->parentOption)<span>{{ $option->parentOption->label }}</span>@endif
                                            @if ($option->scope_countries)<span>{{ implode(', ', $option->scope_countries) }}</span>@endif
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-0.5">
                                        <x-ui.button size="sm" variant="ghost" icon="pencil" wire:click="editOption({{ $option->id }})" :label="__('admin.catalogue.edit_named', ['name' => $option->label])" />
                                        <x-ui.button size="sm" variant="ghost" :icon="$option->is_active ? 'eye-off' : 'eye'" wire:click="toggleOption({{ $option->id }})"
                                            :label="($option->is_active ? __('admin.catalogue.deactivate') : __('admin.catalogue.activate')).': '.$option->label" />
                                        <x-ui.button size="sm" variant="ghost" icon="trash-2" wire:click="deleteOption({{ $option->id }})" wire:confirm="{{ __('admin.catalogue.confirm_delete') }}"
                                            :label="__('admin.catalogue.delete_named', ['name' => $option->label])" />
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                        {{ $options->links('livewire.admin.pagination') }}
                    @endif

                    <form wire:submit="saveOption" class="mt-4 space-y-3 rounded-control bg-surface-muted p-3">
                        <h3 class="text-ui font-semibold">{{ $optionId ? __('admin.catalogue.edit_option') : __('admin.catalogue.new_option') }}</h3>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-ui.input :label="__('admin.catalogue.label')" id="o-label" wire:model="optionLabel" maxlength="80" required :error="$errors->first('optionLabel')" />
                            <x-ui.input :label="__('admin.catalogue.value')" id="o-value" wire:model="optionValue" maxlength="80" :hint="__('admin.catalogue.value_help')" :error="$errors->first('optionValue')" />
                            @if ($parentOptions->isNotEmpty() || $selected->depends_on_attribute_id)
                                <x-ui.select :label="$selected->dependsOn?->label" id="o-parent" wire:model="optionParent" required placeholder="" :selected="$optionParent" :error="$errors->first('optionParent')"
                                    :options="$parentOptions->mapWithKeys(fn ($p) => [$p->id => $p->label])->all()" />
                            @endif
                            <x-ui.input :label="__('admin.catalogue.scope')" id="o-scope" wire:model="optionScope" maxlength="300" :hint="__('admin.catalogue.scope_help')" :error="$errors->first('optionScope')" />
                        </div>
                        <div class="flex gap-2">
                            <x-ui.button type="submit" size="sm" :icon="$optionId ? 'check' : 'plus'">{{ $optionId ? __('staff.manage.save') : __('admin.catalogue.add_option') }}</x-ui.button>
                            @if ($optionId)<x-ui.button size="sm" variant="ghost" wire:click="cancelOption">{{ __('admin.catalogue.cancel') }}</x-ui.button>@endif
                        </div>
                    </form>
                </section>
            @endif

            {{-- 3. Categories using the field --}}
            @if ($selected)
                <section aria-labelledby="attr-links" class="rounded-card border border-line bg-surface p-4 lg:p-5">
                    <h2 id="attr-links" class="mb-3 text-h3 font-semibold">{{ __('admin.catalogue.categories_using') }}</h2>
                    @if ($selected->categories->isEmpty())
                        <p class="mb-3 text-small text-fg-subtle">{{ __('admin.catalogue.no_links') }}</p>
                    @else
                        <ul class="mb-3 divide-y divide-line rounded-control border border-line">
                            @foreach ($selected->categories as $category)
                                <li wire:key="link-{{ $category->id }}" class="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-ui text-fg">@if ($category->parent){{ $category->parent->name }} › @endif{{ $category->name }}</p>
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            @if ($category->pivot->is_required)
                                                <x-ui.badge>{{ __('admin.catalogue.required') }}@if ($category->pivot->required_for_conditions): {{ collect($category->pivot->required_for_conditions)->map(fn ($c) => __('public.conditions.'.$c))->implode(', ') }}@endif</x-ui.badge>
                                            @endif
                                            @if ($category->pivot->is_filterable)<x-ui.badge variant="outline">{{ __('admin.catalogue.filterable') }}</x-ui.badge>@endif
                                            @if ($category->pivot->show_on_card)<x-ui.badge variant="outline">{{ __('admin.catalogue.on_card') }}</x-ui.badge>@endif
                                            @if ($category->pivot->scope_countries)<x-ui.badge variant="outline" icon="globe">{{ implode(', ', $category->pivot->scope_countries) }}</x-ui.badge>@endif
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-0.5">
                                        <x-ui.button size="sm" variant="ghost" icon="pencil" wire:click="editLink({{ $category->id }})" :label="__('admin.catalogue.edit_named', ['name' => $category->name])" />
                                        <x-ui.button size="sm" variant="ghost" icon="x" wire:click="detach({{ $category->id }})" wire:confirm="{{ __('admin.catalogue.confirm_detach') }}"
                                            :label="__('admin.catalogue.detach_named', ['name' => $category->name])" />
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <form wire:submit="saveLink" class="space-y-3 rounded-control bg-surface-muted p-3">
                        <h3 class="text-ui font-semibold">{{ __('admin.catalogue.link') }}</h3>
                        <x-ui.select :label="__('admin.catalogue.category')" id="l-category" wire:model="linkCategory" required placeholder="" :selected="$linkCategory" :error="$errors->first('linkCategory')"
                            :options="$categories->mapWithKeys(fn ($c) => [$c->id => ($c->parent ? $c->parent->name.' › ' : '').$c->name])->all()" />
                        <div class="flex flex-wrap gap-x-6 gap-y-2">
                            <x-ui.checkbox :label="__('admin.catalogue.required')" id="l-required" wire:model="linkRequired" :checked="$linkRequired" />
                            <x-ui.checkbox :label="__('admin.catalogue.filterable')" id="l-filterable" wire:model="linkFilterable" :checked="$linkFilterable" />
                            <x-ui.checkbox :label="__('admin.catalogue.on_card')" id="l-card" wire:model="linkCard" :checked="$linkCard" />
                        </div>
                        <fieldset>
                            <legend class="mb-1.5 text-ui font-medium text-fg">{{ __('admin.catalogue.required_for') }}</legend>
                            <div class="flex flex-wrap gap-x-5 gap-y-2">
                                @foreach ($conditions as $condition)
                                    <x-ui.checkbox :label="__('public.conditions.'.$condition->value)" id="l-cond-{{ $condition->value }}" wire:model="linkConditions" :value="$condition->value"
                                        :checked="in_array($condition->value, $linkConditions, true)" />
                                @endforeach
                            </div>
                        </fieldset>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <x-ui.input :label="__('admin.catalogue.group')" id="l-group" wire:model="linkGroup" maxlength="40" />
                            <x-ui.input type="number" :label="__('admin.catalogue.sort')" id="l-sort" wire:model="linkSort" min="0" max="9999" />
                            <x-ui.input :label="__('admin.catalogue.scope')" id="l-scope" wire:model="linkScope" maxlength="300" :error="$errors->first('linkScope')" />
                        </div>
                        <div class="flex gap-2">
                            <x-ui.button type="submit" size="sm" icon="check">{{ __('staff.manage.save') }}</x-ui.button>
                            <x-ui.button size="sm" variant="ghost" wire:click="cancelLink">{{ __('admin.catalogue.cancel') }}</x-ui.button>
                        </div>
                    </form>
                </section>
            @endif
        </div>
    @endif
</div>
