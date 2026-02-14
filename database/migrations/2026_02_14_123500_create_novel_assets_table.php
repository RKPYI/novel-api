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
        Schema::create('novel_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('novel_id')->constrained('novels')->cascadeOnDelete();
            $table->string('filename');
            $table->string('original_filename');
            $table->string('path');
            $table->string('mime_type', 50);
            $table->unsignedInteger('size'); // bytes
            $table->timestamps();

            $table->index('novel_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('novel_assets');
    }
};
