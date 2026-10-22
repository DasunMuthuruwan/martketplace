<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G1 (ADR-027, E4): what visitors search for, as counts only: per country (0 = all countries), normalised
 * term and day. No user, session or IP is stored. Feeds popular searches in live search and trending
 * searches (G2); rare terms are never shown.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_terms', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('country_id')->default(0);
            $table->string('term', 60);
            $table->date('date');
            $table->unsignedInteger('searches')->default(0);

            $table->unique(['country_id', 'term', 'date']);
            $table->index(['country_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_terms');
    }
};
