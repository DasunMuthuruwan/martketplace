<?php

namespace App\Livewire\Admin;

use App\Authorization\Permissions;
use App\Enums\CategoryModeration;
use App\Enums\ListingType;
use App\Enums\PriceMode;
use App\Enums\PriceType;
use App\Exceptions\InvalidCategoryTree;
use App\Livewire\Admin\Concerns\RequiresPermission;
use App\Models\Category;
use App\Services\Audit\AuditLogger;
use App\Support\CountryScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Category manager (ADM-02, CAT-01/05, category tree doc §6): the two-level tree, create and edit
 * (name, slug, parent, icon, order, active, country scope, listing and price types, price mode,
 * free, condition, max photos, moderation override, SEO meta), move up/down and delete. The
 * Category model keeps the tree valid (two levels, no category with listings deleted or turned
 * into a parent); its errors are shown on the form. Every change is audited. English names now;
 * other languages when they launch.
 */
#[Layout('layouts.admin')]
#[Title('Categories')]
class CategoriesAdmin extends Component
{
    use RequiresPermission;

    /** null = creating a new category. */
    #[Locked]
    public ?int $editingId = null;

    public bool $editing = false;

    public string $name = '';

    public string $slug = '';

    public string $parentId = '';

    public string $icon = '';

    public int $sortOrder = 0;

    public bool $isActive = true;

    /** ISO2 codes, comma or space separated; empty = every country. */
    public string $scope = '';

    /** @var list<string> */
    public array $types = ['sale'];

    public string $priceMode = 'required';

    public bool $allowFree = false;

    /** @var list<string> empty = all price types (subject to allow free) */
    public array $priceTypes = [];

    public bool $showCondition = true;

    public int $maxImages = 8;

    public string $moderation = 'inherit';

    public string $metaTitle = '';

    public string $metaDescription = '';

    public function mount(): void
    {
        $this->requirePermission(Permissions::CATEGORIES_MANAGE);
    }

    public function create(?int $parentId = null): void
    {
        $this->requirePermission(Permissions::CATEGORIES_MANAGE);
        $this->resetForm();
        $this->parentId = $parentId === null ? '' : (string) $parentId;
        $this->editing = true;
    }

    public function edit(int $id): void
    {
        $this->requirePermission(Permissions::CATEGORIES_MANAGE);
        $category = Category::findOrFail($id);
        $this->resetForm();

        $this->editingId = $category->id;
        $this->name = (string) $category->getTranslation('name', 'en', false);
        $this->slug = $category->slug;
        $this->parentId = (string) ($category->parent_id ?? '');
        $this->icon = (string) $category->icon;
        $this->sortOrder = $category->sort_order;
        $this->isActive = $category->is_active;
        $this->scope = implode(', ', $category->scope_countries ?? []);
        $this->types = $category->allowed_types;
        $this->priceMode = $category->price_mode->value;
        $this->allowFree = $category->allow_free;
        $this->priceTypes = $category->allowed_price_types ?? [];
        $this->showCondition = $category->show_condition;
        $this->maxImages = $category->max_images;
        $this->moderation = $category->moderation->value;
        $this->metaTitle = (string) $category->getTranslation('meta_title', 'en', false);
        $this->metaDescription = (string) $category->getTranslation('meta_description', 'en', false);
        $this->editing = true;
    }

