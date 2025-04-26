<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use Spatie\Permission\Models\Role; 

class UserController extends Controller
{
    
    public function index(Request $request)
    {
        $userQuery = User::search($request);

        return inertia('User/Index', [
            'users' => UserResource::collection(
                $userQuery->paginate(5)
            ),
            'search' => request('search') ?? ''
        ]);
    }

    protected function applySearch(Builder $query, $search)
    {
        return $query->when($search, function ($query, $search) {
            $query->where('name', 'like', '%' . $search . '%');
        });
    }

    public function create()
    {
       
        return inertia('User/Create', [
            'roles' => Role::pluck('name')
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $user = User::create($request->validated());

        //if ($request->has('role')) {
            $user->assignRole($request->role); 
        //}

        return redirect()->route('users.index');
    }

    public function edit(User $user)
    {
        $roles = Role::pluck('name');

        return inertia('User/Edit', [
            'user' => UserResource::make($user),
            'roles' => $roles,
            'currentRole' => $user->getRoleNames()->first(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        //$user->update($request->validated());

        //return redirect()->route('users.index');

        $validated = $request->validated();

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->estado = $validated['estado'];

        if (!empty($validated['password'])) {
            $user->password = bcrypt($validated['password']);
        }

        $user->save();

        $user->syncRoles([$validated['role']]);
        
        return redirect()->route('users.index')
        ->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $user)
    {
         $user->update(['estado' => 0]);

        return redirect()->route('users.index');
    }

}
