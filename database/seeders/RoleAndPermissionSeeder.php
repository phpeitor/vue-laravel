<?php

namespace Database\Seeders;

<<<<<<< HEAD
=======
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
>>>>>>> gitlab/main
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RoleAndPermissionSeeder extends Seeder
{
<<<<<<< HEAD
    public function run(): void
    {
        $permissions = [
            'users',
            'edit user',
            'delete user',
            'add user',
            'templates',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
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

        $email = request()->get('email');
        $roleInput = request()->get('role');

        if (!$email || !$roleInput) {
            $this->command->warn("📌 Seeder ejecutado sin asignar usuario. Se crearon roles y permisos.");
            return;
        }

        $validRoles = ['admin', 'user'];
        if (!in_array($roleInput, $validRoles)) {
            $this->command->error("❌ Rol inválido: '$roleInput'. Usa: admin o user.");
            return;
        }

        $userModel = User::where('email', $email)->first();

        if (!$userModel) {
            $this->command->error("❌ No se encontró un usuario con el email: $email");
            return;
        }

        $userModel->syncRoles([$roleInput]);

        $this->command->info("✅ Rol '$roleInput' asignado a {$userModel->name} ({$userModel->email})");
=======
 
    public function run(): void
    {
        Permission::query()->delete();
        
        Permission::firstOrCreate(['name' => 'users']);
        Permission::firstOrCreate(['name' => 'edit user']);
        Permission::firstOrCreate(['name' => 'delete user']);
        Permission::firstOrCreate(['name' => 'add user']);
        Permission::firstOrCreate(['name' => 'students']);  

        //$admin = Role::create(['name' => 'admin']);
        //$user = Role::create(['name' => 'user']);
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $user = Role::firstOrCreate(['name' => 'user']);

        $admin->givePermissionTo(['users', 'delete user', 'edit user', 'add user']);
        $user->givePermissionTo(['students']);

        $user1 = User::find(1); 
        $user1->assignRole('admin'); 
>>>>>>> gitlab/main
    }
}
