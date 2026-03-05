<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'users',
            'edit user',
            'delete user',
            'add user',
            'templates',
            'add template',
            'campaigns',
            'add campaign',
            'chat',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $supervisor = Role::firstOrCreate(['name' => 'supervisor']);
        $asesor = Role::firstOrCreate(['name' => 'asesor']);
        $user = Role::firstOrCreate(['name' => 'user']);

        $admin->syncPermissions([
            'users',
            'edit user',
            'delete user',
            'add user',
            'templates',
            'add template',
            'campaigns',
            'add campaign',
            'chat',
        ]);

        $supervisor->syncPermissions([
            // 'users',
            // 'edit user',
            // 'add user',
            'templates',
            'add template',
            'campaigns',
            'add campaign',
            'chat',
        ]);

        $asesor->syncPermissions([
            // 'templates',
            // 'campaigns',
            'chat',
        ]);

        $user->syncPermissions([
             'templates',
        ]);

        $email = request()->get('email');
        $roleInput = request()->get('role');

        if (!$email || !$roleInput) {
            $this->command->warn("📌 Seeder ejecutado sin asignar usuario. Se crearon roles y permisos.");
            return;
        }

        $validRoles = ['admin', 'supervisor', 'asesor', 'user'];
        if (!in_array($roleInput, $validRoles)) {
            $this->command->error("❌ Rol inválido: '$roleInput'. Usa: admin, supervisor, asesor o user.");
            return;
        }

        $userModel = User::where('email', $email)->first();

        if (!$userModel) {
            $this->command->error("❌ No se encontró un usuario con el email: $email");
            return;
        }

        $userModel->syncRoles([$roleInput]);

        $this->command->info("✅ Rol '$roleInput' asignado a {$userModel->name} ({$userModel->email})");
    }
}
