<div>
    <h1>{{ __('admin.nav.categories') }}</h1>

    @if (session('status'))
        <p class="status" role="status">{{ session('status') }}</p>
    @endif
    @error('delete')<p class="error" role="alert">{{ $message }}</p>@enderror

    <p><button type="button" wire:click="create">{{ __('admin.catalogue.new_top') }}</button></p>

    <ul>
        @foreach ($roots as $root)
            <li wire:key="cat-{{ $root->id }}">
                <strong>{{ $root->name }}</strong> <span class="muted">/{{ $root->slug }} @unless ($root->is_active)· {{ __('admin.catalogue.inactive') }}@endunless @if ($root->scope_countries)· {{ implode(', ', $root->scope_countries) }}@endif</span>
                <button type="button" wire:click="move({{ $root->id }}, 'up')" aria-label="{{ __('admin.catalogue.up') }}">↑</button>
                <button type="button" wire:click="move({{ $root->id }}, 'down')" aria-label="{{ __('admin.catalogue.down') }}">↓</button>
                <button type="button" wire:click="edit({{ $root->id }})">{{ __('admin.catalogue.edit') }}</button>
                <button type="button" wire:click="create({{ $root->id }})">{{ __('admin.catalogue.new_sub') }}</button>
                @if ($root->children->isEmpty())
                    <button type="button" wire:click="delete({{ $root->id }})" wire:confirm="{{ __('admin.catalogue.confirm_delete') }}">{{ __('admin.catalogue.delete') }}</button>
                @endif
                <ul>
                    @foreach ($root->children as $child)
                        <li wire:key="cat-{{ $child->id }}">
                            {{ $child->name }} <span class="muted">/{{ $child->slug }} @unless ($child->is_active)· {{ __('admin.catalogue.inactive') }}@endunless @if ($child->scope_countries)· {{ implode(', ', $child->scope_countries) }}@endif</span>
                            <button type="button" wire:click="move({{ $child->id }}, 'up')" aria-label="{{ __('admin.catalogue.up') }}">↑</button>
                            <button type="button" wire:click="move({{ $child->id }}, 'down')" aria-label="{{ __('admin.catalogue.down') }}">↓</button>
                            <button type="button" wire:click="edit({{ $child->id }})">{{ __('admin.catalogue.edit') }}</button>
                            <button type="button" wire:click="delete({{ $child->id }})" wire:confirm="{{ __('admin.catalogue.confirm_delete') }}">{{ __('admin.catalogue.delete') }}</button>
                        </li>
                    @endforeach
                </ul>
            </li>
        @endforeach
    </ul>

    @if ($editing)
        <section class="pane" aria-labelledby="cat-form">
            <h2 id="cat-form">{{ $editingId ? __('admin.catalogue.edit') : __('admin.catalogue.new') }}</h2>
            <form wire:submit="save">
                <label for="c-name">{{ __('auth.ui.name') }}</label>
                <input id="c-name" type="text" wire:model="name" maxlength="80" required>
                @error('name')<p class="error">{{ $message }}</p>@enderror

                <label for="c-slug">{{ __('admin.catalogue.slug') }}</label>
                <input id="c-slug" type="text" wire:model="slug" maxlength="120">
                <p class="muted">{{ __('admin.catalogue.slug_help') }}</p>
                @error('slug')<p class="error">{{ $message }}</p>@enderror

                <label for="c-parent">{{ __('admin.catalogue.parent') }}</label>
                <select id="c-parent" wire:model="parentId">
                    <option value="">{{ __('admin.catalogue.top_level') }}</option>
                    @foreach ($roots as $root)
                        <option value="{{ $root->id }}">{{ $root->name }}</option>
                    @endforeach
                </select>
                @error('parentId')<p class="error">{{ $message }}</p>@enderror

                <label for="c-icon">{{ __('admin.catalogue.icon') }}</label>
                <input id="c-icon" type="text" wire:model="icon" maxlength="60">
                @error('icon')<p class="error">{{ $message }}</p>@enderror

                <label for="c-sort">{{ __('admin.catalogue.sort') }}</label>
                <input id="c-sort" type="number" wire:model="sortOrder" min="0" max="9999">

                <label><input type="checkbox" wire:model="isActive"> {{ __('admin.catalogue.active') }}</label>

                <label for="c-scope">{{ __('admin.catalogue.scope') }}</label>
                <input id="c-scope" type="text" wire:model="scope" maxlength="300">
                <p class="muted">{{ __('admin.catalogue.scope_help') }}</p>
                @error('scope')<p class="error">{{ $message }}</p>@enderror

                <fieldset>
                    <legend>{{ __('admin.catalogue.types') }}</legend>
                    @foreach ($listingTypes as $type)
                        <label><input type="checkbox" wire:model="types" value="{{ $type->value }}"> {{ __('public.types.'.$type->value) }}</label>
                    @endforeach
                </fieldset>
                @error('types')<p class="error">{{ $message }}</p>@enderror

                <label for="c-pricemode">{{ __('admin.catalogue.price_mode') }}</label>
                <select id="c-pricemode" wire:model="priceMode">
                    @foreach ($priceModes as $mode)
                        <option value="{{ $mode->value }}">{{ __('admin.catalogue.price_modes.'.$mode->value) }}</option>
                    @endforeach
                </select>

                <fieldset>
                    <legend>{{ __('admin.catalogue.price_types') }}</legend>
                    @foreach ($allPriceTypes as $type)
                        <label><input type="checkbox" wire:model="priceTypes" value="{{ $type->value }}"> {{ __('admin.catalogue.price_type_names.'.$type->value) }}</label>
                    @endforeach
                    <p class="muted">{{ __('admin.catalogue.price_types_help') }}</p>
                </fieldset>

                <label><input type="checkbox" wire:model="allowFree"> {{ __('admin.catalogue.allow_free') }}</label>
                <label><input type="checkbox" wire:model="showCondition"> {{ __('admin.catalogue.show_condition') }}</label>

                <label for="c-images">{{ __('admin.catalogue.max_images') }}</label>
                <input id="c-images" type="number" wire:model="maxImages" min="1" max="20">

                <label for="c-moderation">{{ __('admin.catalogue.moderation') }}</label>
                <select id="c-moderation" wire:model="moderation">
                    @foreach ($moderations as $m)
                        <option value="{{ $m->value }}">{{ __('admin.catalogue.moderations.'.$m->value) }}</option>
                    @endforeach
                </select>

                <label for="c-meta-title">{{ __('admin.catalogue.meta_title') }}</label>
                <input id="c-meta-title" type="text" wire:model="metaTitle" maxlength="120">
                <label for="c-meta-description">{{ __('admin.catalogue.meta_description') }}</label>
                <textarea id="c-meta-description" wire:model="metaDescription" rows="2" maxlength="300"></textarea>

                <p>
                    <button type="submit">{{ __('staff.manage.save') }}</button>
                    <button type="button" wire:click="cancel">{{ __('admin.catalogue.cancel') }}</button>
                </p>
            </form>
        </section>
    @endif
</div>
