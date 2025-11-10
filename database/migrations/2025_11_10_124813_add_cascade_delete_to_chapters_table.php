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
            // Drop the existing foreign key constraint
            $table->dropForeign(['novel_id']);

            // Recreate the foreign key with cascade delete
            $table->foreign('novel_id')
                  ->references('id')
                  ->on('novels')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chapters', function (Blueprint $table) {
            // Drop the cascade foreign key
            $table->dropForeign(['novel_id']);

            // Recreate the original foreign key without cascade
            $table->foreign('novel_id')
                  ->references('id')
                  ->on('novels');
        });
    }
};
