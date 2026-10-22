<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paid boosts, B5 (ADR-026, D3): a seller's order for a boost, paid by manual bank transfer and
 * confirmed by staff. The product, price and currency are copied onto the order, so later price
 * changes never alter it. Orders are financial records: they survive the ad and the account
 * (references set to null), and keep their reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boost_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 16)->unique(); // written on the bank transfer
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('boost_product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_type', 20);
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->unsignedSmallInteger('country_id');
            $table->char('currency', 3);
            $table->decimal('amount', 18, 3);
            $table->string('method', 20)->default('bank_transfer');
            $table->string('status', 20)->default('pending'); // BoostOrderStatus
            $table->timestamp('expires_at')->nullable(); // unpaid orders lapse
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('closed_at')->nullable(); // cancelled, expired or refunded
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 500)->nullable(); // staff note: payment details seen, refund reason
            $table->timestamps();

            $table->foreign('country_id')->references('id')->on('countries')->restrictOnDelete();
            $table->foreign('currency')->references('code')->on('currencies')->restrictOnDelete();
            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'expires_at']);
        });

        Schema::table('listing_boosts', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('boost_orders')->nullOnDelete();
            // "Your Top ad ends tomorrow" is sent once.
            $table->timestamp('ending_reminder_sent_at')->nullable()->after('last_shown_at');
        });
    }

    public function down(): void
    {
        Schema::table('listing_boosts', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropColumn('ending_reminder_sent_at');
        });
        Schema::dropIfExists('boost_orders');
    }
};
