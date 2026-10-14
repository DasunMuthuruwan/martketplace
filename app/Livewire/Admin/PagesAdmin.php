<?php

namespace App\Livewire\Admin;

use App\Authorization\Permissions;
use App\Livewire\Admin\Concerns\RequiresPermission;
use App\Models\Page;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Static pages (ADM-06, `pages.manage`): write in Markdown, preview the rendered (sanitised) page,
 * publish or unpublish. The standard pages cannot be deleted (the footer and sign-up flow link to
 * them); extra pages can. Every change is audited.
 */
#[Layout('layouts.admin')]
#[Title('Pages')]
class PagesAdmin extends Component
{
    use RequiresPermission;

    #[Locked]
    public ?int $selectedId = null;

    public bool $editing = false;

    public string $slug = '';

    public string $title = '';

    public string $body = '';

    public string $description = '';

    public bool $published = false;

    public bool $preview = false;

    public function mount(): void
    {
        $this->requirePermission(Permissions::PAGES_MANAGE);
    }

    public function create(): void
    {
        $this->requirePermission(Permissions::PAGES_MANAGE);
        $this->resetForm();
        $this->editing = true;
    }

    public function edit(int $id): void
    {
        $this->requirePermission(Permissions::PAGES_MANAGE);
        $page = Page::findOrFail($id);
        $this->resetForm();
        $this->selectedId = $page->id;
        $this->slug = $page->slug;
        $this->title = (string) $page->getTranslation('title', 'en', false);
        $this->body = (string) $page->getTranslation('body', 'en', false);
        $this->description = (string) ($page->meta['description'] ?? '');
        $this->published = $page->is_published;
        $this->editing = true;
    }

    public function togglePreview(): void
    {
        $this->preview = ! $this->preview;
    }

    public function save(AuditLogger $audit): void
    {
        $this->requirePermission(Permissions::PAGES_MANAGE);
        $page = $this->selectedId === null ? new Page : Page::findOrFail($this->selectedId);
        $data = $this->validate([
            'slug' => [$page->exists ? 'nullable' : 'required', 'string', 'max:80', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::unique('pages', 'slug')->ignore($page->id)],
            'title' => ['required', 'string', 'max:120'],
            'body' => [$this->published ? 'required' : 'nullable', 'string', 'max:60000'],
            'description' => ['nullable', 'string', 'max:300'],
        ]);

        $created = ! $page->exists;
        if ($created) {
            $page->slug = $data['slug']; // fixed afterwards: other pages and emails link to it
        }
        $page->setTranslation('title', 'en', trim($data['title']));
        $page->setTranslation('body', 'en', (string) $data['body']);
        $page->meta = blank($data['description']) ? null : ['description' => trim($data['description'])];
        $wasPublished = $page->exists && $page->getOriginal('is_published');
        $page->is_published = $this->published;
        $page->save();

        $action = match (true) {
            $created => 'page.created',
            ! $wasPublished && $page->is_published => 'page.published',
            $wasPublished && ! $page->is_published => 'page.unpublished',
            default => 'page.updated',
        };
        $audit->record(Auth::user(), $action, $page, null, ['slug' => $page->slug, 'published' => $page->is_published]);
        session()->flash('status', __('admin.catalogue.saved', ['name' => $page->title]));
        $this->resetForm();
    }

    public function delete(int $id, AuditLogger $audit): void
    {
        $this->requirePermission(Permissions::PAGES_MANAGE);
        $page = Page::findOrFail($id);
        if (array_key_exists($page->slug, Page::DEFAULTS)) {
            $this->addError('delete', __('admin.pages.standard'));

            return;
        }

        $page->delete();
        $audit->record(Auth::user(), 'page.deleted', null, null, ['slug' => $page->slug]);
        session()->flash('status', __('admin.catalogue.deleted', ['name' => $page->title]));
        $this->resetForm();
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function render(): View
    {
        return view('livewire.admin.pages', [
            'pages' => Page::orderBy('id')->get(),
            'standard' => Page::DEFAULTS,
            'rendered' => $this->preview ? (new Page(['body' => ['en' => $this->body]]))->html() : null,
        ]);
    }

    private function resetForm(): void
    {
        $this->reset(['selectedId', 'editing', 'slug', 'title', 'body', 'description', 'published', 'preview']);
        $this->resetErrorBag();
    }
}
