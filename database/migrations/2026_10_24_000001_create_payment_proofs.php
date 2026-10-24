<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B7 (owner 2026-10-24): sellers attach proof of their bank transfer (slip photo, screenshot or
 * PDF) to an unpaid promotion order, with the transfer date and a note. Files live on the private
 * disk and are deleted `retention.payment_proof` days after the order is paid or closed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boost_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 10); // image (re-encoded JPEG) or pdf
            $table->string('path', 255); // on the private disk
            $table->string('original_name', 120); // shown to staff and the seller only
            $table->unsignedInteger('bytes');
            $table->char('sha256', 64);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('boost_orders', function (Blueprint $table) {
            $table->timestamp('proof_at')->nullable()->after('closed_at'); // latest proof; null when none
            $table->date('transfer_date')->nullable()->after('proof_at'); // as given by the seller
            $table->string('transfer_note', 300)->nullable()->after('transfer_date');
            $table->index(['status', 'proof_at']);
        });
    }

    public function down(): void
    {
        Schema::table('boost_orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'proof_at']);
            $table->dropColumn(['proof_at', 'transfer_date', 'transfer_note']);
        });
        Schema::dropIfExists('payment_proofs');
    }
};
