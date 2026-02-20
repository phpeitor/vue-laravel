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
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'cursor' => ['nullable', 'integer'], // cursor por a.id (thread id)
        ]);

        $companyId = (int) $data['company_id'];
        $channelId = (int) $data['communication_channel_id'];
        $dateStart = isset($data['date_start'])
            ? Carbon::parse($data['date_start'])->startOfDay()
            : null;

        $dateEnd = isset($data['date_end'])
            ? Carbon::parse($data['date_end'])->endOfDay() // 👈 23:59:59
            : null;
        $q         = trim($data['q'] ?? '');
        $limit     = (int) ($data['limit'] ?? 60);
        $cursor    = isset($data['cursor']) ? (int) $data['cursor'] : null;

        $base = DB::table('threads as a')
            ->where('a.company_id', $companyId)
            ->where('a.communication_channel_id', $channelId);

        // Cursor: trae threads menores al cursor (infinite scroll)
        if ($cursor) {
            $base->where('a.id', '<', $cursor);
        }

        // ✅ Filtro por fechas sobre mensajes, pero manteniendo OPEN
        if ($dateStart && $dateEnd) {
            $base->where(function ($w) use ($dateStart, $dateEnd) {
                $w->whereExists(function ($sub) use ($dateStart, $dateEnd) {
                    $sub->select(DB::raw(1))
                        ->from('messages as b')
                        ->whereColumn('b.thread_id', 'a.id')
                        ->whereBetween('b.create_date', [$dateStart, $dateEnd]);
                })
                ->orWhere('a.thread_status', 'OPEN');
            });
        } else {
            // si no mandan fechas, mínimo OPEN (o quítalo si quieres todos)
            $base->where('a.thread_status', 'OPEN');
        }

        // búsqueda (opcional)
        if ($q !== '') {
            $base->where(function ($w) use ($q) {
                $w->whereExists(function ($sub) use ($q) {
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

        // ✅ Último mensaje por thread (Postgres)
        $rows = $base
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
            ])
            ->orderByDesc('a.id')
            ->limit($limit)
            ->get();

        // next_cursor = último thread_id del lote
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
            'cursor' => ['nullable', 'integer'], // para “cargar más” hacia arriba
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

        // Si cursor viene, trae mensajes “anteriores” (más viejos)
        if ($cursor) {
            $q->where('b.id', '<', $cursor);
        }

        // para infinite scroll hacia arriba: pedimos DESC, luego invertimos
        $rows = $q->orderByDesc('b.id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        $nextCursor = $rows->first()->message_id ?? null;

        return response()->json([
            'data' => $rows,
            'next_cursor' => $nextCursor, // úsalo para pedir más viejos
        ]);
    }

}
