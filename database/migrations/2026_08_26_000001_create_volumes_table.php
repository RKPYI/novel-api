<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('volumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('novel_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('volume_number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['novel_id', 'volume_number']);
            $table->index(['novel_id', 'volume_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volumes');
    }
};
