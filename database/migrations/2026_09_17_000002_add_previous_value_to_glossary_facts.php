<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('glossary_facts', function (Blueprint $table) {
            $table->text('previous_value')->nullable()->after('value');
        });
    }

    public function down(): void
    {
        Schema::table('glossary_facts', function (Blueprint $table) {
            $table->dropColumn('previous_value');
        });
    }
};
