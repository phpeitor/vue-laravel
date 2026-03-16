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
        if (!Schema::hasTable('users_laravel')) {
            return;
        }

        if (Schema::hasColumn('users_laravel', 'omnichannel_user_id')) {
            Schema::table('users_laravel', function (Blueprint $table) {
                $table->dropColumn('omnichannel_user_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('users_laravel')) {
            return;
        }

        if (!Schema::hasColumn('users_laravel', 'omnichannel_user_id')) {
            Schema::table('users_laravel', function (Blueprint $table) {
                $table->unsignedBigInteger('omnichannel_user_id')->nullable()->after('id');
            });
        }
    }
};
