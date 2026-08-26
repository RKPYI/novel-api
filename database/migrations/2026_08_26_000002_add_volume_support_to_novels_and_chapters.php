<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('novels', function (Blueprint $table) {
            $table->boolean('uses_volumes')->default(false)->after('status');
        });

        Schema::table('chapters', function (Blueprint $table) {
            $table->foreignId('volume_id')->nullable()->after('novel_id')->constrained()->nullOnDelete();
            $table->index(['volume_id', 'chapter_number']);
        });
    }

    public function down(): void
    {
        Schema::table('chapters', function (Blueprint $table) {
            $table->dropForeign(['volume_id']);
            $table->dropIndex(['volume_id', 'chapter_number']);
            $table->dropColumn('volume_id');
        });

        Schema::table('novels', function (Blueprint $table) {
            $table->dropColumn('uses_volumes');
        });
    }
};
