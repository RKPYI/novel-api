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
        Schema::table('novels', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->string('status')->default('ongoing'); // ongoing, completed, hiatus
            $table->string('cover_image')->nullable();
            $table->integer('total_chapters')->default(0);
            $table->integer('views')->default(0);
            $table->integer('likes')->default(0);
            $table->decimal('rating', 3, 2)->default(0.00); // 0.00 to 5.00
            $table->integer('rating_count')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_trending')->default(false);
            $table->timestamp('published_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('novels', function (Blueprint $table) {
            $table->dropColumn([
                'description', 'status', 'cover_image', 'total_chapters',
                'views', 'likes', 'rating', 'rating_count', 'is_featured',
                'is_trending', 'published_at'
            ]);
        });
    }
};
