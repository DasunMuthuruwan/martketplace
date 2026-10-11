<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketplace account fields (docs/architecture/03-database-design.md §2.1).
 * Phone numbers are stored as E.164 and validated per country (libphonenumber).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email_verified_at');
            $table->char('phone_country', 2)->nullable()->after('phone');
            $table->timestamp('phone_verified_at')->nullable()->after('phone_country'); // reserved for future OTP
            $table->string('whatsapp_phone', 20)->nullable()->after('phone_verified_at');
            $table->unsignedSmallInteger('country_id')->nullable()->after('whatsapp_phone');
            $table->string('contact_visibility', 20)->default('hidden')->after('country_id');
            $table->string('status', 20)->default('active')->after('contact_visibility');
            $table->string('status_reason', 500)->nullable()->after('status');
            $table->timestamp('suspended_until')->nullable()->after('status_reason');
            $table->string('locale', 10)->default('en')->after('suspended_until');
            $table->timestamp('last_login_at')->nullable()->after('locale');
            $table->boolean('legal_hold')->default(false)->after('last_login_at');
            $table->timestamp('anonymised_at')->nullable()->after('legal_hold');
            $table->softDeletes();

            $table->foreign('country_id')->references('id')->on('countries')->nullOnDelete();
            $table->index('phone');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropIndex(['phone']);
            $table->dropIndex(['status']);
            $table->dropSoftDeletes();
            $table->dropColumn([
                'phone', 'phone_country', 'phone_verified_at', 'whatsapp_phone', 'country_id', 'contact_visibility',
                'status', 'status_reason', 'suspended_until', 'locale', 'last_login_at', 'legal_hold', 'anonymised_at',
            ]);
        });
    }
};
