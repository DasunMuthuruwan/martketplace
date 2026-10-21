<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paid boosts, step 1 (implementation plan §7a, B1; ADR-026).
 * - `listings.sorted_at`: the "newest" order. Equal to the publication time until a Bump moves it;
 *   renewal leaves it alone (renewing is not free bumping). The browse indexes move to it.
 * - `listing_boosts`: Bump and Top ad, granted by staff or (B5) bought.
 * - `listing_daily_views`: views per ad and day, for the seller's before/during statistics.
 */
return new class extends Migration
{
    /** Browse indexes that end with the "newest" column (create_listing_tables). */
    private const BROWSE = [['status', 'category_id'], ['status', 'country_id'], ['status', 'country_id', 'category_id'], ['status', 'region_id'], ['status', 'subregion_id'], ['status', 'city_id']];

    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->timestamp('sorted_at')->nullable()->after('published_at');
        });
        DB::table('listings')->whereNotNull('published_at')->update(['sorted_at' => DB::raw('published_at')]);

        Schema::table('listings', function (Blueprint $table) {
            foreach (self::BROWSE as $columns) {
                $table->dropIndex([...$columns, 'published_at']);
                $table->index([...$columns, 'sorted_at']);
            }
        });

        Schema::create('listing_boosts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // BoostType: bump, top
            // The order that paid for it (B5); null when granted by staff.
            $table->unsignedBigInteger('order_id')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable(); // null for a Bump (one moment)
            $table->timestamp('ended_at')->nullable(); // ended early (ad no longer public, refund)
            $table->timestamp('last_shown_at')->nullable(); // top-ad rotation
            $table->timestamps();

            $table->index(['type', 'ends_at']);
            $table->index(['listing_id', 'type']);
            $table->index('order_id');
        });

        Schema::create('listing_daily_views', function (Blueprint $table) {
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('views')->default(0);
            $table->primary(['listing_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_daily_views');
        Schema::dropIfExists('listing_boosts');
        Schema::table('listings', function (Blueprint $table) {
            foreach (self::BROWSE as $columns) {
                $table->dropIndex([...$columns, 'sorted_at']);
                $table->index([...$columns, 'published_at']);
            }
        });
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn('sorted_at');
        });
    }
};
