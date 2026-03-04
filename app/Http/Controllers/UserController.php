<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use Spatie\Permission\Models\Role; 
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use App\Models\CommunicationChannel;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class UserController extends Controller
{

    /*public function __construct()
    {
        $this->authorizeResource(User::class, 'user');
    }*/
    
    public function index(Request $request)
    {

        if (Gate::denies('viewAny', User::class)) {
            return Inertia::location(route('error.403'));
        }
        
        $allowedSorts = ['id'];

        $sort = in_array($request->get('sort'), $allowedSorts)
            ? $request->get('sort')
            : 'id';

        $direction = $request->get('direction') === 'desc'
            ? 'desc'
            : 'asc';

        $userQuery = User::search($request)
            ->orderBy($sort, $direction);

        return inertia('User/Index', [
            'users' => UserResource::collection(
                $userQuery->paginate(5)->withQueryString()
            ),
            'search' => $request->search ?? '',
            'sort' => $sort,
            'direction' => $direction,
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
        if (!auth()->user()->hasPermissionTo('add user')) {
            return redirect()->route('error.403'); 
        }

        $channels = DB::table('communication_channels as a')
            ->leftJoin('companies as b', 'a.company_id', '=', 'b.id')
            ->where('a.status', 'ACTIVO')
            ->orderBy('b.company_name', 'asc')
            ->selectRaw("
                a.id,
                a.company_id,
                b.company_name,
                channel_name
            ")
            ->get();

        return inertia('User/Create', [
            'roles' => Role::pluck('name'),
            'channels' => $channels,
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        if (!auth()->user()->can('add user')) {
            abort(403, 'User does not have the right permissions');
        }

        $user = User::create($request->validated());
        $user->assignRole($request->role); 
        $selectedIds = $request->input('channels', []);

        if (!empty($selectedIds)) {
            $rows = CommunicationChannel::query()
                ->whereIn('id', $selectedIds)
                ->get(['id', 'company_id']);

            $syncData = $rows->mapWithKeys(fn ($ch) => [
                $ch->id => ['company_id' => $ch->company_id],
            ])->toArray();

            $user->communicationChannels()->sync($syncData);
        }

        return redirect()->route('users.index');
    }

    public function edit(User $user)
    {
        $roles = Role::pluck('name');

        $channels = DB::table('communication_channels as a')
            ->leftJoin('companies as b', 'a.company_id', '=', 'b.id')
            ->where('a.status', 'ACTIVO')
            ->orderBy('b.company_name', 'asc')
            ->selectRaw("
                a.id,
                a.company_id,
                b.company_name,
                a.channel_name
            ")
            ->get();

        // ids ya asignados al usuario (pivot user_communication_channels)
        $selectedChannels = DB::table('user_communication_channels')
            ->where('user_id', $user->id)
            ->pluck('communication_channel_id')
            ->map(fn ($id) => (int) $id)
            ->values();

        return inertia('User/Edit', [
            'user' => UserResource::make($user),
            'roles' => $roles,
            'currentRole' => $user->getRoleNames()->first(),
            'channels' => $channels,
            'selectedChannels' => $selectedChannels,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $validated = $request->validated();

        $user->name = $validated['name'];
        $user->username = $validated['username'];
        $user->email = $validated['email'];
        $user->estado = $validated['estado'];

        if (!empty($validated['password'])) {
            $user->password = bcrypt($validated['password']);
        }

        $user->save();
        $user->syncRoles([$validated['role']]);

        // ✅ sync canales (si viene vacío, limpia todos)
        $selectedIds = $request->input('channels', []);

        $rows = CommunicationChannel::query()
            ->whereIn('id', $selectedIds)
            ->get(['id', 'company_id']);

        $syncData = $rows->mapWithKeys(fn ($ch) => [
            $ch->id => ['company_id' => $ch->company_id],
        ])->toArray();

        $user->communicationChannels()->sync($syncData);

        return redirect()->route('users.index')
            ->with('success', $user->name . ' actualizado correctamente');
    }

    public function destroy(User $user)
    {
         $user->update(['estado' => 0]);
        return redirect()->route('users.index');
    }

}
