<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('glossary_proposals', function (Blueprint $table): void {
            $table->string('ai_recommendation', 24)->nullable()->after('status');
            $table->text('ai_explanation')->nullable()->after('ai_recommendation');
        });
    }

    public function down(): void
    {
        Schema::table('glossary_proposals', function (Blueprint $table): void {
            $table->dropColumn(['ai_recommendation', 'ai_explanation']);
        });
    }
};
