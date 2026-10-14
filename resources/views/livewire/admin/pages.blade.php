<div>
    <h1>{{ __('admin.nav.pages') }}</h1>

    @if (session('status'))
        <p class="status" role="status">{{ session('status') }}</p>
    @endif
    @error('delete')<p class="error" role="alert">{{ $message }}</p>@enderror

    <p><button type="button" wire:click="create">{{ __('admin.pages.new') }}</button></p>

    <table>
        <thead><tr><th>{{ __('admin.pages.title') }}</th><th>{{ __('admin.catalogue.slug') }}</th><th>{{ __('admin.reports.status') }}</th><th></th></tr></thead>
        <tbody>
            @foreach ($pages as $page)
                <tr wire:key="page-{{ $page->id }}">
                    <td>{{ $page->title }}</td>
                    <td><code>/page/{{ $page->slug }}</code></td>
                    <td>{{ $page->is_published ? __('admin.pages.published') : __('admin.pages.draft') }}</td>
                    <td>
                        <button type="button" wire:click="edit({{ $page->id }})">{{ __('admin.catalogue.edit') }}</button>
                        @if ($page->is_published)<a href="{{ route('page.show', $page->slug) }}">{{ __('admin.pages.view') }}</a>@endif
                        @unless (array_key_exists($page->slug, $standard))
                            <button type="button" wire:click="delete({{ $page->id }})" wire:confirm="{{ __('admin.catalogue.confirm_delete') }}">{{ __('admin.catalogue.delete') }}</button>
                        @endunless
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($editing)
        <section class="pane" aria-labelledby="page-form">
            <h2 id="page-form">{{ $selectedId ? $title : __('admin.pages.new') }}</h2>
            <form wire:submit="save">
                @unless ($selectedId)
                    <label for="p-slug">{{ __('admin.catalogue.slug') }}</label>
                    <input id="p-slug" type="text" wire:model="slug" maxlength="80" required>
                    <p class="muted">{{ __('admin.pages.slug_help') }}</p>
                    @error('slug')<p class="error">{{ $message }}</p>@enderror
                @endunless

                <label for="p-title">{{ __('admin.pages.title') }}</label>
                <input id="p-title" type="text" wire:model="title" maxlength="120" required>
                @error('title')<p class="error">{{ $message }}</p>@enderror

                <label for="p-body">{{ __('admin.pages.body') }}</label>
                <textarea id="p-body" wire:model="body" rows="18" maxlength="60000"></textarea>
                <p class="muted">{{ __('admin.pages.body_help') }}</p>
                @error('body')<p class="error">{{ $message }}</p>@enderror

                <label for="p-description">{{ __('admin.catalogue.meta_description') }}</label>
                <textarea id="p-description" wire:model="description" rows="2" maxlength="300"></textarea>

                <label><input type="checkbox" wire:model="published"> {{ __('admin.pages.publish') }}</label>

                <p>
                    <button type="submit">{{ __('staff.manage.save') }}</button>
                    <button type="button" wire:click="togglePreview">{{ $preview ? __('admin.pages.hide_preview') : __('admin.pages.preview') }}</button>
                    <button type="button" wire:click="cancel">{{ __('admin.catalogue.cancel') }}</button>
                </p>
            </form>
            @if ($rendered)
                <article class="pane" aria-label="{{ __('admin.pages.preview') }}">{{ $rendered }}</article>
            @endif
        </section>
    @endif
</div>
