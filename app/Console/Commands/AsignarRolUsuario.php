<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AsignarRolUsuario extends Command
{
    protected $signature = 'asignar:rol {--email=} {--role=}';
    protected $description = 'Asigna un rol (admin/user) a un usuario por email y crea roles/permisos si no existen';

    public function handle()
    {
        $email = $this->option('email');
        $roleInput = $this->option('role');

        if (!$email || !$roleInput) {
            $this->error("Debes proporcionar --email y --role (admin o user).");
            return;
        }

        $validRoles = ['admin', 'user'];
        if (!in_array($roleInput, $validRoles)) {
            $this->error("Rol inválido. Usa: admin o user.");
            return;
        }

        $permissions = [
            'users',
            'edit user',
            'delete user',
            'add user',
            'templates',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $user = Role::firstOrCreate(['name' => 'user']);

        $admin->syncPermissions([
            'users',
            'edit user',
            'delete user',
            'add user',
            'templates',
        ]);

        $user->syncPermissions([
            'templates',
        ]);

        $usuario = User::where('email', $email)->first();
        if (!$usuario) {
            $this->error("No se encontró el usuario con email: $email");
            return;
        }

        $usuario->syncRoles([$roleInput]);

        $this->info("Rol '$roleInput' asignado exitosamente al usuario: {$usuario->name} ({$usuario->email})");
    }
}