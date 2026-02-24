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
        // 1) Permiso
        $this->authorize('viewAny', Thread::class);

        // 2) Validación de params
        $data = $request->validate([
            'company_id' => ['required', 'integer'],
            'communication_channel_id' => ['required', 'integer'],
            'date_start' => ['nullable', 'date'],
            'date_end' => ['nullable', 'date'],
            'q' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'cursor' => ['nullable', 'integer'],
        ]);

        // 3) Normalización
        $companyId = (int) $data['company_id'];
        $channelId = (int) $data['communication_channel_id'];
        $dateStart = isset($data['date_start']) ? Carbon::parse($data['date_start'])->startOfDay() : null;
        $dateEnd   = isset($data['date_end']) ? Carbon::parse($data['date_end'])->endOfDay() : null;
        $q         = trim($data['q'] ?? '');
        $limit     = (int) ($data['limit'] ?? 60);
        $cursor    = isset($data['cursor']) ? (int) $data['cursor'] : null;

        // 4) Base query: threads filtrados por company y canal
        //    (OJO: aquí NO aplicamos cursor)
        $base = DB::table('threads as a')
            ->where('a.company_id', $companyId)
            ->where('a.communication_channel_id', $channelId);

        // 5) Filtro por fecha (sobre mensajes) pero manteniendo OPEN
        if ($dateStart && $dateEnd) {
            $base->where(function ($w) use ($dateStart, $dateEnd) {
                $w->whereExists(function ($sub) use ($dateStart, $dateEnd) {
                    $sub->select(DB::raw(1))
                        ->from('messages as b')
                        ->whereColumn('b.thread_id', 'a.id')
                        ->whereBetween('b.create_date', [$dateStart, $dateEnd]);
                })->orWhere('a.thread_status', 'OPEN');
            });
        } else {
            $base->where('a.thread_status', 'OPEN');
        }

        // 6) Búsqueda opcional (nombre/phone/contenido)
        if ($q !== '') {
            $base->whereExists(function ($sub) use ($q) {
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
        }

        // 7) Subquery: last message + customer + row_number (dedupe por cliente)
        //    rn = 1 => el thread más reciente para ese cliente en ese canal+company
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
                'a.first_conversation_date',
                'a.last_conversation_date',
                'c.name',
                'c.phone',
                'last_msg.item_content as last_message',
                'last_msg.message_create_date as last_at',
                DB::raw("
                    ROW_NUMBER() OVER (
                        PARTITION BY
                            c.id,               -- dedupe por customer
                            a.company_id,
                            a.communication_channel_id
                        ORDER BY a.id DESC
                    ) as rn
                "),
            ]);

        // 8) Query final: nos quedamos solo con rn=1 (uno por cliente)
        //    y recién aquí aplicamos cursor para paginar sin repetir contactos
        $rowsQuery = DB::query()
            ->fromSub($sub, 't')
            ->where('t.rn', 1)
            ->when($cursor, fn ($qq) => $qq->where('t.thread_id', '<', $cursor))
            ->orderByDesc('t.thread_id')
            ->limit($limit);

        $rows = $rowsQuery->get();

        // 9) next_cursor
        $nextCursor = $rows->last()->thread_id ?? null;

        // 10) Response
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
            ->leftJoin('customers as c', 'b.customer_id', '=', 'c.id')
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

}
