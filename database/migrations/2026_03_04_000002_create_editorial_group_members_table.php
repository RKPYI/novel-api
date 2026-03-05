<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('editorial_group_id')->constrained('editorial_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['editor', 'author']);
            $table->timestamps();

            // Each user can only belong to one group
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_group_members');
    }
};
