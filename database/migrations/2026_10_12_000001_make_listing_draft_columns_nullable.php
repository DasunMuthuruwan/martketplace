<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The posting wizard saves the draft at the location step (routes doc §3.1), before details and
 * price exist. These columns are filled by later steps and required again on submit (T4.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->string('type', 10)->nullable()->change();
            $table->string('title', 100)->nullable()->change();
            $table->string('slug', 120)->nullable()->change();
            $table->text('description')->nullable()->change();
            $table->string('price_type', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->string('type', 10)->nullable(false)->change();
            $table->string('title', 100)->nullable(false)->change();
            $table->string('slug', 120)->nullable(false)->change();
            $table->text('description')->nullable(false)->change();
            $table->string('price_type', 20)->nullable(false)->change();
        });
    }
};
