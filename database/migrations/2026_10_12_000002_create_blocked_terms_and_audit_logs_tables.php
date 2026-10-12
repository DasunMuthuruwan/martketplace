<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keyword blocklist (MOD-03, security doc §5; per country, global design §10) and the staff audit
 * log (MOD-04, database design §2.10). Audit rows are append-only (no updated_at, guarded in code).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocked_terms', function (Blueprint $table) {
            $table->id();
            $table->string('term', 100);
            $table->string('match', 10)->default('word');   // word | phrase
            $table->string('action', 10)->default('flag');  // flag | block
            $table->unsignedSmallInteger('country_id')->nullable(); // null = every country
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('country_id')->references('id')->on('countries')->cascadeOnDelete();
            $table->unique(['term', 'country_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60);
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('reason', 500)->nullable();
            $table->json('changes')->nullable();
            $table->char('ip_hash', 64)->nullable(); // HMAC of the IP, never the raw address
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id', 'created_at']);
            $table->index(['actor_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('blocked_terms');
    }
};
