<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banners managed by staff (owner 2026-10-31, B10): an image, a link, a country (null = every
 * country), a placement and dates. There is no seller self-service.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120); // also the image's alternative text
            $table->string('image_path', 255);
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->string('link_url', 500)->nullable();
            $table->unsignedSmallInteger('country_id')->nullable();
            $table->string('placement', 20); // BannerPlacement
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('country_id')->references('id')->on('countries')->cascadeOnDelete();
            $table->index(['placement', 'is_active', 'country_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
