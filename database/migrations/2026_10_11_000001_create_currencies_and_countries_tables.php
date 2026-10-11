<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Global marketplace master data: currencies (ISO 4217), countries (ISO 3166-1) and the
 * currencies a country allows for listings. See docs/architecture/13-global-marketplace.md §2–§3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->char('code', 3)->primary();
            $table->char('numeric_code', 3)->nullable();
            $table->json('name');
            $table->unsignedTinyInteger('minor_units');
            $table->string('symbol_override', 8)->nullable();
            $table->string('display_decimals', 10)->default('auto');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->char('iso2', 2)->unique();
            $table->char('iso3', 3)->unique();
            $table->json('name');
            $table->string('slug', 80)->unique();
            $table->char('default_currency', 3)->nullable();
            $table->string('number_locale', 15);
            $table->string('phone_country_code', 5)->nullable();
            $table->string('timezone', 40)->nullable();
            $table->unsignedTinyInteger('region_levels')->default(1);
            $table->json('region_label')->nullable();
            $table->json('subregion_label')->nullable();
            $table->json('city_label')->nullable();
            $table->json('locality_label')->nullable();
            $table->boolean('require_region')->default(false);
            $table->boolean('require_city')->default(false);
            $table->boolean('is_active')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedInteger('geonames_id')->nullable()->unique();
            $table->timestamps();

            $table->foreign('default_currency')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('country_currencies', function (Blueprint $table) {
            $table->unsignedSmallInteger('country_id');
            $table->char('currency_code', 3);
            $table->boolean('is_default')->default(false);

            $table->primary(['country_id', 'currency_code']);
            $table->foreign('country_id')->references('id')->on('countries')->cascadeOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_currencies');
        Schema::dropIfExists('countries');
        Schema::dropIfExists('currencies');
    }
};