    public function save(AuditLogger $audit): void
    {
        $this->requirePermission(Permissions::CATEGORIES_MANAGE);
        $data = $this->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', 'not_in:in', Rule::unique('categories', 'slug')->ignore($this->editingId)],
            'parentId' => ['nullable', Rule::exists('categories', 'id')->whereNull('parent_id')],
            'icon' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/'],
            'sortOrder' => ['integer', 'min:0', 'max:9999'],
            'scope' => ['nullable', 'string', 'max:300', 'regex:/^[A-Za-z]{2}([,\s]+[A-Za-z]{2})*$/'],
            'types' => ['required', 'array', 'min:1'],
            'types.*' => [Rule::enum(ListingType::class)],
            'priceMode' => [Rule::enum(PriceMode::class)],
            'priceTypes' => ['array'],
            'priceTypes.*' => [Rule::enum(PriceType::class)],
            'maxImages' => ['integer', 'min:1', 'max:20'],
            'moderation' => [Rule::enum(CategoryModeration::class)],
            'metaTitle' => ['nullable', 'string', 'max:120'],
            'metaDescription' => ['nullable', 'string', 'max:300'],
        ], [
            'slug.not_in' => __('admin.catalogue.slug_reserved'),
        ]);

        $category = $this->editingId === null ? new Category : Category::findOrFail($this->editingId);
        $before = $category->exists ? $category->only(['slug', 'parent_id', 'is_active', 'scope_countries', 'allowed_types']) : null;

        try {
            DB::transaction(function () use ($category, $data, $audit, $before): void {
                $category->fill([
                    'parent_id' => $data['parentId'] === '' || $data['parentId'] === null ? null : (int) $data['parentId'],
                    'slug' => $data['slug'] === '' ? null : $data['slug'],
                    'icon' => $data['icon'] === '' ? null : $data['icon'],
                    'sort_order' => $data['sortOrder'],
                    'is_active' => $this->isActive,
                    'scope_countries' => CountryScope::normalise(preg_split('/[,\s]+/', (string) $data['scope'], -1, PREG_SPLIT_NO_EMPTY) ?: []),
                    'allowed_types' => $data['types'],
                    'price_mode' => $data['priceMode'],
                    'allow_free' => $this->allowFree,
                    'allowed_price_types' => $data['priceTypes'] === [] ? null : $data['priceTypes'],
                    'show_condition' => $this->showCondition,
                    'max_images' => $data['maxImages'],
                    'moderation' => $data['moderation'],
                ]);
                $category->setTranslation('name', 'en', trim($data['name']));
                $category->setTranslation('meta_title', 'en', (string) $data['metaTitle']);
                $category->setTranslation('meta_description', 'en', (string) $data['metaDescription']);
                $category->save();

                $audit->record(Auth::user(), $before === null ? 'category.created' : 'category.updated', $category, null, [
                    'before' => $before,
                    'after' => $category->only(['slug', 'parent_id', 'is_active', 'scope_countries', 'allowed_types']),
                ]);
            });
        } catch (InvalidCategoryTree $e) {
            $this->addError('parentId', $e->getMessage());

            return;
        }

        session()->flash('status', __('admin.catalogue.saved', ['name' => $category->name]));
        $this->resetForm();
    }

    /** Swap the order with the neighbour above or below (same parent). */
    public function move(int $id, string $direction): void
    {
        $this->requirePermission(Permissions::CATEGORIES_MANAGE);
        $category = Category::findOrFail($id);
        $siblings = Category::where('parent_id', $category->parent_id)->orderBy('sort_order')->orderBy('id')->get()->values();
        $index = $siblings->search(fn (Category $c) => $c->is($category));
        $other = $siblings->get($direction === 'up' ? $index - 1 : $index + 1);
        if ($other === null) {
            return;
        }

        DB::transaction(function () use ($siblings, $index, $other): void {
            // Renumber so equal sort values cannot block the swap.
            $order = $siblings->pluck('id')->all();
            $otherIndex = array_search($other->id, $order, true);
            [$order[$index], $order[$otherIndex]] = [$order[$otherIndex], $order[$index]];
            foreach ($order as $position => $categoryId) {
                Category::whereKey($categoryId)->first()?->update(['sort_order' => $position + 1]);
            }
        });
    }

    public function delete(int $id, AuditLogger $audit): void
    {
        $this->requirePermission(Permissions::CATEGORIES_MANAGE);
        $category = Category::findOrFail($id);

        try {
            $category->delete();
        } catch (InvalidCategoryTree $e) {
            $this->addError('delete', $e->getMessage());

            return;
        }
        $audit->record(Auth::user(), 'category.deleted', null, null, ['slug' => $category->slug, 'id' => $category->id]);
        session()->flash('status', __('admin.catalogue.deleted', ['name' => $category->name]));
        if ($this->editingId === $id) {
            $this->resetForm();
        }
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function render(): View
    {
        return view('livewire.admin.categories', [
            'roots' => Category::roots()->with('children')->orderBy('sort_order')->orderBy('id')->get(),
            'listingTypes' => ListingType::cases(),
            'priceModes' => PriceMode::cases(),
            'allPriceTypes' => PriceType::cases(),
            'moderations' => CategoryModeration::cases(),
        ]);
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'editing', 'name', 'slug', 'parentId', 'icon', 'sortOrder', 'isActive', 'scope', 'types', 'priceMode',
            'allowFree', 'priceTypes', 'showCondition', 'maxImages', 'moderation', 'metaTitle', 'metaDescription']);
        $this->resetErrorBag();
    }
}
