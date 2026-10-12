<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Listings, their typed attribute values (EAV) and images (database design §2.7–§2.9, §4;
 * global design §9). Indexes follow the public scope + sort patterns; more are added only
 * where EXPLAIN on seeded data shows a need (T5.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            // Leaf categories only (enforced in code); a category with listings cannot be deleted.
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('type', 10);    // sale, rent, wanted
            $table->string('status', 20);  // draft … expired (ListingStatus)
            $table->string('title', 100);
            $table->string('slug', 120);
            $table->text('description');
            // Original asking price in `currency`, never converted (ADR-020).
            $table->decimal('price', 18, 3)->nullable();
            $table->char('currency', 3);
            $table->string('price_type', 20);
            $table->string('rent_period', 10)->nullable();
            $table->string('condition', 20)->nullable();
            $table->unsignedSmallInteger('country_id');
            $table->foreignId('region_id')->nullable()->constrained('regions')->restrictOnDelete();
            $table->foreignId('subregion_id')->nullable()->constrained('regions')->restrictOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->restrictOnDelete();
            $table->foreignId('locality_id')->nullable()->constrained('localities')->nullOnDelete();
            $table->string('locality_text', 80)->nullable();
            $table->boolean('show_call')->default(true);
            $table->boolean('show_whatsapp')->default(true);
            $table->json('attributes_summary')->nullable();
            $table->unsignedBigInteger('cover_image_id')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->string('moderation_note', 500)->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->timestamp('renewed_at')->nullable();
            $table->boolean('flagged')->default(false);
            $table->boolean('legal_hold')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('currency')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('country_id')->references('id')->on('countries')->restrictOnDelete();

            // Browse and search (public scope = status + expiry, then location/category + newest).
            $table->index(['status', 'category_id', 'published_at']);
            $table->index(['status', 'country_id', 'published_at']);
            $table->index(['status', 'country_id', 'category_id', 'published_at']);
            $table->index(['status', 'region_id', 'published_at']);
            $table->index(['status', 'subregion_id', 'published_at']);
            $table->index(['status', 'city_id', 'published_at']);
            // Price in one currency (never mixed, global design §6).
            $table->index(['status', 'country_id', 'currency', 'price']);
            $table->index(['status', 'category_id', 'currency', 'price']);
            // Expiry, moderation queue, "my listings".
            $table->index(['status', 'expires_at']);
            $table->index(['status', 'submitted_at']);
            $table->index(['user_id', 'status', 'updated_at']);
        });

        // Keyword search (T5.1). SQLite (tests) has no FULLTEXT; search falls back to LIKE there.
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('listings', function (Blueprint $table) {
                $table->fullText(['title', 'description']);
            });
        }

        Schema::create('listing_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            // Attributes and options in use cannot be deleted, only deactivated (CAT-06).
            $table->foreignId('attribute_id')->constrained()->restrictOnDelete();
            $table->decimal('value_number', 18, 4)->nullable(); // number, year; area normalised to m²
            $table->foreignId('value_option_id')->nullable()->constrained('attribute_options')->restrictOnDelete();
            $table->string('value_text', 255)->nullable();
            $table->boolean('value_bool')->nullable();
            $table->decimal('input_value', 18, 4)->nullable(); // area as entered (source of truth)
            $table->unsignedSmallInteger('input_unit_id')->nullable();

            $table->foreign('input_unit_id')->references('id')->on('measurement_units')->restrictOnDelete();
            // Explicit names: the generated ones exceed MySQL's 64-character identifier limit.
            $table->index(['attribute_id', 'value_option_id', 'listing_id'], 'lav_attribute_option_listing_index');
            $table->index(['attribute_id', 'value_number', 'listing_id'], 'lav_attribute_number_listing_index');
            $table->index(['attribute_id', 'value_bool', 'listing_id'], 'lav_attribute_bool_listing_index');
        });

        Schema::create('listing_images', function (Blueprint $table) {
            $table->id();
            // Null while uploaded in a draft before the listing exists (orphans cleaned after 24 h).
            $table->foreignId('listing_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('disk', 20);
            $table->string('directory', 191);
            $table->char('basename', 32)->unique();
            $table->string('format', 10); // webp, jpg
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->unsignedInteger('bytes');
            $table->char('sha256', 64);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['listing_id', 'sort_order']);
            $table->index(['listing_id', 'created_at']);
            $table->index('sha256');
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->foreign('cover_image_id')->references('id')->on('listing_images')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropForeign(['cover_image_id']);
        });
        Schema::dropIfExists('listing_images');
        Schema::dropIfExists('listing_attribute_values');
        Schema::dropIfExists('listings');
    }
};
