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
        Schema::table('users', function (Blueprint $table) {
            $table->integer('role')->default(0)->after('password'); // 0 = user, 1 = admin
            $table->string('provider')->nullable()->after('role'); // google, email
            $table->string('provider_id')->nullable()->after('provider'); // Google ID
            $table->string('avatar')->nullable()->after('provider_id'); // Profile picture
            $table->text('bio')->nullable()->after('avatar'); // User bio
            $table->timestamp('last_login_at')->nullable()->after('bio');
            $table->boolean('is_active')->default(true)->after('last_login_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role', 'provider', 'provider_id', 'avatar', 'bio', 'last_login_at', 'is_active'
            ]);
        });
    }
};
