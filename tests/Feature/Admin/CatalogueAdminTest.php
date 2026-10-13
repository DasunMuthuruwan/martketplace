<?php

namespace Tests\Feature\Admin;

use App\Enums\AttributeType;
use App\Enums\StaffRole;
use App\Livewire\Admin\AttributesAdmin;
use App\Livewire\Admin\CategoriesAdmin;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;
use Tests\TestCase;

/** T6.3: catalogue admin (ADM-02, CAT-01..06). */
class CatalogueAdminTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->admin = $this->staff(StaffRole::Admin);
    }

    private function categories(?User $actor = null): Testable
    {
        return Livewire::actingAs($actor ?? $this->admin)->test(CategoriesAdmin::class);
    }

    private function attributes(?User $actor = null): Testable
    {
        return Livewire::actingAs($actor ?? $this->admin)->test(AttributesAdmin::class);
    }

    // ---- Access -----------------------------------------------------------------------------

    public function test_pages_need_the_catalogue_permissions(): void
    {
        $this->actingAs($this->admin)->get(route('admin.categories'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.attributes'))->assertOk();

        $moderator = $this->staff(StaffRole::Moderator, twoFactor: false);
        $this->actingAs($moderator)->get(route('admin.categories'))->assertForbidden();
        $this->actingAs($moderator)->get(route('admin.attributes'))->assertForbidden();
        Livewire::actingAs($moderator)->test(CategoriesAdmin::class)->assertForbidden();
        Livewire::actingAs(User::factory()->create())->test(AttributesAdmin::class)->assertForbidden();
    }

    public function test_actions_check_the_permission_when_called_directly(): void
    {
        $category = Category::factory()->create();
        $component = $this->categories();
        $this->admin->syncRoles([]);
        $this->admin->forgetCachedPermissions();

        $component->call('delete', $category->id)->assertForbidden();
        $this->assertModelExists($category);
    }

    // ---- Categories -------------------------------------------------------------------------

    public function test_create_a_category_and_subcategory_with_settings_and_audit(): void
    {
        $this->categories()->call('create')
            ->set('name', 'Boats')->set('scope', 'qm, QN')->set('types', ['sale', 'rent'])
            ->set('priceMode', 'optional')->set('priceTypes', ['fixed', 'negotiable'])->set('maxImages', 12)
            ->set('moderation', 'always')->set('metaTitle', 'Boats for sale')
            ->call('save')->assertHasNoErrors();

        $boats = Category::where('slug', 'boats')->firstOrFail();
        $this->assertNull($boats->parent_id);
        $this->assertSame(['QM', 'QN'], $boats->scope_countries);
        $this->assertSame(['sale', 'rent'], $boats->allowed_types);
        $this->assertSame(['fixed', 'negotiable'], $boats->allowed_price_types);
        $this->assertSame(12, $boats->max_images);
        $this->assertSame('Boats for sale', $boats->getTranslation('meta_title', 'en'));
        $this->assertTrue(AuditLog::where('action', 'category.created')->where('subject_id', $boats->id)->exists());

        $this->categories()->call('create', $boats->id)->set('name', 'Jet Skis')->call('save')->assertHasNoErrors();
        $this->assertSame($boats->id, Category::where('slug', 'jet-skis')->value('parent_id'));
    }

    public function test_validation_and_tree_rules_are_shown_on_the_form(): void
    {
        $root = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $root->id]);

        $this->categories()->call('create')->set('name', '')->set('slug', 'Bad Slug')->set('types', [])->set('scope', 'LKA')
            ->call('save')->assertHasErrors(['name', 'slug', 'types', 'scope']);
        $this->categories()->call('create')->set('name', 'X')->set('slug', 'in')->call('save')->assertHasErrors('slug');

        // Three levels are not allowed: a subcategory cannot be a parent.
        $this->categories()->call('create')->set('name', 'Deep')->set('parentId', (string) $child->id)->call('save')->assertHasErrors('parentId');

        // A category with listings cannot get subcategories.
        $leaf = Category::factory()->create();
        Listing::factory()->create(['category_id' => $leaf->id]);
        $this->categories()->call('create')->set('name', 'Under')->set('parentId', (string) $leaf->id)->call('save')->assertHasErrors('parentId');
        $this->assertFalse(Category::where('slug', 'under')->exists());
    }

    public function test_renaming_the_slug_keeps_old_urls_redirecting(): void
    {
        $category = Category::factory()->create(['slug' => 'old-name']);

        $this->categories()->call('edit', $category->id)->set('slug', 'new-name')->call('save')->assertHasNoErrors();

        $this->assertSame('new-name', $category->fresh()->slug);
        $this->assertTrue(Category::findBySlugOrRedirect('old-name')->is($category));
    }

    public function test_reorder_swaps_neighbours(): void
    {
        $a = Category::factory()->create(['sort_order' => 1]);
        $b = Category::factory()->create(['sort_order' => 1]); // equal order: renumbered on move
        $c = Category::factory()->create(['sort_order' => 3]);

        $this->categories()->call('move', $c->id, 'up');

        $this->assertSame([$a->id, $c->id, $b->id], Category::roots()->orderBy('sort_order')->pluck('id')->all());

        $this->categories()->call('move', $a->id, 'up'); // already first: nothing happens
        $this->assertSame(1, $a->fresh()->sort_order);
    }

    public function test_category_in_use_can_only_be_switched_off(): void
    {
        $category = Category::factory()->create();
        Listing::factory()->create(['category_id' => $category->id]);

        $this->categories()->call('delete', $category->id)->assertHasErrors('delete');
        $this->assertModelExists($category);

        $this->categories()->call('edit', $category->id)->set('isActive', false)->call('save')->assertHasNoErrors();
        $this->assertFalse($category->fresh()->is_active);

        $unused = Category::factory()->create();
        $this->categories()->call('delete', $unused->id)->assertHasNoErrors();
        $this->assertModelMissing($unused);
        $this->assertTrue(AuditLog::where('action', 'category.deleted')->exists());
    }

    // ---- Attributes -------------------------------------------------------------------------

    public function test_create_an_attribute_with_rules(): void
    {
        $this->attributes()->call('create')
            ->set('key', 'engine_cc')->set('label', 'Engine capacity')->set('type', 'number')->set('unit', 'cc')
            ->set('ruleMin', '50')->set('ruleMax', '8000')->set('ruleStep', '1')
            ->call('save')->assertHasNoErrors();

        $attribute = Attribute::where('key', 'engine_cc')->firstOrFail();
        $this->assertSame(AttributeType::Number, $attribute->type);
        $this->assertSame(['min' => 50, 'max' => 8000, 'step' => 1], $attribute->rules);
        $this->assertTrue(AuditLog::where('action', 'attribute.created')->exists());

        $this->attributes()->call('create')->set('key', 'engine_cc')->set('label', 'Again')->call('save')->assertHasErrors('key');
        $this->attributes()->call('create')->set('key', 'Bad Key')->set('label', 'X')->call('save')->assertHasErrors('key');
        $this->attributes()->call('create')->set('key', 'range_x')->set('label', 'X')->set('type', 'number')
            ->set('ruleMin', '10')->set('ruleMax', '5')->call('save')->assertHasErrors('ruleMax');
    }

    public function test_attribute_in_use_keeps_its_type_and_cannot_be_deleted(): void
    {
        $attribute = Attribute::factory()->ofType(AttributeType::Number)->create();
        $listing = Listing::factory()->create();
        $listing->attributeValues()->create(['attribute_id' => $attribute->id, 'value_number' => 5]);

        $this->attributes()->call('select', $attribute->id)->assertSee(__('admin.catalogue.in_use'))
            ->set('type', 'text')->call('save')->assertHasErrors('type');
        $this->assertSame(AttributeType::Number, $attribute->fresh()->type);

        $this->attributes()->call('select', $attribute->id)->call('deleteAttribute')->assertHasErrors('form');
        $this->assertModelExists($attribute);

        $this->attributes()->call('select', $attribute->id)->set('isActive', false)->call('save')->assertHasNoErrors();
        $this->assertFalse($attribute->fresh()->is_active);
    }

    public function test_options_add_deactivate_and_delete_only_when_unused(): void
    {
        $attribute = Attribute::factory()->select(['petrol'])->create();
        $petrol = $attribute->options()->firstOrFail();

        $this->attributes()->call('select', $attribute->id)->assertSee('Petrol')
            ->set('optionLabel', 'Plug-in Hybrid')->call('saveOption')->assertHasNoErrors();
        $hybrid = AttributeOption::where('attribute_id', $attribute->id)->where('value', 'plug-in-hybrid')->firstOrFail();

        // Duplicate value.
        $this->attributes()->call('select', $attribute->id)->set('optionLabel', 'Petrol')->call('saveOption')->assertHasErrors('optionValue');

        Listing::factory()->create()->attributeValues()->create(['attribute_id' => $attribute->id, 'value_option_id' => $petrol->id]);

        $this->attributes()->call('select', $attribute->id)->call('deleteOption', $petrol->id)->assertHasErrors('options');
        $this->assertModelExists($petrol);
        $this->attributes()->call('select', $attribute->id)->call('editOption', $petrol->id)->set('optionValue', 'gasoline')
            ->call('saveOption')->assertHasErrors('optionValue');
        $this->attributes()->call('select', $attribute->id)->call('toggleOption', $petrol->id);
        $this->assertFalse($petrol->fresh()->is_active);

        $this->attributes()->call('select', $attribute->id)->call('deleteOption', $hybrid->id)->assertHasNoErrors();
        $this->assertModelMissing($hybrid);
        $this->assertTrue(AuditLog::where('action', 'attribute_option.deleted')->exists());
    }

    public function test_options_of_another_attribute_cannot_be_touched(): void
    {
        $attribute = Attribute::factory()->select(['a'])->create();
        $other = Attribute::factory()->select(['b'])->create();
        $foreign = $other->options()->firstOrFail();

        $this->attributes()->call('select', $attribute->id)->call('deleteOption', $foreign->id)->assertNotFound();
        $this->assertModelExists($foreign);
    }

    public function test_dependent_options_need_a_parent_option(): void
    {
        $make = Attribute::factory()->select(['toyota', 'honda'])->create(['key' => 'make']);
        $toyota = $make->options()->where('value', 'toyota')->firstOrFail();

        $this->attributes()->call('create')->set('key', 'model')->set('label', 'Model')->set('type', 'select')
            ->set('dependsOn', (string) $make->id)->call('save')->assertHasNoErrors();
        $model = Attribute::where('key', 'model')->firstOrFail();
        $this->assertSame($make->id, $model->depends_on_attribute_id);

        $this->attributes()->call('select', $model->id)->set('optionLabel', 'Aqua')->call('saveOption')->assertHasErrors('optionParent');
        $this->attributes()->call('select', $model->id)->set('optionLabel', 'Aqua')->set('optionParent', (string) $toyota->id)
            ->call('saveOption')->assertHasNoErrors();
        $this->assertSame($toyota->id, AttributeOption::where('attribute_id', $model->id)->value('parent_option_id'));

        // Options exist: what it depends on is now fixed, and the parent option cannot be deleted.
        $this->attributes()->call('select', $model->id)->set('dependsOn', '')->call('save')->assertHasErrors('dependsOn');
        $this->attributes()->call('select', $make->id)->call('deleteOption', $toyota->id)->assertHasErrors('options');
        $this->assertModelExists($toyota);
    }

    public function test_link_an_attribute_to_a_category_and_remove_it(): void
    {
        $attribute = Attribute::factory()->ofType(AttributeType::Number)->create();
        $category = Category::factory()->create();

        $this->attributes()->call('select', $attribute->id)
            ->set('linkCategory', (string) $category->id)->set('linkRequired', true)->set('linkConditions', ['used'])
            ->set('linkFilterable', true)->set('linkGroup', 'Engine')->set('linkScope', 'qm')
            ->call('saveLink')->assertHasNoErrors();

        $pivot = $category->attributeDefinitions()->whereKey($attribute->id)->firstOrFail()->pivot;
        $this->assertTrue($pivot->is_required);
        $this->assertSame(['used'], $pivot->required_for_conditions);
        $this->assertTrue($pivot->is_filterable);
        $this->assertSame('Engine', $pivot->group);
        $this->assertSame(['QM'], $pivot->scope_countries);

        // Update the same link.
        $this->attributes()->call('select', $attribute->id)->call('editLink', $category->id)->assertSet('linkGroup', 'Engine')
            ->set('linkFilterable', false)->call('saveLink')->assertHasNoErrors();
        $this->assertFalse($category->attributeDefinitions()->whereKey($attribute->id)->firstOrFail()->pivot->is_filterable);

        // Linked attributes cannot be deleted.
        $this->attributes()->call('select', $attribute->id)->call('deleteAttribute')->assertHasErrors('form');

        $this->attributes()->call('select', $attribute->id)->call('detach', $category->id);
        $this->assertFalse($category->attributeDefinitions()->whereKey($attribute->id)->exists());
        $this->assertTrue(AuditLog::where('action', 'attribute.unlinked')->exists());
    }

    public function test_unused_unlinked_attribute_can_be_deleted_with_its_options(): void
    {
        $attribute = Attribute::factory()->select(['a', 'b'])->create();

        $this->attributes()->call('select', $attribute->id)->call('deleteAttribute')->assertHasNoErrors();

        $this->assertModelMissing($attribute);
        $this->assertSame(0, AttributeOption::where('attribute_id', $attribute->id)->count());
    }
}
