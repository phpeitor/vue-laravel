<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Company;
use App\Models\Thread;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    
    public function index(Request $request)
    {
        $this->authorize('viewAny', Thread::class);

        $userId = $request->user()->id;

        $assignments = DB::table('user_communication_channels')
            ->where('user_id', $userId)
            ->get(['company_id', 'communication_channel_id']);

        $hasAssignments = $assignments->isNotEmpty();

        $companiesQuery = Company::select('id', 'company_name')->orderBy('company_name');

        if ($hasAssignments) {
            $companyIds = $assignments->pluck('company_id')->unique()->values();
            $companiesQuery->whereIn('id', $companyIds);
        }

        $companies = $companiesQuery->get();

        // Mapa: company_id => [channel_ids...]
        $allowedChannelsByCompany = $hasAssignments
            ? $assignments
                ->groupBy('company_id')
                ->map(fn ($rows) => $rows->pluck('communication_channel_id')->unique()->values())
                ->toArray()
            : null;

        // Default: primer par permitido (si tiene asignaciones)
        $defaultCompanyId = null;
        $defaultChannelId = null;

        if ($hasAssignments) {
            $first = $assignments->first();
            $defaultCompanyId = (int) $first->company_id;
            $defaultChannelId = (int) $first->communication_channel_id;
        }

        $hasAssignments = $assignments->isNotEmpty();

        $authUser = $request->user();
        $isRestrictedRole = $authUser->hasAnyRole(['user', 'asesor']);

        return Inertia::render('Chat/Index', [
            'companies' => $companies,
            'allowed_channels_by_company' => $allowedChannelsByCompany,
            'default_company_id' => $defaultCompanyId,
            'default_channel_id' => $defaultChannelId,
            'has_channel_assignments' => $hasAssignments,
            'current_user_agent_id' => (int) $authUser->id,
            'is_restricted_role' => $isRestrictedRole,
        ]);
    }

    private function assertCompanyChannelAccess(Request $request, int $companyId, int $channelId): void
    {
        $userId = $request->user()->id;

        $hasAssignments = DB::table('user_communication_channels')
            ->where('user_id', $userId)
            ->exists();

        // Si no tiene asignaciones -> NO restringir
        if (!$hasAssignments) return;

        $allowed = DB::table('user_communication_channels')
            ->where('user_id', $userId)
            ->where('company_id', $companyId)
            ->where('communication_channel_id', $channelId)
            ->exists();

        abort_unless($allowed, 403, 'No autorizado para esa compañía/canal.');
    }

    public function threads(Request $request)
    {
        $this->authorize('viewAny', Thread::class);

        $data = $request->validate([
            'company_id' => ['required', 'integer'],
            'communication_channel_id' => ['required', 'integer'],
            'date_start' => ['nullable', 'date'],
            'date_end' => ['nullable', 'date'],
            'q' => ['nullable', 'string'],
            'q_by' => ['nullable', 'in:ALL,PHONE,SENDER_ID'],
            'thread_status' => ['nullable', 'in:ALL,OPEN,CLOSED'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'cursor' => ['nullable', 'integer'],
            'phone' => ['nullable', 'string'], // ✅ búsqueda global por teléfono/sender_id
            'assigned_agent_id_min' => ['nullable', 'integer', 'min:1'],
            'assigned_agent_id_max' => ['nullable', 'integer', 'min:1'],
        ]);

        $companyId = (int) $data['company_id'];
        $channelId = (int) $data['communication_channel_id'];
        $this->assertCompanyChannelAccess($request, $companyId, $channelId);
        $dateStart = isset($data['date_start']) ? Carbon::parse($data['date_start'])->startOfDay() : null;
        $dateEnd   = isset($data['date_end']) ? Carbon::parse($data['date_end'])->endOfDay() : null;
        $q         = trim($data['q'] ?? '');
        $qBy       = strtoupper(trim($data['q_by'] ?? 'ALL'));
        $status    = strtoupper(trim($data['thread_status'] ?? '')); // '', OPEN, CLOSED, ALL
        $limit     = (int) ($data['limit'] ?? 60);
        $cursor    = isset($data['cursor']) ? (int) $data['cursor'] : null;
        $assignedAgentIdMin = isset($data['assigned_agent_id_min']) ? (int) $data['assigned_agent_id_min'] : null;
        $assignedAgentIdMax = isset($data['assigned_agent_id_max']) ? (int) $data['assigned_agent_id_max'] : null;

        // ✅ Roles restringidos: solo ven sus propios threads
        $authUser = $request->user();
        $isRestricted = $authUser->hasAnyRole(['user', 'asesor']);
        if ($isRestricted) {
            $assignedAgentIdMin = $authUser->id;
            $assignedAgentIdMax = $authUser->id;
        }

        // ✅ NUEVO: si viene phone => buscar global (ignorando fecha/status/q)
        $phone = trim($data['phone'] ?? '');

        // 4) Base query
        $base = DB::table('threads as a')
            ->where('a.company_id', $companyId)
            ->where('a.communication_channel_id', $channelId);

        // ✅ MODO BÚSQUEDA GLOBAL POR TELÉFONO/SENDER_ID (solo company+canal)
        if ($phone !== '') {
            $base->where(function ($w) use ($phone) {
                // threads.sender_id
                $w->where('a.sender_id', 'ilike', "%{$phone}%")
                // customers.phone (a través de messages)
                ->orWhereExists(function ($sub) use ($phone) {
                    $sub->select(DB::raw(1))
                        ->from('messages as bq')
                        ->leftJoin('customers as cq', 'cq.id', '=', 'bq.customer_id')
                        ->whereColumn('bq.thread_id', 'a.id')
                        ->where('cq.phone', 'ilike', "%{$phone}%");
                });
            });
        } else {
            // ✅ MODO NORMAL (fecha/status + q/q_by)

            $hasDates = ($dateStart && $dateEnd);

            // 5) Filtro OPEN/CLOSED/ALL + lógica de fechas
            if ($hasDates) {
                if ($status === 'OPEN') {
                    $base->where('a.thread_status', 'OPEN');
                } elseif ($status === 'CLOSED') {
                    $base->where('a.thread_status', 'CLOSED')
                        ->whereExists(function ($sub) use ($dateStart, $dateEnd) {
                            $sub->select(DB::raw(1))
                                ->from('messages as b')
                                ->whereColumn('b.thread_id', 'a.id')
                                ->whereBetween('b.create_date', [$dateStart, $dateEnd]);
                        });
                } else {
                    // ALL o vacío => (mensajes en rango) OR (OPEN)
                    $base->where(function ($w) use ($dateStart, $dateEnd) {
                        $w->whereExists(function ($sub) use ($dateStart, $dateEnd) {
                            $sub->select(DB::raw(1))
                                ->from('messages as b')
                                ->whereColumn('b.thread_id', 'a.id')
                                ->whereBetween('b.create_date', [$dateStart, $dateEnd]);
                        })->orWhere('a.thread_status', 'OPEN');
                    });
                }
            } else {
                if ($status === 'CLOSED') $base->where('a.thread_status', 'CLOSED');
                elseif ($status === 'ALL') { /* sin filtro */ }
                else $base->where('a.thread_status', 'OPEN'); // default
            }

            // 6) Búsqueda opcional por ALL / PHONE / SENDER_ID
            if ($q !== '') {
                $base->where(function ($w) use ($q, $qBy) {
                    if ($qBy === 'SENDER_ID') {
                        $w->where('a.sender_id', 'ilike', "%{$q}%");
                        return;
                    }

                    if ($qBy === 'PHONE') {
                        $w->where('a.sender_id', 'ilike', "%{$q}%")
                        ->orWhereExists(function ($sub) use ($q) {
                            $sub->select(DB::raw(1))
                                ->from('messages as bq')
                                ->leftJoin('customers as cq', 'cq.id', '=', 'bq.customer_id')
                                ->whereColumn('bq.thread_id', 'a.id')
                                ->where('cq.phone', 'ilike', "%{$q}%");
                        });
                        return;
                    }

                    // ALL
                    $w->where('a.sender_id', 'ilike', "%{$q}%")
                    ->orWhereExists(function ($sub) use ($q) {
                        $sub->select(DB::raw(1))
                            ->from('messages as bq')
                            ->leftJoin('customers as cq', 'cq.id', '=', 'bq.customer_id')
                            ->whereColumn('bq.thread_id', 'a.id')
                            ->where(function ($w2) use ($q) {
                                $w2->where('cq.name', 'ilike', "%{$q}%")
                                    ->orWhere('cq.phone', 'ilike', "%{$q}%")
                                    ->orWhere('bq.item_content', 'ilike', "%{$q}%");
                            });
                    });
                });
            }
        }

        // ✅ FILTRO: assigned_agent_id (bot, holding, asignados)
        if ($assignedAgentIdMin !== null && $assignedAgentIdMax !== null) {
            // Rango específico: BETWEEN
            $base->whereBetween('a.assigned_agent_id', [$assignedAgentIdMin, $assignedAgentIdMax]);
        } elseif ($assignedAgentIdMin !== null) {
            // Solo mínimo: >=
            $base->where('a.assigned_agent_id', '>=', $assignedAgentIdMin);
        }

        // 7) Subquery: last message + customer + row_number (dedupe)
        $sub = $base
            ->leftJoin(DB::raw("
                LATERAL (
                    SELECT b.id as message_id,
                        b.item_content,
                        b.create_date as message_create_date,
                        b.external_id,
                        b.customer_id
                    FROM messages b
                    WHERE b.thread_id = a.id
                    ORDER BY b.id DESC
                    LIMIT 1
                ) last_msg
            "), DB::raw('true'), DB::raw('true'))
            ->leftJoin('customers as c', 'c.id', '=', 'last_msg.customer_id')
            ->select([
                'a.id as thread_id',
                'a.thread_status',
                'a.sender_id',
                'a.create_date',
                'a.origin',
                'a.first_conversation_date',
                'a.last_conversation_date',
                'c.name',
                'c.phone',
                'last_msg.item_content as last_message',
                'last_msg.message_create_date as last_at',
                DB::raw("
                    ROW_NUMBER() OVER (
                        PARTITION BY
                            COALESCE(c.id::text, a.sender_id, a.id::text),
                            a.company_id,
                            a.communication_channel_id
                        ORDER BY a.id DESC
                    ) as rn
                "),
            ]);

        // 8) Query final
        $rowsQuery = DB::query()
            ->fromSub($sub, 't')
            ->where('t.rn', 1)
            ->when($cursor, fn ($qq) => $qq->where('t.thread_id', '<', $cursor))
            ->orderByDesc('t.thread_id')
            ->limit($limit);

        $rows = $rowsQuery->get();
        $nextCursor = $rows->last()->thread_id ?? null;

        return response()->json([
            'data' => $rows,
            'next_cursor' => $nextCursor,
        ]);
    }

    public function messages(Request $request, int $threadId)
    {
        $this->authorize('viewAny', Thread::class);

        $data = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:300'],
            'cursor' => ['nullable', 'integer'], 
        ]);

        $limit  = (int) ($data['limit'] ?? 100);
        $cursor = isset($data['cursor']) ? (int) $data['cursor'] : null;

        $q = DB::table('messages as b')
        ->join('threads as a', 'a.id', '=', 'b.thread_id') // ✅ para company/canal
        ->leftJoin('customers as c', 'b.customer_id', '=', 'c.id')
        ->leftJoin('message_templates as mt', function ($join) {
            $join->on('mt.name', '=', DB::raw("
                CASE
                WHEN b.item_type = 'template'
                THEN (b.item_content::jsonb)->>'templateName'
                ELSE NULL
                END
            "))
            ->on('mt.company_id', '=', 'a.company_id') 
            ->on('mt.communication_channel_id', '=', 'a.communication_channel_id')
            ->where('mt.status_talina', true);
        })
        ->select([
            'b.id as message_id',
            'b.thread_id',
            'b.item_type',
            'b.item_content',
            'b.create_date as message_create_date',
            'b.origin as message_origin',
            'b.external_id',
            'c.name',
            'c.phone',
            'mt.components as template_components',
            DB::raw("
                case
                    when b.item_type = 'template' then mt.components
                    else to_jsonb(b.item_content)
                end as final_content
            "),
            DB::raw("
                case
                    when coalesce(b.external_id, '') <> '' then 'USUARIO'
                    else 'BOT'
                end as enviado_por
            "),
        ])
        ->where('b.thread_id', $threadId);

        if ($cursor) {
            $q->where('b.id', '<', $cursor);
        }

        $rows = $q->orderByDesc('b.id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        $rows->transform(function ($r) {
            $r->template_components = $r->template_components ? json_decode($r->template_components, true) : null;
            $r->final_content = $r->final_content ? json_decode($r->final_content, true) : null;
            return $r;
        });

        $nextCursor = $rows->first()->message_id ?? null;

        return response()->json([
            'data' => $rows,
            'next_cursor' => $nextCursor, 
        ]);
    }

    public function closeThread(Request $request, Thread $thread)
    {
        $this->authorize('close', $thread);

        // ya cerrado => no hacer nada (opcional)
        if (strtoupper($thread->thread_status) === 'CLOSED') {
            return response()->json([
                'message' => 'El thread ya está cerrado.',
                'thread_id' => $thread->id,
                'thread_status' => $thread->thread_status,
            ]);
        }

        $data = $request->validate([
            'tipificacion_id' => ['required', 'integer'],
        ]);

        $tipId = (int) $data['tipificacion_id'];

        // validar que la tipificación pertenezca al mismo company/canal del thread
        $ok = DB::table('chat_tipificaciones')
            ->where('id', $tipId)
            ->where('company_id', $thread->company_id)
            ->where('communication_channel_id', $thread->communication_channel_id)
            ->where('is_active', true)
            ->exists();

        if (!$ok) {
            return response()->json([
                'message' => 'Tipificación inválida para este thread.',
            ], 422);
        }

        DB::transaction(function () use ($request, $thread, $tipId) {
            // inserta registro de cierre (1 por thread)
            DB::table('thread_tipificaciones')->updateOrInsert(
                ['thread_id' => $thread->id],
                [
                    'tipificacion_id' => $tipId,
                    'user_id' => $request->user()->id,
                    'fecha_registro' => now(),
                ]
            );

            // cierra thread
            $thread->update(['thread_status' => 'CLOSED']);
        });

        return response()->json([
            'message' => 'Conversación cerrada exitosamente',
            'thread_id' => $thread->id,
            'thread_status' => 'CLOSED',
        ]);
    }

    public function agents(Request $request)
    {
        $this->authorize('viewAny', Thread::class);

        $data = $request->validate([
            'company_id'               => ['nullable', 'integer'],
            'communication_channel_id' => ['nullable', 'integer'],
            'date_start'               => ['nullable', 'date'],
            'date_end'                 => ['nullable', 'date'],
            'thread_status'            => ['nullable', 'in:ALL,OPEN,CLOSED'],
            'phone'                    => ['nullable', 'string'],
        ]);

        $companyId = isset($data['company_id']) ? (int) $data['company_id'] : null;
        $channelId = isset($data['communication_channel_id']) ? (int) $data['communication_channel_id'] : null;

        if ($companyId && $channelId) {
            $this->assertCompanyChannelAccess($request, $companyId, $channelId);
        }

        $dateStart = isset($data['date_start']) ? Carbon::parse($data['date_start'])->startOfDay() : null;
        $dateEnd = isset($data['date_end']) ? Carbon::parse($data['date_end'])->endOfDay() : null;
        $status = strtoupper(trim($data['thread_status'] ?? ''));
        $phone = trim($data['phone'] ?? '');

        $threadIds = DB::table('threads as a');

        if ($companyId) {
            $threadIds->where('a.company_id', $companyId);
        }

        if ($channelId) {
            $threadIds->where('a.communication_channel_id', $channelId);
        }

        if ($phone !== '') {
            $threadIds->where(function ($w) use ($phone) {
                $w->where('a.sender_id', 'ilike', "%{$phone}%")
                    ->orWhereExists(function ($sub) use ($phone) {
                        $sub->select(DB::raw(1))
                            ->from('messages as bq')
                            ->leftJoin('customers as cq', 'cq.id', '=', 'bq.customer_id')
                            ->whereColumn('bq.thread_id', 'a.id')
                            ->where('cq.phone', 'ilike', "%{$phone}%");
                    });
            });
        } else {
            $hasDates = ($dateStart && $dateEnd);

            if ($hasDates) {
                if ($status === 'OPEN') {
                    $threadIds->where('a.thread_status', 'OPEN');
                } elseif ($status === 'CLOSED') {
                    $threadIds->where('a.thread_status', 'CLOSED')
                        ->whereExists(function ($sub) use ($dateStart, $dateEnd) {
                            $sub->select(DB::raw(1))
                                ->from('messages as b')
                                ->whereColumn('b.thread_id', 'a.id')
                                ->whereBetween('b.create_date', [$dateStart, $dateEnd]);
                        });
                } else {
                    $threadIds->where(function ($w) use ($dateStart, $dateEnd) {
                        $w->whereExists(function ($sub) use ($dateStart, $dateEnd) {
                            $sub->select(DB::raw(1))
                                ->from('messages as b')
                                ->whereColumn('b.thread_id', 'a.id')
                                ->whereBetween('b.create_date', [$dateStart, $dateEnd]);
                        })->orWhere('a.thread_status', 'OPEN');
                    });
                }
            } else {
                if ($status === 'CLOSED') {
                    $threadIds->where('a.thread_status', 'CLOSED');
                } elseif ($status !== 'ALL') {
                    $threadIds->where('a.thread_status', 'OPEN');
                }
            }
        }

        $users = DB::table('users_laravel as u')
            ->joinSub($threadIds->select('a.assigned_agent_id')->distinct(), 'filtered_threads', function ($join) {
                $join->on('filtered_threads.assigned_agent_id', '=', 'u.id');
            })
            ->where('u.id', '>', 2)
            ->orderBy('u.name')
            ->get(['u.id', 'u.name']);

        return response()->json($users);
    }

    public function tipificaciones(Request $request)
    {
        $this->authorize('viewAny', Thread::class);

        $data = $request->validate([
            'company_id' => ['required', 'integer'],
            'communication_channel_id' => ['required', 'integer'],
        ]);

        $rows = DB::table('chat_tipificaciones')
            ->where('company_id', (int)$data['company_id'])
            ->where('communication_channel_id', (int)$data['communication_channel_id'])
            ->where('is_active', true)
            ->orderBy('tipificacion_1')
            ->orderBy('tipificacion_2')
            ->orderBy('tipificacion_3')
            ->get([
                'id',
                'tipificacion_1',
                'tipificacion_2',
                'tipificacion_3',
            ]);

        return response()->json(['data' => $rows]);
    }

    public function historyByPhone(Request $request)
    {
        $this->authorize('viewAny', Thread::class);

        $data = $request->validate([
            'company_id' => ['required', 'integer'],
            'communication_channel_id' => ['required', 'integer'],
            'phone' => ['required', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:300'],
            'cursor' => ['nullable', 'integer'], // b.id (message id)
        ]);

        $companyId = (int) $data['company_id'];
        $channelId = (int) $data['communication_channel_id'];
        $this->assertCompanyChannelAccess($request, $companyId, $channelId);
        $phone     = trim($data['phone']);
        $limit     = (int) ($data['limit'] ?? 100);
        $cursor    = isset($data['cursor']) ? (int) $data['cursor'] : null;

        $q = DB::table('threads as a')
        ->leftJoin('messages as b', 'a.id', '=', 'b.thread_id')
        ->leftJoin('customers as c', 'b.customer_id', '=', 'c.id')
        ->leftJoin('message_templates as mt', function ($join) {
            $join->on('mt.name', '=', DB::raw("
                CASE
                WHEN b.item_type = 'template'
                THEN (b.item_content::jsonb)->>'templateName'
                ELSE NULL
                END
            "))
            ->on('mt.company_id', '=', 'a.company_id') 
            ->on('mt.communication_channel_id', '=', 'a.communication_channel_id')
            ->where('mt.status_talina', true);
        })
        ->where('a.company_id', $companyId)
        ->where('a.communication_channel_id', $channelId)
        ->where('c.phone', $phone)
        ->when($cursor, fn ($qq) => $qq->where('b.id', '<', $cursor))
        ->orderByDesc('b.id')
        ->limit($limit)
        ->select([
            'b.id as message_id',
            'b.thread_id',
            'b.item_type',
            'b.item_content',
            'b.create_date as message_create_date',
            'b.origin as message_origin',
            'b.external_id',
            'c.name',
            'c.phone',
            'mt.components as template_components',
            DB::raw("
                case
                    when b.item_type = 'template' then mt.components
                    else to_jsonb(b.item_content)
                end as final_content
            "),
            DB::raw("
                case
                    when coalesce(b.external_id, '') <> '' then 'USUARIO'
                    else 'BOT'
                end as enviado_por
            "),
        ]);

        $rows = $q->get();

        $rows->transform(function ($r) {
            $r->template_components = $r->template_components ? json_decode($r->template_components, true) : null;
            $r->final_content = $r->final_content ? json_decode($r->final_content, true) : null;
            return $r;
        });

        $nextCursor = $rows->last()->message_id ?? null;

        return response()->json([
            'data' => $rows,
            'next_cursor' => $nextCursor,
        ]);
    }

}
