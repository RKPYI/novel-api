<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('chapters', function (Blueprint $table) {
            $table->integer('word_count')->default(0);
            $table->integer('views')->default(0);
            $table->boolean('is_free')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->index(['novel_id', 'chapter_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chapters', function (Blueprint $table) {
            $table->dropIndex(['novel_id', 'chapter_number']);
            $table->dropColumn(['word_count', 'views', 'is_free', 'published_at']);
        });
    }
};
