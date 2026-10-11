<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Category-specific attributes (database design §2.4–§2.6, category tree doc §2–§3, §6).
 * Area attributes entered with a unit (land size) are added with the measurement units in T1.5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique(); // stable, used in filter URLs
            $table->json('label');
            $table->string('type', 20);           // text, number, select, multiselect, boolean, year
            $table->string('unit', 20)->nullable(); // display unit for numbers: km, cc, GB, in
            $table->json('rules')->nullable();      // {min, max, step, maxlength, max_years_ahead}
            $table->foreignId('depends_on_attribute_id')->nullable()->constrained('attributes')->nullOnDelete();
            $table->boolean('allows_other')->default(false); // select: "Other" + free text
            $table->json('help_text')->nullable();
            $table->boolean('is_active')->default(true); // deactivated, never hard-deleted once used (CAT-06)
            $table->timestamps();
        });

        Schema::create('attribute_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_option_id')->nullable()->constrained('attribute_options')->restrictOnDelete();
            $table->string('value', 80); // slug used in URLs
            $table->json('label');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('scope_countries')->nullable();

            $table->unique(['attribute_id', 'parent_option_id', 'value']);
            $table->index(['attribute_id', 'is_active', 'sort_order']);
        });

        Schema::create('category_attribute', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->restrictOnDelete();
            $table->boolean('is_required')->default(false);
            $table->json('required_for_conditions')->nullable(); // e.g. mileage: ["used","reconditioned"]
            $table->boolean('is_filterable')->default(false);
            $table->boolean('show_on_card')->default(false);
            $table->string('group', 40)->nullable();
            $table->integer('sort_order')->default(0);
            $table->json('scope_countries')->nullable();

            $table->primary(['category_id', 'attribute_id']);
            $table->index('attribute_id');
        });

        // Calculated price per unit (Land → land_size, ADR-018/021); set once the attribute exists.
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('unit_price_attribute_id')->nullable()->after('allowed_price_types')
                ->constrained('attributes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_price_attribute_id');
        });
        Schema::dropIfExists('category_attribute');
        Schema::dropIfExists('attribute_options');
        Schema::dropIfExists('attributes');
    }
};
