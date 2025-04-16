<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;

class UserController extends Controller
{
    

    public function index(Request $request)
    {
        $userQuery = User::search($request);
        //$classes = ClassResource::collection(Classes::all());

        return inertia('User/Index', [
            'users' => UserResource::collection(
                $userQuery->paginate(5)
            ),
            //s'classes' => $classes,
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
        $classes = ClassResource::collection(Classes::all());

        return inertia('User/Create', [
            'classes' => $classes
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        User::create($request->validated());

        return redirect()->route('users.index');
    }

    public function edit(User $user)
    {
        //$classes = ClassResource::collection(Classes::all());

        return inertia('User/Edit', [
            'user' => UserResource::make($user),
            //'classes' => $classes
        ]);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $user->update($request->validated());

        return redirect()->route('users.index');
    }

    public function destroy(User $user)
    {
        $user->delete();

        return redirect()->route('users.index');
    }

}
