<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chapters', function (Blueprint $table) {
            // review_status, reviewed_by, reviewed_at already exist — only add review_notes
            if (!Schema::hasColumn('chapters', 'review_notes')) {
                $table->text('review_notes')->nullable()->after('reviewed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('chapters', function (Blueprint $table) {
            if (Schema::hasColumn('chapters', 'review_notes')) {
                $table->dropColumn('review_notes');
            }
        });
    }
};
