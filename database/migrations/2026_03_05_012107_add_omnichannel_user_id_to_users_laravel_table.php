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
        // Agregar columna para vincular con users (omnichannel)
        Schema::table('users_laravel', function (Blueprint $table) {
            $table->integer('omnichannel_user_id')->nullable()->after('id');
            $table->foreign('omnichannel_user_id')->references('id')->on('users')->onDelete('set null');
        });

        // Sincronizar usuarios existentes (excepto ID=1 que es el bot)
        $laravelUsers = DB::table('users_laravel')->where('id', '!=', 1)->get();
        
        foreach ($laravelUsers as $user) {
            // Verificar si ya existe en users por email o username
            $omnichannelUser = DB::table('users')
                ->where(function($query) use ($user) {
                    $query->where('email_address', $user->email)
                          ->orWhere('login_username', $user->username);
                })
                ->first();

            if ($omnichannelUser) {
                // Ya existe, solo vincular
                DB::table('users_laravel')
                    ->where('id', $user->id)
                    ->update(['omnichannel_user_id' => $omnichannelUser->id]);
            } else {
                // Crear nuevo registro en users solo si no existe
                try {
                    $newOmnichannelId = DB::table('users')->insertGetId([
                        'login_username' => $user->username,
                        'email_address' => $user->email,
                        'user_role' => 'agent',
                        'status' => $user->estado == 1 ? 'active' : 'inactive',
                        'create_date' => now(),
                    ]);

                    // Vincular
                    DB::table('users_laravel')
                        ->where('id', $user->id)
                        ->update(['omnichannel_user_id' => $newOmnichannelId]);
                } catch (\Exception $e) {
                    // Si falla, intentar buscar nuevamente por si se creó entre tiempo
                    $omnichannelUser = DB::table('users')
                        ->where('email_address', $user->email)
                        ->first();
                    
                    if ($omnichannelUser) {
                        DB::table('users_laravel')
                            ->where('id', $user->id)
                            ->update(['omnichannel_user_id' => $omnichannelUser->id]);
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users_laravel', function (Blueprint $table) {
            $table->dropForeign(['omnichannel_user_id']);
            $table->dropColumn('omnichannel_user_id');
        });
    }
};
