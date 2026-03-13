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
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Illuminate\Validation\ValidationException;

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

        $onlineThreshold = now()->subMinutes((int) config('session.lifetime', 120))->getTimestamp();

        $userQuery = User::search($request)
            ->select('users_laravel.*')
            ->selectRaw(
                'CASE WHEN EXISTS (
                    SELECT 1
                    FROM sessions s
                    WHERE s.user_id = users_laravel.id
                      AND s.last_activity >= ?
                ) THEN 1 ELSE 0 END as is_online',
                [$onlineThreshold]
            )
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

        $rooms = DB::table('room')
            ->where('estado', true)
            ->orderBy('nombre', 'asc')
            ->get([
                'id',
                'nombre',
                'company_id',
                'communication_channel_id',
            ]);

        return inertia('User/Create', [
            'roles' => Role::pluck('name'),
            'channels' => $channels,
            'rooms' => $rooms,
        ]);
    }

    public function lookupDni(Request $request)
    {
        if (!auth()->user()->can('add user')) {
            abort(403, 'User does not have the right permissions');
        }

        $data = $request->validate([
            'dni' => ['required', 'digits:8'],
        ]);

        $dni = (string) $data['dni'];
        $baseUrl = (string) config('services.metadata.dni_lookup_url');

        if ($baseUrl === '') {
            return response()->json(['message' => 'DNI lookup URL not configured.'], 500);
        }

        $url = str_contains($baseUrl, '{dni}')
            ? str_replace('{dni}', $dni, $baseUrl)
            : $baseUrl;

        if (!str_contains($url, $dni)) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . 'dni=' . urlencode($dni);
        }

        $response = Http::timeout(10)->acceptJson()->get($url);

        if ($response->failed()) {
            return response()->json([
                'message' => 'No se pudo consultar el servicio de DNI.',
            ], 422);
        }

        $payload = $response->json();

        return response()->json([
            'found_patient' => $payload['found_patient'] ?? null,
            'raw' => $payload,
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        if (!auth()->user()->can('add user')) {
            abort(403, 'User does not have the right permissions');
        }

        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated) {
            $user = User::create([
                'name' => $validated['name'],
                'username' => $validated['username'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'estado' => 1,
            ]);

            $user->assignRole($request->role);

            $selectedIds = collect($request->input('channels', []))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $selectedChannels = CommunicationChannel::query()
                ->whereIn('id', $selectedIds)
                ->get(['id', 'company_id'])
                ->keyBy('id');

            if ($selectedChannels->isNotEmpty()) {
                $syncData = $selectedChannels->mapWithKeys(fn ($ch) => [
                    $ch->id => ['company_id' => $ch->company_id],
                ])->toArray();

                $user->communicationChannels()->sync($syncData);
            }

            $roomAssignments = collect($request->input('room_assignments', []))
                ->mapWithKeys(function ($roomId, $channelId) {
                    $cid = (int) $channelId;
                    $rid = (int) $roomId;
                    return $cid > 0 && $rid > 0 ? [$cid => $rid] : [];
                });

            $roomRows = DB::table('room')
                ->where('estado', true)
                ->get(['id', 'company_id', 'communication_channel_id'])
                ->keyBy('id');

            $insertRows = [];

            foreach ($selectedChannels as $channelId => $channel) {
                $availableRooms = $roomRows
                    ->filter(fn ($room) =>
                        (int) $room->communication_channel_id === (int) $channelId
                        && (int) $room->company_id === (int) $channel->company_id
                    )
                    ->values();

                if ($availableRooms->isEmpty()) {
                    continue;
                }

                $assignedRoomId = (int) ($roomAssignments->get((int) $channelId) ?? 0);

                if ($assignedRoomId <= 0) {
                    throw ValidationException::withMessages([
                        'room_assignments' => 'Debe seleccionar un room para cada canal que tenga rooms disponibles.',
                    ]);
                }

                if (! $availableRooms->contains(fn ($room) => (int) $room->id === $assignedRoomId)) {
                    throw ValidationException::withMessages([
                        'room_assignments' => 'El room seleccionado no pertenece al canal/compañía elegidos.',
                    ]);
                }

                $insertRows[] = [
                    'user_id' => $user->id,
                    'room_id' => $assignedRoomId,
                    'estado' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (!empty($insertRows)) {
                DB::table('user_room')->insert($insertRows);
            }
        });

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

        $rooms = DB::table('room')
            ->where('estado', true)
            ->orderBy('nombre', 'asc')
            ->get([
                'id',
                'nombre',
                'company_id',
                'communication_channel_id',
            ]);

        $selectedRoomAssignments = DB::table('user_room as ur')
            ->join('room as r', 'r.id', '=', 'ur.room_id')
            ->where('ur.user_id', $user->id)
            ->where('ur.estado', true)
            ->where('r.estado', true)
            ->get([
                'r.communication_channel_id',
                'r.id as room_id',
            ])
            ->mapWithKeys(fn ($row) => [
                (string) ((int) $row->communication_channel_id) => (int) $row->room_id,
            ]);

        return inertia('User/Edit', [
            'user' => UserResource::make($user),
            'roles' => $roles,
            'currentRole' => $user->getRoleNames()->first(),
            'channels' => $channels,
            'selectedChannels' => $selectedChannels,
            'rooms' => $rooms,
            'selectedRoomAssignments' => $selectedRoomAssignments,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated, $user) {
            $user->name = $validated['name'];
            $user->username = $validated['username'];
            $user->email = $validated['email'];
            $user->estado = $validated['estado'];

            if (!empty($validated['password'])) {
                $user->password = bcrypt($validated['password']);
            }

            $user->save();
            $user->syncRoles([$validated['role']]);

            $selectedIds = collect($request->input('channels', []))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $selectedChannels = CommunicationChannel::query()
                ->whereIn('id', $selectedIds)
                ->get(['id', 'company_id'])
                ->keyBy('id');

            $syncData = $selectedChannels->mapWithKeys(fn ($ch) => [
                $ch->id => ['company_id' => $ch->company_id],
            ])->toArray();

            $user->communicationChannels()->sync($syncData);

            $roomAssignments = collect($request->input('room_assignments', []))
                ->mapWithKeys(function ($roomId, $channelId) {
                    $cid = (int) $channelId;
                    $rid = (int) $roomId;
                    return $cid > 0 && $rid > 0 ? [$cid => $rid] : [];
                });

            $roomRows = DB::table('room')
                ->where('estado', true)
                ->get(['id', 'company_id', 'communication_channel_id'])
                ->keyBy('id');

            $insertRows = [];

            foreach ($selectedChannels as $channelId => $channel) {
                $availableRooms = $roomRows
                    ->filter(fn ($room) =>
                        (int) $room->communication_channel_id === (int) $channelId
                        && (int) $room->company_id === (int) $channel->company_id
                    )
                    ->values();

                if ($availableRooms->isEmpty()) {
                    continue;
                }

                $assignedRoomId = (int) ($roomAssignments->get((int) $channelId) ?? 0);

                if ($assignedRoomId <= 0) {
                    throw ValidationException::withMessages([
                        'room_assignments' => 'Debe seleccionar un room para cada canal que tenga rooms disponibles.',
                    ]);
                }

                if (! $availableRooms->contains(fn ($room) => (int) $room->id === $assignedRoomId)) {
                    throw ValidationException::withMessages([
                        'room_assignments' => 'El room seleccionado no pertenece al canal/compañía elegidos.',
                    ]);
                }

                $insertRows[] = [
                    'user_id' => $user->id,
                    'room_id' => $assignedRoomId,
                    'estado' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('user_room')->where('user_id', $user->id)->delete();

            if (!empty($insertRows)) {
                DB::table('user_room')->insert($insertRows);
            }
        });

        return redirect()->route('users.index')
            ->with('success', $user->name . ' actualizado correctamente');
    }

    public function destroy(User $user)
    {
         $user->update(['estado' => 0]);
        return redirect()->route('users.index');
    }

}
