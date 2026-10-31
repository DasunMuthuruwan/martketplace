{{-- Banners (B10): staff upload an image with a link, country, placement and dates. --}}
<div>
    <x-admin.page-header :title="__('admin.nav.banners')" :count="$banners->total()" :description="__('admin.banners.help')" />

    @if (session('status'))
        <x-ui.alert variant="success" role="status" class="mb-4">{{ session('status') }}</x-ui.alert>
    @endif

    @if ($editing)
        <section aria-labelledby="banner-form" class="mb-6 rounded-card border border-line bg-surface p-4 lg:p-5">
            <h2 id="banner-form" class="mb-3 text-h3 font-semibold">{{ $selectedId ? __('admin.banners.edit_title') : __('admin.banners.add') }}</h2>
            <form wire:submit="save" class="grid gap-3 md:grid-cols-2">
                <x-ui.input :label="__('admin.banners.title')" id="b-title" wire:model="title" maxlength="120" required :hint="__('admin.banners.title_hint')" :error="$errors->first('title')" />
                <x-ui.input type="url" :label="__('admin.banners.link')" id="b-link" wire:model="linkUrl" maxlength="500" :hint="__('admin.banners.link_hint')" :error="$errors->first('linkUrl')" />
                <div class="md:col-span-2">
                    <x-ui.field :label="__('admin.banners.image')" for="b-image" :required="$selectedId === null" :error="$errors->first('image')"
                        :hint="__('admin.banners.image_hint', ['mb' => intdiv((int) config('marketplace.banners.max_upload_kb'), 1024), 'width' => config('marketplace.banners.max_width')])">
                        <input type="file" id="b-image" wire:model="image" accept="image/jpeg,image/png,image/webp" aria-describedby="b-image-hint" @if ($errors->has('image')) aria-invalid="true" @endif
                            class="block w-full text-ui text-fg file:me-3 file:rounded-control file:border file:border-control file:bg-surface-muted file:px-3 file:py-2 file:text-ui file:font-medium file:text-fg">
                    </x-ui.field>
                    @if ($selected)
                        <img src="{{ $selected->imageUrl() }}" alt="" width="{{ $selected->width }}" height="{{ $selected->height }}" class="mt-2 h-auto max-h-32 w-auto max-w-full rounded-control border border-line">
                    @endif
                </div>
                <x-ui.select :label="__('admin.banners.country')" id="b-country" wire:model="countryId" :options="$countries" :error="$errors->first('countryId')" />
                <x-ui.select :label="__('admin.banners.placement')" id="b-placement" wire:model="placement" :options="__('admin.banners.placements')" :error="$errors->first('placement')" />
                <x-ui.input type="date" :label="__('admin.banners.starts')" id="b-starts" wire:model="startsOn" :error="$errors->first('startsOn')" />
                <x-ui.input type="date" :label="__('admin.banners.ends')" id="b-ends" wire:model="endsOn" :error="$errors->first('endsOn')" />
                <div class="md:col-span-2"><x-ui.checkbox type="switch" :label="__('admin.banners.active')" id="b-active" wire:model="active" :checked="$active" /></div>
                <div class="flex flex-wrap gap-2 md:col-span-2">
                    <x-ui.button type="submit" icon="check" wire:loading.attr="disabled" wire:target="image,save">{{ __('admin.banners.save') }}</x-ui.button>
                    <x-ui.button variant="secondary" wire:click="cancel">{{ __('admin.catalogue.cancel') }}</x-ui.button>
                </div>
            </form>
        </section>
    @else
        <div class="mb-4"><x-ui.button icon="plus" wire:click="create">{{ __('admin.banners.add') }}</x-ui.button></div>
    @endif

    @if ($banners->isEmpty())
        <x-ui.empty-state icon="image" :title="__('admin.banners.empty')" />
    @else
        <x-ui.table :caption="__('admin.nav.banners')">
            <thead>
                <tr>
                    <th scope="col">{{ __('admin.banners.title') }}</th>
                    <th scope="col">{{ __('admin.banners.where') }}</th>
                    <th scope="col">{{ __('admin.banners.period') }}</th>
                    <th scope="col">{{ __('admin.banners.state') }}</th>
                    <th scope="col"><span class="sr-only">{{ __('admin.banners.actions') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($banners as $banner)
                    <tr wire:key="banner-{{ $banner->id }}">
                        <td data-label="{{ __('admin.banners.title') }}" class="min-w-0">
                            <span class="prose-user font-medium text-fg">{{ $banner->title }}</span>
                            @if ($banner->link_url)<span class="block truncate text-small text-fg-subtle">{{ $banner->link_url }}</span>@endif
                        </td>
                        <td data-label="{{ __('admin.banners.where') }}" class="text-fg-muted">
                            {{ __('admin.banners.placements.'.$banner->placement->value) }}
                            <span class="block text-small text-fg-subtle">{{ $banner->country?->name ?? __('admin.banners.all_countries') }}</span>
                        </td>
                        <td data-label="{{ __('admin.banners.period') }}" class="tabular text-fg-muted">
                            {{ $banner->starts_at?->toFormattedDateString() ?? __('admin.banners.now') }} – {{ $banner->ends_at?->toFormattedDateString() ?? __('admin.banners.no_end') }}
                        </td>
                        <td data-label="{{ __('admin.banners.state') }}">
                            <x-ui.badge :variant="$banner->state() === 'live' ? 'success' : 'neutral'">{{ __('admin.banners.states.'.$banner->state()) }}</x-ui.badge>
                        </td>
                        <td class="whitespace-nowrap text-right">
                            <x-ui.button size="sm" variant="ghost" icon="pencil" wire:click="edit({{ $banner->id }})" :label="__('admin.banners.edit_named', ['title' => $banner->title])" />
                            <x-ui.button size="sm" variant="ghost" icon="trash-2" wire:click="delete({{ $banner->id }})" wire:confirm="{{ __('admin.banners.confirm_delete') }}" :label="__('admin.banners.delete_named', ['title' => $banner->title])" class="text-danger" />
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-ui.table>
        {{ $banners->links('livewire.admin.pagination') }}
    @endif
</div>
