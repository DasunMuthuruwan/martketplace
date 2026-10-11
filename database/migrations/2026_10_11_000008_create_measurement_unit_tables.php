<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Measurement units for land and floor area (global design §5, ADR-021). Units are data, never
 * code: which units a country offers, its default and its "price per" unit live here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('measurement_units', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 20)->unique();
            $table->json('name');
            $table->string('symbol', 20);
            $table->string('dimension', 20)->default('area');
            $table->decimal('factor_to_base', 20, 10); // base unit of the dimension (area: m²)
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['dimension', 'is_active']);
        });

        // Units offered by a country in area inputs; exactly one default per country (enforced in code).
        Schema::create('country_measurement_units', function (Blueprint $table) {
            $table->unsignedSmallInteger('country_id');
            $table->unsignedSmallInteger('measurement_unit_id');
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);

            $table->primary(['country_id', 'measurement_unit_id']);
            $table->foreign('country_id')->references('id')->on('countries')->cascadeOnDelete();
            $table->foreign('measurement_unit_id')->references('id')->on('measurement_units')->restrictOnDelete();
        });

        Schema::table('countries', function (Blueprint $table) {
            // "Price per X" on land (set per country in data). Null = no per-unit price in that country.
            $table->unsignedSmallInteger('land_price_unit_id')->nullable()->after('timezone');
            $table->foreign('land_price_unit_id')->references('id')->on('measurement_units')->nullOnDelete();
        });

        Schema::table('attributes', function (Blueprint $table) {
            // "area": entered as value + unit offered by the listing country, normalised to m².
            $table->string('dimension', 20)->nullable()->after('unit');
        });
    }

    public function down(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->dropColumn('dimension');
        });
        Schema::table('countries', function (Blueprint $table) {
            $table->dropForeign(['land_price_unit_id']);
            $table->dropColumn('land_price_unit_id');
        });
        Schema::dropIfExists('country_measurement_units');
        Schema::dropIfExists('measurement_units');
    }
};
