<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users_laravel', function (Blueprint $table) {
            $table->string('username')->nullable()->after('name');
        });

        // Generar username a partir del email para usuarios existentes
        DB::table('users_laravel')->get()->each(function ($user) {
            $username = explode('@', $user->email)[0];
            $baseUsername = $username;
            $counter = 1;
            
            // Verificar si el username ya existe y agregar número si es necesario
            while (DB::table('users_laravel')->where('username', $username)->exists()) {
                $username = $baseUsername . $counter;
                $counter++;
            }
            
            DB::table('users_laravel')
                ->where('id', $user->id)
                ->update(['username' => $username]);
        });

        // Hacer el campo obligatorio y único después de llenar los datos
        Schema::table('users_laravel', function (Blueprint $table) {
            $table->string('username')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users_laravel', function (Blueprint $table) {
            $table->dropColumn('username');
        });
    }
};
