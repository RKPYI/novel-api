<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('glossary_proposals', function (Blueprint $table): void {
            $table->string('classification', 32)->nullable()->after('ai_explanation');
        });
    }

    public function down(): void
    {
        Schema::table('glossary_proposals', function (Blueprint $table): void {
            $table->dropColumn('classification');
        });
    }
};
