<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        // No crear en users si es ID=1 (reservado para bot)
        if ($user->id === 1) {
            return;
        }

        // Crear registro en tabla users (omnichannel) automáticamente
        $omnichannelId = DB::table('users')->insertGetId([
            'login_username' => $user->username,
            'email_address' => $user->email,
            'user_role' => 'agent',
            'status' => $user->estado == 1 ? 'active' : 'inactive',
            'create_date' => now(),
        ]);

        // Actualizar omnichannel_user_id en users_laravel
        DB::table('users_laravel')
            ->where('id', $user->id)
            ->update(['omnichannel_user_id' => $omnichannelId]);
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        // Si no tiene omnichannel_user_id o es ID=1, no hacer nada
        if (!$user->omnichannel_user_id || $user->id === 1) {
            return;
        }

        // Actualizar registro en users (omnichannel)
        DB::table('users')
            ->where('id', $user->omnichannel_user_id)
            ->update([
                'login_username' => $user->username,
                'email_address' => $user->email,
                'status' => $user->estado == 1 ? 'active' : 'inactive',
                'write_date' => now(),
            ]);
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        // Si tiene omnichannel_user_id, marcar como inactivo en users
        if ($user->omnichannel_user_id && $user->id !== 1) {
            DB::table('users')
                ->where('id', $user->omnichannel_user_id)
                ->update([
                    'status' => 'inactive',
                    'write_date' => now(),
                ]);
        }
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        //
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        //
    }
}
