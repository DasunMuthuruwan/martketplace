<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paid boosts, B4 (ADR-026, D1–D3): what can be bought, at what price in each country, and where
 * sellers send a bank transfer. Prices and bank details are data the Owner sets per country
 * (never in code); the products are reference data inserted here, without prices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boost_products', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20); // BoostType
            $table->unsignedSmallInteger('duration_days')->nullable(); // null for a Bump
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['type', 'duration_days']);
        });

        Schema::create('boost_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boost_product_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('country_id');
            $table->char('currency', 3);
            $table->decimal('amount', 18, 3);
            $table->timestamps();

            $table->foreign('country_id')->references('id')->on('countries')->cascadeOnDelete();
            $table->foreign('currency')->references('code')->on('currencies')->restrictOnDelete();
            $table->unique(['boost_product_id', 'country_id']);
        });

        // Manual bank transfer (D3): one receiving account per country, shown to sellers with their
        // order reference. Off until the Owner switches it on.
        Schema::create('bank_transfer_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('country_id')->unique();
            $table->string('bank_name', 120);
            $table->string('account_name', 120);
            $table->string('account_number', 60);
            $table->string('branch', 120)->nullable();
            $table->string('swift_code', 20)->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('is_active')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('country_id')->references('id')->on('countries')->cascadeOnDelete();
        });

        $now = now();
        DB::table('boost_products')->insert([
            ['type' => 'bump', 'duration_days' => null, 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'top', 'duration_days' => 3, 'sort_order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'top', 'duration_days' => 7, 'sort_order' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'top', 'duration_days' => 15, 'sort_order' => 4, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transfer_accounts');
        Schema::dropIfExists('boost_prices');
        Schema::dropIfExists('boost_products');
    }
};
