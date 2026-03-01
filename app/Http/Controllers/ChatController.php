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
    
    public function index()
    {
        $this->authorize('viewAny', Thread::class);

        return Inertia::render('Chat/Index', [
            'companies' => Company::select('id', 'company_name')
                ->orderBy('company_name')
                ->get(),
        ]);
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
        ]);

        $companyId = (int) $data['company_id'];
        $channelId = (int) $data['communication_channel_id'];
        $dateStart = isset($data['date_start']) ? Carbon::parse($data['date_start'])->startOfDay() : null;
        $dateEnd   = isset($data['date_end']) ? Carbon::parse($data['date_end'])->endOfDay() : null;
        $q         = trim($data['q'] ?? '');
        $qBy       = strtoupper(trim($data['q_by'] ?? 'ALL'));
        $status    = strtoupper(trim($data['thread_status'] ?? '')); // '', OPEN, CLOSED, ALL
        $limit     = (int) ($data['limit'] ?? 60);
        $cursor    = isset($data['cursor']) ? (int) $data['cursor'] : null;

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

        $thread->update(['thread_status' => 'CLOSED']);

        return response()->json([
            'message' => 'Conversación cerrada exitosamente',
            'thread_id' => $thread->id,
            'thread_status' => $thread->thread_status,
        ]);
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
