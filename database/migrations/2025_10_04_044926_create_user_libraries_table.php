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
        Schema::create('user_libraries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('novel_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['want_to_read', 'reading', 'completed', 'dropped', 'on_hold'])->default('want_to_read');
            $table->boolean('is_favorite')->default(false);
            $table->timestamp('added_at')->useCurrent();
            $table->timestamp('status_updated_at')->useCurrent();
            $table->timestamps();

            $table->unique(['user_id', 'novel_id']); // One entry per user per novel
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'is_favorite']);
            $table->index(['user_id', 'added_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_libraries');
    }
};
