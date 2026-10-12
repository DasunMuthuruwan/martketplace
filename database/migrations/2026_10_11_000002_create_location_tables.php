<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Location hierarchy: country → region (level 1) → region (level 2, optional) → city → locality.
 * See docs/architecture/13-global-marketplace.md §4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('country_id');
            $table->foreignId('parent_id')->nullable()->constrained('regions')->restrictOnDelete();
            $table->unsignedTinyInteger('level');
            $table->json('name');
            // Unique per country (not per parent): location URLs accept a level-1 or level-2 slug.
            $table->string('slug', 120);
            $table->string('code', 40)->nullable();
            $table->unsignedInteger('geonames_id')->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('country_id')->references('id')->on('countries')->restrictOnDelete();
            $table->unique(['country_id', 'slug']);
            $table->index(['country_id', 'level', 'parent_id', 'sort_order']);
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('country_id');
            // Deepest region level of the country; null only for countries without regions.
            $table->foreignId('region_id')->nullable()->constrained('regions')->restrictOnDelete();
            $table->json('name');
            $table->string('ascii_name', 150);
            $table->string('slug', 120);
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->unsignedInteger('population')->nullable();
            $table->unsignedInteger('geonames_id')->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_curated')->default(false);
            $table->timestamps();

            $table->foreign('country_id')->references('id')->on('countries')->restrictOnDelete();
            $table->unique(['country_id', 'region_id', 'slug']);
            $table->index(['region_id', 'is_active', 'ascii_name']);
            $table->index(['country_id', 'ascii_name']);
        });

        Schema::create('localities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained('cities')->restrictOnDelete();
            $table->json('name');
            $table->string('slug', 120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['city_id', 'slug']);
        });

        Schema::create('location_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('country_id');
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->foreignId('subregion_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('proposed_city', 80)->nullable();
            $table->string('proposed_locality', 80)->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('resolved_city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->foreignId('resolved_locality_id')->nullable()->constrained('localities')->nullOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->string('resolution_note', 500)->nullable();
            $table->timestamps();

            $table->foreign('country_id')->references('id')->on('countries')->cascadeOnDelete();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_requests');
        Schema::dropIfExists('localities');
        Schema::dropIfExists('cities');
        // Self-referencing parent_id (RESTRICT): SQLite deletes rows on DROP and would refuse.
        Schema::withoutForeignKeyConstraints(fn () => Schema::dropIfExists('regions'));
    }
};
