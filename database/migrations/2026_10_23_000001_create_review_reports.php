<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G5b (ADR-027): anyone signed in may report a review once (fake, abusive, personal details,
 * other); a reported review goes to the staff queue. Also syncs the new `reviews.moderate`
 * permission (Owner, Admin, Moderator) into existing databases.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('seller_reviews')->cascadeOnDelete();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 20);
            $table->string('details', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['review_id', 'reporter_id']);
        });

        Schema::table('seller_reviews', function (Blueprint $table) {
            $table->timestamp('reported_at')->nullable()->after('status'); // open reports wait for staff
            $table->index('reported_at');
        });

        (new RolesAndPermissionsSeeder)->run();
    }

    public function down(): void
    {
        Schema::table('seller_reviews', function (Blueprint $table) {
            $table->dropIndex(['reported_at']);
            $table->dropColumn('reported_at');
        });
        Schema::dropIfExists('review_reports');
    }
};
