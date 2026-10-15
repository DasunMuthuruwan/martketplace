{{-- Pages (ADM-06): help and legal pages in Markdown. The list, or the editor with a live preview beside
     it on wide screens. Standard pages (seeded) cannot be deleted. --}}
<div>
    @if (! $editing)
        <x-admin.page-header :title="__('admin.nav.pages')" :count="$pages->count()" :description="__('admin.pages.intro')">
            <x-ui.button size="sm" icon="plus" wire:click="create">{{ __('admin.pages.new') }}</x-ui.button>
        </x-admin.page-header>

        @if (session('status'))
            <x-ui.alert variant="success" role="status" class="mb-4">{{ session('status') }}</x-ui.alert>
        @endif
        @error('delete')<x-ui.alert variant="danger" role="alert" class="mb-4">{{ $message }}</x-ui.alert>@enderror

        <x-ui.table :caption="__('admin.nav.pages')">
            <thead>
                <tr>
                    <th scope="col">{{ __('admin.pages.title') }}</th>
                    <th scope="col">{{ __('admin.pages.address') }}</th>
                    <th scope="col">{{ __('admin.reports.status') }}</th>
                    <th scope="col">{{ __('admin.pages.updated') }}</th>
                    <th scope="col"><span class="sr-only">{{ __('admin.staff.actions') }}</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pages as $page)
                    <tr wire:key="page-{{ $page->id }}">
                        <td data-label="{{ __('admin.pages.title') }}">
                            <div>
                                <span class="font-medium text-fg">{{ $page->title }}</span>
                                @if (array_key_exists($page->slug, $standard))<x-ui.badge variant="outline" class="ml-1">{{ __('admin.pages.standard_badge') }}</x-ui.badge>@endif
                            </div>
                        </td>
                        <td data-label="{{ __('admin.pages.address') }}"><code class="font-mono text-micro text-fg-muted">/page/{{ $page->slug }}</code></td>
                        <td data-label="{{ __('admin.reports.status') }}">
                            <x-ui.badge :variant="$page->is_published ? 'success' : 'warning'">{{ $page->is_published ? __('admin.pages.published') : __('admin.pages.draft') }}</x-ui.badge>
                        </td>
                        <td data-label="{{ __('admin.pages.updated') }}" class="whitespace-nowrap text-fg-muted">{{ $page->updated_at?->diffForHumans() }}</td>
                        <td class="text-right">
                            <div class="inline-flex items-center gap-0.5">
                                @if ($page->is_published)
                                    <x-ui.button size="sm" variant="ghost" icon="external-link" :href="route('page.show', $page->slug)" target="_blank" rel="noopener" :label="__('admin.pages.view').': '.$page->title" />
                                @endif
                                <x-ui.button size="sm" variant="secondary" icon="pencil" wire:click="edit({{ $page->id }})">{{ __('admin.catalogue.edit') }}</x-ui.button>
                                @unless (array_key_exists($page->slug, $standard))
                                    <x-ui.button size="sm" variant="ghost" icon="trash-2" wire:click="delete({{ $page->id }})" wire:confirm="{{ __('admin.catalogue.confirm_delete') }}"
                                        :label="__('admin.catalogue.delete_named', ['name' => $page->title])" />
                                @endunless
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-ui.table>
    @else
        <x-ui.button variant="ghost" size="sm" icon="chevron-left" wire:click="cancel" class="mb-2">{{ __('admin.pages.back') }}</x-ui.button>
        <x-admin.page-header :title="$selectedId ? $title : __('admin.pages.new')" :description="__('admin.pages.body_help')" />

        <div @class(['grid items-start gap-4', 'xl:grid-cols-2' => $rendered])>
            <form wire:submit="save" class="space-y-4 rounded-card border border-line bg-surface p-4 lg:p-5">
                <div @class(['grid gap-3', 'sm:grid-cols-2' => ! $selectedId])>
                    <x-ui.input :label="__('admin.pages.title')" id="p-title" wire:model="title" maxlength="120" required :error="$errors->first('title')" />
                    @unless ($selectedId)
                        <x-ui.input :label="__('admin.catalogue.slug')" id="p-slug" wire:model="slug" maxlength="80" required prefix="/page/" :hint="__('admin.pages.slug_help')" :error="$errors->first('slug')" />
                    @endunless
                </div>
                <x-ui.textarea :label="__('admin.pages.body')" id="p-body" wire:model="body" rows="18" class="font-mono text-small" :error="$errors->first('body')">{{ $body }}</x-ui.textarea>
                <x-ui.textarea :label="__('admin.catalogue.meta_description')" id="p-description" wire:model="description" rows="2" :maxlength="300">{{ $description }}</x-ui.textarea>
                <x-ui.checkbox type="switch" :label="__('admin.pages.publish')" id="p-published" wire:model="published" :checked="$published" />
                <div class="flex flex-wrap gap-2 border-t border-line pt-4">
                    <x-ui.button type="submit" icon="check">{{ __('staff.manage.save') }}</x-ui.button>
                    <x-ui.button variant="secondary" :icon="$preview ? 'eye-off' : 'eye'" wire:click="togglePreview">{{ $preview ? __('admin.pages.hide_preview') : __('admin.pages.preview') }}</x-ui.button>
                    <x-ui.button variant="ghost" wire:click="cancel">{{ __('admin.catalogue.cancel') }}</x-ui.button>
                </div>
            </form>
            @if ($rendered)
                <section aria-label="{{ __('admin.pages.preview') }}" class="rounded-card border border-dashed border-line bg-surface p-4 lg:p-6 xl:sticky xl:top-20">
                    <p class="mb-4 text-micro font-medium uppercase tracking-wide text-fg-subtle">{{ __('admin.pages.preview') }}</p>
                    <h1 class="mb-4 text-h1 font-semibold">{{ $title }}</h1>
                    <div class="page-body">{{ $rendered }}</div>
                </section>
            @endif
        </div>
    @endif
</div>
