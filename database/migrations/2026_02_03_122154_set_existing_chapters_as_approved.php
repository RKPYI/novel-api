<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Set all existing chapters to 'approved' status so they remain visible.
     */
    public function up(): void
    {
        DB::table('chapters')
            ->where('status', 'draft')
            ->update([
                'status' => 'approved',
                'published_at' => DB::raw('COALESCE(published_at, created_at)'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot reliably reverse this migration
    }
};
