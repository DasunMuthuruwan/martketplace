{{-- Categories (ADM-02): the tree (top level and subcategories) with order, status, country scope and
     actions; the form opens beside it on desktop and above the tree on phones. --}}
<div>
    <x-admin.page-header :title="__('admin.nav.categories')" :description="__('admin.catalogue.intro')">
        <x-ui.button size="sm" icon="plus" wire:click="create">{{ __('admin.catalogue.new_top') }}</x-ui.button>
    </x-admin.page-header>

    @if (session('status'))
        <x-ui.alert variant="success" role="status" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif
    @error('delete')<x-ui.alert variant="danger" role="alert" class="mb-4">{{ $message }}</x-ui.alert>@enderror

    <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(340px,440px)]">
        <ul @class(['space-y-3', 'max-lg:hidden' => $editing]) aria-label="{{ __('admin.nav.categories') }}">
            @foreach ($roots as $root)
                <li wire:key="cat-{{ $root->id }}" class="overflow-hidden rounded-card border border-line bg-surface">
                    @include('livewire.admin.partials.category-row', ['category' => $root, 'top' => true])
                    @if ($root->children->isNotEmpty())
                        <ul class="divide-y divide-line border-t border-line">
                            @foreach ($root->children as $child)
                                <li wire:key="cat-{{ $child->id }}" class="pl-6">@include('livewire.admin.partials.category-row', ['category' => $child, 'top' => false])</li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>

        <div class="min-w-0 lg:sticky lg:top-20">
            @if ($editing)
                <section aria-labelledby="cat-form" class="rounded-card border border-line bg-surface p-4 lg:p-5">
                    <div class="mb-4 flex items-center justify-between gap-2">
                        <h2 id="cat-form" class="text-h3 font-semibold">{{ $editingId ? __('admin.catalogue.edit_named', ['name' => $name]) : __('admin.catalogue.new') }}</h2>
                        <x-ui.button size="sm" variant="ghost" icon="x" wire:click="cancel" :label="__('admin.catalogue.cancel')" />
                    </div>
                    <form wire:submit="save" class="space-y-5">
                        <fieldset class="space-y-3">
                            <legend class="mb-2 text-small font-medium uppercase tracking-wide text-fg-subtle">{{ __('admin.catalogue.section_basics') }}</legend>
                            <x-ui.input :label="__('admin.name')" id="c-name" wire:model="name" maxlength="80" required :error="$errors->first('name')" />
                            <x-ui.input :label="__('admin.catalogue.slug')" id="c-slug" wire:model="slug" maxlength="120" :hint="__('admin.catalogue.slug_help')" :error="$errors->first('slug')" />
                            <x-ui.select :label="__('admin.catalogue.parent')" id="c-parent" wire:model="parentId" :placeholder="__('admin.catalogue.top_level')" :error="$errors->first('parentId')"
                                :options="$roots->mapWithKeys(fn ($r) => [$r->id => $r->name])->all()" :selected="$parentId" />
                            <div class="grid grid-cols-2 gap-3">
                                <x-ui.input :label="__('admin.catalogue.icon')" id="c-icon" wire:model="icon" maxlength="60" :error="$errors->first('icon')" />
                                <x-ui.input type="number" :label="__('admin.catalogue.sort')" id="c-sort" wire:model="sortOrder" min="0" max="9999" />
                            </div>
                            <x-ui.checkbox type="switch" :label="__('admin.catalogue.active')" id="c-active" wire:model="isActive" :checked="$isActive" />
                            <x-ui.input :label="__('admin.catalogue.scope')" id="c-scope" wire:model="scope" maxlength="300" :hint="__('admin.catalogue.scope_help')" :error="$errors->first('scope')" />
                        </fieldset>

                        <fieldset class="space-y-3 border-t border-line pt-4">
                            <legend class="mb-2 text-small font-medium uppercase tracking-wide text-fg-subtle">{{ __('admin.catalogue.section_ads') }}</legend>
                            <div>
                                <p class="mb-1.5 text-ui font-medium text-fg">{{ __('admin.catalogue.types') }}</p>
                                <div class="flex flex-wrap gap-x-5 gap-y-2">
                                    @foreach ($listingTypes as $type)
                                        <x-ui.checkbox :label="__('public.types.'.$type->value)" id="c-type-{{ $type->value }}" wire:model="types" :value="$type->value" :checked="in_array($type->value, $types, true)" />
                                    @endforeach
                                </div>
                                @error('types')<p class="mt-1 text-small text-danger">{{ $message }}</p>@enderror
                            </div>
                            <x-ui.select :label="__('admin.catalogue.price_mode')" id="c-pricemode" wire:model="priceMode" :selected="$priceMode"
                                :options="collect($priceModes)->mapWithKeys(fn ($m) => [$m->value => __('admin.catalogue.price_modes.'.$m->value)])->all()" />
                            <div>
                                <p class="mb-1.5 text-ui font-medium text-fg">{{ __('admin.catalogue.price_types') }}</p>
                                <div class="flex flex-wrap gap-x-5 gap-y-2">
                                    @foreach ($allPriceTypes as $type)
                                        <x-ui.checkbox :label="__('admin.catalogue.price_type_names.'.$type->value)" id="c-ptype-{{ $type->value }}" wire:model="priceTypes" :value="$type->value" :checked="in_array($type->value, $priceTypes, true)" />
                                    @endforeach
                                </div>
                                <p class="mt-1 text-small text-fg-subtle">{{ __('admin.catalogue.price_types_help') }}</p>
                            </div>
                            <x-ui.checkbox type="switch" :label="__('admin.catalogue.allow_free')" id="c-free" wire:model="allowFree" :checked="$allowFree" />
                            <x-ui.checkbox type="switch" :label="__('admin.catalogue.show_condition')" id="c-condition" wire:model="showCondition" :checked="$showCondition" />
                            <div class="grid grid-cols-2 gap-3">
                                <x-ui.input type="number" :label="__('admin.catalogue.max_images')" id="c-images" wire:model="maxImages" min="1" max="20" />
                                <x-ui.select :label="__('admin.catalogue.moderation')" id="c-moderation" wire:model="moderation" :selected="$moderation"
                                    :options="collect($moderations)->mapWithKeys(fn ($m) => [$m->value => __('admin.catalogue.moderations.'.$m->value)])->all()" />
                            </div>
                        </fieldset>

                        <fieldset class="space-y-3 border-t border-line pt-4">
                            <legend class="mb-2 text-small font-medium uppercase tracking-wide text-fg-subtle">{{ __('admin.catalogue.section_seo') }}</legend>
                            <x-ui.input :label="__('admin.catalogue.meta_title')" id="c-meta-title" wire:model="metaTitle" maxlength="120" />
                            <x-ui.textarea :label="__('admin.catalogue.meta_description')" id="c-meta-description" wire:model="metaDescription" rows="2" :maxlength="300" />
                        </fieldset>

                        <div class="flex gap-2 border-t border-line pt-4">
                            <x-ui.button type="submit" icon="check">{{ __('staff.manage.save') }}</x-ui.button>
                            <x-ui.button variant="ghost" wire:click="cancel">{{ __('admin.catalogue.cancel') }}</x-ui.button>
                        </div>
                    </form>
                </section>
            @else
                <x-ui.empty-state icon="list" :title="__('admin.catalogue.choose')" class="max-lg:hidden">{{ __('admin.catalogue.choose_text') }}</x-ui.empty-state>
            @endif
        </div>
    </div>
</div>
