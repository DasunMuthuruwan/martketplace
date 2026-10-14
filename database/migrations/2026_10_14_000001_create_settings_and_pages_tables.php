<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site settings (ADM-05) and static pages (ADM-06), database design §2.9. Settings are typed and
 * validated by App\Support\Settings; missing keys fall back to config/marketplace.php. Pages hold
 * Markdown source only (rendered with raw HTML stripped) and relative links (portability P2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->json('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->json('title');
            $table->json('body');
            $table->boolean('is_published')->default(false);
            $table->json('meta')->nullable(); // {description}
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
        Schema::dropIfExists('settings');
    }
};
