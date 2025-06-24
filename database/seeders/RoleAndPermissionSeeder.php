<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RoleAndPermissionSeeder extends Seeder
{
 
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
    }
}
