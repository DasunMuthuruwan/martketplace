<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Re-registration block after a ban (data retention §1: "keep HMAC hashes of email and phone for
 * 2 years"). Only keyed hashes are stored, never the address or number itself. Pruned after
 * retention.banned_identity_hash days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banned_identities', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10); // email | phone
            $table->char('hash', 64);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['kind', 'hash']);
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banned_identities');
    }
};
