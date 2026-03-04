<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds claim fields to chapters table so editors can claim/lock chapters
     * for review, preventing race conditions where multiple editors review
     * the same chapter simultaneously.
     */
    public function up(): void
    {
        Schema::table('chapters', function (Blueprint $table) {
            $table->foreignId('claimed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable()->after('claimed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chapters', function (Blueprint $table) {
            $table->dropForeign(['claimed_by']);
            $table->dropColumn(['claimed_by', 'claimed_at']);
        });
    }
};
