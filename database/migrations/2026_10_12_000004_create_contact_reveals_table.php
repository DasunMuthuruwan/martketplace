<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Each "show number" / WhatsApp reveal (database design §2.10, MSG-04): rate-limit evidence and
 * seller statistics. The IP is stored only as an HMAC. Pruned after retention.contact_reveal days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_reveals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 10); // call | whatsapp
            $table->char('ip_hash', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['listing_id', 'created_at']);
            $table->index(['ip_hash', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_reveals');
    }
};
