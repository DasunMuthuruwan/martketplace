<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Urgent badge (owner 2026-10-31, B9): a paid, labelled badge for 7 days. No price is inserted, so
 * it is on sale only where the Owner sets one. `listings.urgent_until` keeps the end on the ad, so
 * the many pages that show cards need no extra query.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->timestamp('urgent_until')->nullable()->after('sorted_at')->index();
        });

        $now = now();
        DB::table('boost_products')->insert([
            'type' => 'urgent', 'duration_days' => 7, 'sort_order' => 5, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        // Orders are financial records: refuse rather than delete them or leave a type the code cannot read.
        if (DB::table('boost_orders')->where('product_type', 'urgent')->exists()) {
            throw new RuntimeException('Urgent orders exist: resolve them before rolling back.');
        }

        DB::table('listing_boosts')->where('type', 'urgent')->delete();
        DB::table('boost_products')->where('type', 'urgent')->delete();
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex(['urgent_until']);
            $table->dropColumn('urgent_until');
        });
    }
};
