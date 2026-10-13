<?php

namespace App\Livewire\Admin;

use App\Authorization\Permissions;
use App\Enums\AttributeType;
use App\Enums\ItemCondition;
use App\Livewire\Admin\Concerns\RequiresPermission;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\ListingAttributeValue;
use App\Models\MeasurementUnit;
use App\Services\Audit\AuditLogger;
use App\Support\CountryScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Attribute manager (ADM-02, CAT-02/03/04/06): attribute fields, their options (a dependent
 * select's options each belong to an option of the attribute it depends on: Model → Make) and the
 * categories that use them (required, required only for some conditions, filterable, on the card,
 * group, order, country scope). Anything already used by ads can only be switched off: the type
 * is locked and used options cannot be deleted. The models enforce the same rules; their errors are
 * shown on the form. Every change is audited.
 */
#[Layout('layouts.admin')]
#[Title('Attributes')]
class AttributesAdmin extends Component
{
    use RequiresPermission, WithPagination;

    private const OPTIONS_PER_PAGE = 50;

    public string $q = '';

    /** The attribute being edited; null while creating one or with nothing open. */
    #[Locked]
    public ?int $selectedId = null;

    public bool $editing = false;

    // ---- Attribute form ---------------------------------------------------------------------

    public string $key = '';

    public string $label = '';

    public string $type = 'select';

    public string $unit = '';

    public string $dimension = '';

    public string $ruleMin = '';

    public string $ruleMax = '';

    public string $ruleStep = '';

    public string $ruleMaxlength = '';

    public string $ruleYearsAhead = '';

    /** Area dimension: allowed unit codes, comma separated; empty = every unit the country offers. */
    public string $ruleUnits = '';

    public bool $allowsOther = false;

    public string $helpText = '';

    public bool $isActive = true;

    public string $dependsOn = '';

    // ---- Option form ------------------------------------------------------------------------

    #[Locked]
    public ?int $optionId = null;

    public string $optionLabel = '';

    public string $optionValue = '';

    public string $optionParent = '';

    public string $optionScope = '';

    public string $optionSearch = '';

    public string $optionParentFilter = '';

    // ---- Category link form -----------------------------------------------------------------

    public string $linkCategory = '';

    public bool $linkRequired = false;

    /** @var list<string> */
    public array $linkConditions = [];

    public bool $linkFilterable = false;

    public bool $linkCard = false;

    public string $linkGroup = '';

    public int $linkSort = 0;

    public string $linkScope = '';

    public function mount(): void
    {
        $this->requirePermission(Permissions::ATTRIBUTES_MANAGE);
    }

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function updatedOptionSearch(): void
    {
        $this->resetPage('opage');
    }

    public function updatedOptionParentFilter(): void
    {
        $this->resetPage('opage');
    }

    // ---- Attributes -------------------------------------------------------------------------

    public function create(): void
    {
        $this->requirePermission(Permissions::ATTRIBUTES_MANAGE);
        $this->resetAll();
        $this->editing = true;
    }

    public function select(int $id): void
    {
        $this->requirePermission(Permissions::ATTRIBUTES_MANAGE);
        $attribute = Attribute::findOrFail($id);
        $this->resetAll();

        $rules = $attribute->rules ?? [];
        $this->selectedId = $attribute->id;
        $this->key = $attribute->key;
        $this->label = (string) $attribute->getTranslation('label', 'en', false);
        $this->type = $attribute->type->value;
        $this->unit = (string) $attribute->unit;
        $this->dimension = (string) $attribute->dimension;
        $this->ruleMin = (string) ($rules['min'] ?? '');
        $this->ruleMax = (string) ($rules['max'] ?? '');
        $this->ruleStep = (string) ($rules['step'] ?? '');
        $this->ruleMaxlength = (string) ($rules['maxlength'] ?? '');
        $this->ruleYearsAhead = (string) ($rules['max_years_ahead'] ?? '');
        $this->ruleUnits = implode(', ', $rules['units'] ?? []);
        $this->allowsOther = $attribute->allows_other;
        $this->helpText = (string) $attribute->getTranslation('help_text', 'en', false);
        $this->isActive = $attribute->is_active;
        $this->dependsOn = (string) ($attribute->depends_on_attribute_id ?? '');
        $this->editing = true;
    }

    public function save(AuditLogger $audit): void
    {
        $this->requirePermission(Permissions::ATTRIBUTES_MANAGE);
        $attribute = $this->selectedId === null ? new Attribute : Attribute::findOrFail($this->selectedId);
        $inUse = $attribute->exists && $this->inUse($attribute);
        $hasOptions = $attribute->exists && $attribute->options()->exists();

        $data = $this->validate([
            'key' => $attribute->exists ? [] : ['required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('attributes', 'key')],
            'label' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::enum(AttributeType::class)],
            'unit' => ['nullable', 'string', 'max:20'],
            'dimension' => ['nullable', Rule::in([MeasurementUnit::AREA])],
            'ruleMin' => ['nullable', 'numeric'],
            'ruleMax' => ['nullable', 'numeric'],
            'ruleStep' => ['nullable', 'numeric', 'gt:0'],
            'ruleMaxlength' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'ruleYearsAhead' => ['nullable', 'integer', 'min:0', 'max:5'],
            'ruleUnits' => ['nullable', 'string', 'max:200', 'regex:/^[a-z0-9_]+([,\s]+[a-z0-9_]+)*$/'],
            'helpText' => ['nullable', 'string', 'max:200'],
            'dependsOn' => ['nullable', Rule::exists('attributes', 'id')->where('type', AttributeType::Select->value)],
        ]);

        if (! blank($data['ruleMin']) && ! blank($data['ruleMax']) && $data['ruleMax'] < $data['ruleMin']) {
            $this->addError('ruleMax', __('admin.catalogue.max_below_min'));

            return;
        }
        // Ads store values by type, and options by parent: changing either would orphan them.
        if ($inUse && $data['type'] !== $attribute->type->value) {
            $this->addError('type', __('admin.catalogue.type_locked'));

            return;
        }
        $dependsOn = blank($data['dependsOn']) ? null : (int) $data['dependsOn'];
        if ($hasOptions && $dependsOn !== $attribute->depends_on_attribute_id) {
            $this->addError('dependsOn', __('admin.catalogue.depends_locked'));

            return;
        }

        $before = $attribute->exists ? $this->snapshot($attribute) : null;
        $rules = array_filter([
            'min' => $this->number($data['ruleMin']),
            'max' => $this->number($data['ruleMax']),
            'step' => $this->number($data['ruleStep']),
            'maxlength' => blank($data['ruleMaxlength']) ? null : (int) $data['ruleMaxlength'],
            'max_years_ahead' => blank($data['ruleYearsAhead']) ? null : (int) $data['ruleYearsAhead'],
            'units' => $this->codes((string) $data['ruleUnits'], lower: true),
        ], fn ($value) => $value !== null);

        try {
            DB::transaction(function () use ($attribute, $data, $rules, $dependsOn, $audit, $before): void {
                if (! $attribute->exists) {
                    $attribute->key = $data['key'];
                }
                $attribute->fill([
                    'type' => $data['type'],
                    'unit' => blank($data['unit']) ? null : trim($data['unit']),
                    'dimension' => blank($data['dimension']) ? null : $data['dimension'],
                    'rules' => $rules === [] ? null : $rules,
                    'depends_on_attribute_id' => $dependsOn,
                    'allows_other' => $this->allowsOther,
                    'is_active' => $this->isActive,
                ]);
                $attribute->setTranslation('label', 'en', trim($data['label']));
                $attribute->setTranslation('help_text', 'en', trim((string) $data['helpText']));
                $attribute->save();

                $audit->record(Auth::user(), $before === null ? 'attribute.created' : 'attribute.updated', $attribute, null, [
                    'before' => $before,
                    'after' => $this->snapshot($attribute),
                ]);
            });
        } catch (InvalidArgumentException $e) {
            $this->addError('form', $e->getMessage());

            return;
        }

        session()->flash('status', __('admin.catalogue.saved', ['name' => $attribute->label]));
        $this->select($attribute->id);
    }

    public function deleteAttribute(AuditLogger $audit): void
    {
        $this->requirePermission(Permissions::ATTRIBUTES_MANAGE);
        $attribute = Attribute::findOrFail($this->selectedId);
        if ($attribute->categories()->exists() || Attribute::where('depends_on_attribute_id', $attribute->id)->exists()) {
            $this->addError('form', __('admin.catalogue.attribute_linked'));

            return;
        }

        try {
            DB::transaction(function () use ($attribute): void {
                // Child options first: their parent link restricts deletes.
                $attribute->options()->whereNotNull('parent_option_id')->get()->each->delete();
                $attribute->options()->get()->each->delete();
                $attribute->delete();
            });
        } catch (InvalidArgumentException $e) {
            $this->addError('form', $e->getMessage());

            return;
        }

        $audit->record(Auth::user(), 'attribute.deleted', null, null, ['key' => $attribute->key, 'id' => $attribute->id]);
        session()->flash('status', __('admin.catalogue.deleted', ['name' => $attribute->label]));
        $this->resetAll();
    }

    public function cancel(): void
    {
        $this->resetAll();
    }

    // ---- Options ----------------------------------------------------------------------------

    public function editOption(int $id): void
    {
        $this->requirePermission(Permissions::ATTRIBUTES_MANAGE);
        $option = $this->option($id);
        $this->optionId = $option->id;
        $this->optionLabel = (string) $option->getTranslation('label', 'en', false);
        $this->optionValue = $option->value;
        $this->optionParent = (string) ($option->parent_option_id ?? '');
        $this->optionScope = implode(', ', $option->scope_countries ?? []);
        $this->resetErrorBag();
    }

    public function saveOption(AuditLogger $audit): void
    {
        $this->requirePermission(Permissions::ATTRIBUTES_MANAGE);
        $attribute = Attribute::findOrFail($this->selectedId);
        $option = $this->optionId === null ? new AttributeOption(['attribute_id' => $attribute->id]) : $this->option($this->optionId);
        $inUse = $option->exists && ListingAttributeValue::where('value_option_id', $option->id)->exists();

        $data = $this->validate([
            'optionLabel' => ['required', 'string', 'max:80'],
            'optionValue' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/'],
            'optionParent' => $attribute->depends_on_attribute_id === null
                ? ['nullable', 'prohibited']
                : ['required', Rule::exists('attribute_options', 'id')->where('attribute_id', $attribute->depends_on_attribute_id)],
            'optionScope' => ['nullable', 'string', 'max:300', 'regex:/^[A-Za-z]{2}([,\s]+[A-Za-z]{2})*$/'],
        ]);
        if (! $attribute->type->hasOptions()) {
            $this->addError('optionLabel', __('admin.catalogue.no_options'));

            return;
        }

        $parentId = blank($data['optionParent']) ? null : (int) $data['optionParent'];
        $value = blank($data['optionValue']) ? null : $data['optionValue'];
        // A used option keeps its value and parent: filter URLs and stored ads point at them.
        if ($inUse && (($value !== null && $value !== $option->value) || $parentId !== $option->parent_option_id)) {
            $this->addError('optionValue', __('admin.catalogue.option_locked'));

            return;
        }
        $duplicate = AttributeOption::where('attribute_id', $attribute->id)
            ->where('parent_option_id', $parentId)
            ->where('value', $value ?? Str::slug($data['optionLabel']))
            ->when($option->exists, fn ($q) => $q->whereKeyNot($option->id))
            ->exists();
        if ($duplicate) {
            $this->addError('optionValue', __('admin.catalogue.option_exists'));

            return;
        }

        $created = ! $option->exists;
        try {
            $option->fill([
                'parent_option_id' => $parentId,
                'scope_countries' => $this->codes((string) $data['optionScope']),
            ]);
            if ($value !== null || $created) {
                $option->value = $value ?? ''; // empty: the model derives it from the label
            }
            $option->setTranslation('label', 'en', trim($data['optionLabel']));
            if ($created) {
                $option->sort_order = (int) AttributeOption::where('attribute_id', $attribute->id)->max('sort_order') + 1;
            }
            $option->save();
        } catch (InvalidArgumentException $e) {
            $this->addError('optionLabel', $e->getMessage());

            return;
        }

        $audit->record(Auth::user(), $created ? 'attribute_option.created' : 'attribute_option.updated', $attribute, null, [
            'option_id' => $option->id, 'value' => $option->value, 'parent_option_id' => $option->parent_option_id,
        ]);
        session()->flash('status', __('admin.catalogue.saved', ['name' => $option->label]));
        $this->resetOptionForm();
    }

    public function toggleOption(int $id, AuditLogger $audit): void
    {
        $this->requirePermission(Permissions::ATTRIBUTES_MANAGE);
        $option = $this->option($id);
        $option->update(['is_active' => ! $option->is_active]);
        $audit->record(Auth::user(), $option->is_active ? 'attribute_option.activated' : 'attribute_option.deactivated', $option->attribute, null, [
            'option_id' => $option->id, 'value' => $option->value,
        ]);
    }

    public function deleteOption(int $id, AuditLogger $audit): void
    {
        $this->requirePermission(Permissions::ATTRIBUTES_MANAGE);
        $option = $this->option($id);
        if (AttributeOption::where('parent_option_id', $option->id)->exists()) {
            $this->addError('options', __('admin.catalogue.option_has_children'));

            return;
        }

        try {
            $option->delete();
        } catch (InvalidArgumentException) {
            $this->addError('options', __('admin.catalogue.option_in_use'));

            return;
        }
        $audit->record(Auth::user(), 'attribute_option.deleted', $option->attribute, null, ['option_id' => $option->id, 'value' => $option->value]);
        if ($this->optionId === $id) {
            $this->resetOptionForm();
        }
    }

    public function cancelOption(): void
    {
        $this->resetOptionForm();
    }

    // ---- Category links ---------------------------------------------------------------------

    public function editLink(int $categoryId): void
    {
        $this->requirePermission(Permissions::ATTRIBUTES_MANAGE);
        $category = Attribute::findOrFail($this->selectedId)->categories()->whereKey($categoryId)->firstOrFail();
        /** @var CategoryAttribute $pivot */
        $pivot = $category->getRelation('pivot');

        $this->linkCategory = (string) $category->id;
        $this->linkRequired = (bool) $pivot->is_required;
        $this->linkConditions = $pivot->required_for_conditions ?? [];
        $this->linkFilterable = (bool) $pivot->is_filterable;
        $this->linkCard = (bool) $pivot->show_on_card;
        $this->linkGroup = (string) $pivot->group;
        $this->linkSort = (int) $pivot->sort_order;
        $this->linkScope = implode(', ', $pivot->scope_countries ?? []);
        $this->resetErrorBag();
    }

    public function saveLink(AuditLogger $audit): void
    {
        $this->requirePermission(Permissions::ATTRIBUTES_MANAGE);
        $attribute = Attribute::findOrFail($this->selectedId);
        $data = $this->validate([
            'linkCategory' => ['required', Rule::exists('categories', 'id')],
            'linkConditions' => ['array'],
            'linkConditions.*' => [Rule::enum(ItemCondition::class)],
            'linkGroup' => ['nullable', 'string', 'max:40'],
            'linkSort' => ['integer', 'min:0', 'max:9999'],
            'linkScope' => ['nullable', 'string', 'max:300', 'regex:/^[A-Za-z]{2}([,\s]+[A-Za-z]{2})*$/'],
        ]);

        $pivot = [
            'is_required' => $this->linkRequired,
            'required_for_conditions' => $this->linkRequired && $data['linkConditions'] !== [] ? array_values($data['linkConditions']) : null,
            'is_filterable' => $this->linkFilterable,
            'show_on_card' => $this->linkCard,
            'group' => blank($data['linkGroup']) ? null : trim($data['linkGroup']),
            'sort_order' => $data['linkSort'],
            'scope_countries' => $this->codes((string) $data['linkScope']),
        ];
        $attribute->categories()->syncWithoutDetaching([(int) $data['linkCategory'] => $pivot]);

        $audit->record(Auth::user(), 'attribute.linked', $attribute, null, ['category_id' => (int) $data['linkCategory']] + $pivot);
        session()->flash('status', __('admin.catalogue.link_saved'));
        $this->resetLinkForm();
    }

    public function detach(int $categoryId, AuditLogger $audit): void
    {
        $this->requirePermission(Permissions::ATTRIBUTES_MANAGE);
        $attribute = Attribute::findOrFail($this->selectedId);
        // Values already saved on ads stay in the database; the field just stops showing.
        $attribute->categories()->detach($categoryId);
        $audit->record(Auth::user(), 'attribute.unlinked', $attribute, null, ['category_id' => $categoryId]);
        session()->flash('status', __('admin.catalogue.link_removed'));
        $this->resetLinkForm();
    }

    public function cancelLink(): void
    {
        $this->resetLinkForm();
    }

    // ---- View -------------------------------------------------------------------------------

    public function render(): View
    {
        $selected = $this->selectedId === null ? null : Attribute::with(['dependsOn', 'categories.parent'])->find($this->selectedId);

        return view('livewire.admin.attributes', [
            'attributeList' => Attribute::query()
                ->when($this->q !== '', fn ($query) => $query->where(fn ($w) => $w
                    ->where('key', 'like', '%'.addcslashes($this->q, '%_\\').'%')
                    ->orWhere('label', 'like', '%'.addcslashes($this->q, '%_\\').'%')))
                ->withCount('options')
                ->orderBy('key')
                ->paginate(30),
            'selected' => $selected,
            'inUse' => $selected !== null && $this->inUse($selected),
            'options' => $selected !== null && $selected->type->hasOptions() ? $this->options($selected) : null,
            'parentOptions' => $selected?->depends_on_attribute_id === null ? collect() : AttributeOption::where('attribute_id', $selected->depends_on_attribute_id)->orderBy('sort_order')->orderBy('id')->get(),
            'selectAttributes' => Attribute::where('type', AttributeType::Select->value)->whereKeyNot($this->selectedId ?? 0)->orderBy('key')->get(),
            'categories' => Category::with('parent')->orderBy('parent_id')->orderBy('sort_order')->get()
                ->sortBy(fn (Category $c) => ($c->parent->name ?? '').' '.$c->name),
            'types' => AttributeType::cases(),
            'conditions' => ItemCondition::cases(),
        ]);
    }

    /** @return LengthAwarePaginator<int, AttributeOption> */
    private function options(Attribute $attribute): LengthAwarePaginator
    {
        $search = '%'.addcslashes($this->optionSearch, '%_\\').'%';

        return AttributeOption::where('attribute_id', $attribute->id)
            ->with('parentOption')
            ->when($this->optionParentFilter !== '', fn ($q) => $q->where('parent_option_id', (int) $this->optionParentFilter))
            ->when($this->optionSearch !== '', fn ($q) => $q->where(fn ($w) => $w->where('value', 'like', $search)->orWhere('label', 'like', $search)))
            ->orderBy('sort_order')->orderBy('id')
            ->paginate(self::OPTIONS_PER_PAGE, pageName: 'opage');
    }

    /** An option of the selected attribute (never another attribute's, whatever id is sent). */
    private function option(int $id): AttributeOption
    {
        return AttributeOption::where('attribute_id', $this->selectedId ?? 0)->findOrFail($id);
    }

    private function inUse(Attribute $attribute): bool
    {
        return ListingAttributeValue::where('attribute_id', $attribute->id)->exists();
    }

    /** @return array<string, mixed> */
    private function snapshot(Attribute $attribute): array
    {
        return $attribute->only(['key', 'type', 'unit', 'dimension', 'rules', 'depends_on_attribute_id', 'allows_other', 'is_active']);
    }

    private function number(mixed $value): int|float|null
    {
        return blank($value) ? null : $value + 0;
    }

    /** @return list<string>|null */
    private function codes(string $list, bool $lower = false): ?array
    {
        $codes = preg_split('/[,\s]+/', $list, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($lower) {
            return $codes === [] ? null : array_values(array_unique($codes));
        }

        return CountryScope::normalise($codes);
    }

    private function resetAll(): void
    {
        $this->reset(['selectedId', 'editing', 'key', 'label', 'type', 'unit', 'dimension', 'ruleMin', 'ruleMax', 'ruleStep', 'ruleMaxlength',
            'ruleYearsAhead', 'ruleUnits', 'allowsOther', 'helpText', 'isActive', 'dependsOn', 'optionSearch', 'optionParentFilter']);
        $this->resetOptionForm();
        $this->resetLinkForm();
        $this->resetPage('opage');
    }

    private function resetOptionForm(): void
    {
        $this->reset(['optionId', 'optionLabel', 'optionValue', 'optionParent', 'optionScope']);
        $this->resetErrorBag();
    }

    private function resetLinkForm(): void
    {
        $this->reset(['linkCategory', 'linkRequired', 'linkConditions', 'linkFilterable', 'linkCard', 'linkGroup', 'linkSort', 'linkScope']);
        $this->resetErrorBag();
    }
}
