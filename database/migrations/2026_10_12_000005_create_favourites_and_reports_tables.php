<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Favourites (SRCH-08) and reports of listings or users (MOD-02), database design §2.10.
 * One open report per reporter and target: enforced in code under a lock (a partial unique index
 * is not portable to MySQL/MariaDB).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favourites', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['user_id', 'listing_id']);
            $table->index(['listing_id']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reportable_type', 20); // listing | user
            $table->unsignedBigInteger('reportable_id');
            $table->string('reason', 30);
            $table->string('details', 1000)->nullable();
            $table->string('status', 20)->default('open'); // open | actioned | dismissed
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->string('resolution_note', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['reportable_type', 'reportable_id', 'status']);
            $table->index(['reporter_id', 'reportable_type', 'reportable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('favourites');
    }
};
