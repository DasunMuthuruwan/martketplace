<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seller ratings (G5, ADR-027 E2): a buyer who revealed the seller's number 2–90 days before rates
 * the seller once (and may update it). Stars count at once; a comment shows after moderation; the
 * seller may reply once. A review written by an account later deleted stays (reviewer set to null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained()->nullOnDelete(); // the ad they contacted about
            $table->unsignedSmallInteger('country_id')->nullable(); // for the blocklist
            $table->unsignedTinyInteger('rating'); // 1–5
            $table->text('comment')->nullable();
            $table->string('comment_status', 10)->default('none'); // none, pending, approved, rejected
            $table->string('deal', 5)->nullable(); // yes, no
            $table->text('reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->string('status', 10)->default('published'); // published, removed
            $table->string('removed_reason', 500)->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();

            $table->unique(['seller_id', 'reviewer_id']);
            $table->index(['seller_id', 'status', 'created_at']);
            $table->index(['comment_status', 'created_at']);
        });

        // Eligibility asks: did this buyer reveal one of the seller's numbers in the window?
        Schema::table('contact_reveals', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        // MariaDB adopts the new index for the user_id foreign key, so the key goes first and comes
        // back with its own index afterwards.
        Schema::table('contact_reveals', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('contact_reveals', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
        Schema::dropIfExists('seller_reviews');
    }
};
