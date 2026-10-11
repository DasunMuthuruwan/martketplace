<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Category tree (docs/architecture/03-database-design.md §2.3, category tree doc §1 and §6).
 * Two levels; listings are posted to leaves. `unit_price_attribute_id` is added in T3.2 together
 * with the attributes table. The "price per" unit comes from the listing country (ADR-021), so
 * there is no per-category unit label.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->json('name');
            $table->string('slug', 120)->unique();
            $table->json('description')->nullable();
            $table->string('icon', 60)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('scope_countries')->nullable(); // ISO2 list; null = every country
            $table->json('allowed_types');               // sale / rent / wanted
            $table->string('price_mode', 20)->default('required');
            $table->boolean('allow_free')->default(false);
            $table->json('allowed_price_types')->nullable(); // null = all (subject to allow_free)
            $table->boolean('show_condition')->default(true);
            $table->unsignedTinyInteger('max_images')->default(8);
            $table->string('moderation', 20)->default('inherit');
            $table->json('meta_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
            $table->index('is_active');
        });

        // 301s for renamed slugs (category URLs must keep working after an admin edit).
        Schema::create('slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->string('old_slug', 120);
            $table->unsignedBigInteger('target_id');
            $table->timestamp('created_at')->nullable();

            $table->unique(['type', 'old_slug']);
            $table->index(['type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slug_redirects');
        Schema::dropIfExists('categories');
    }
};
