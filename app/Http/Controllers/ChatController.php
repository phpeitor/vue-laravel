<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Company;

class ChatController extends Controller
{
    /**
     * Devuelve filas (1 por mensaje) como tu query original.
     * Vue agrupa por thread_id para pintar sidebar + mensajes.
     */
    public function threads(Request $request)
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer'],
            'communication_channel_id' => ['required', 'integer'],
            'date_start' => ['nullable', 'date'],
            'date_end' => ['nullable', 'date'],
        ]);

        $companyId = (int) $data['company_id'];
        $channelId = (int) $data['communication_channel_id'];
        $dateStart = $data['date_start'] ?? null;
        $dateEnd   = $data['date_end'] ?? null;

        $q = DB::table('threads as a')
            ->leftJoin('messages as b', 'a.id', '=', 'b.thread_id')
            ->leftJoin('customers as c', 'b.customer_id', '=', 'c.id')
            ->select([
                'a.id',
                'a.company_id',
                'a.communication_channel_id',
                'a.assigned_agent_id',
                'a.thread_status',
                'a.first_conversation_date',
                'a.last_conversation_date',
                'a.create_date as thread_create_date',
                'a.sender_id',
                'a.origin as thread_origin',
                'a.last_outgoing_message_id',

                'b.id as message_id',
                'b.thread_id',
                'b.item_type',
                'b.item_content',
                'b.create_date as message_create_date',
                'b.origin as message_origin',
                'b.external_id',

                'c.name',
                'c.phone',
                'c.create_date as customer_create_date',

                DB::raw("
                    case
                        when coalesce(b.external_id, '') <> '' then 'USUARIO'
                        else 'BOT'
                    end as enviado_por
                "),
            ])
            ->where('a.company_id', $companyId)
            ->where('a.communication_channel_id', $channelId)
            ->where(function ($w) use ($dateStart, $dateEnd) {
                // (b.create_date between start and end) OR thread_status = 'OPEN'
                if ($dateStart && $dateEnd) {
                    $w->whereBetween('b.create_date', [$dateStart, $dateEnd])
                      ->orWhere('a.thread_status', 'OPEN');
                } else {
                    // si no mandan fechas, al menos OPEN
                    $w->where('a.thread_status', 'OPEN');
                }
            })
            ->orderByDesc('a.id')
            ->orderBy('b.id', 'asc');

        return response()->json($q->get());
    }

    /**
     * Opcional: traer mensajes de un hilo puntual (si luego lo necesitas)
     */
    public function messages(Request $request, int $threadId)
    {
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
            ->where('b.thread_id', $threadId)
            ->orderBy('b.id', 'asc');

        return response()->json($q->get());
    }
}
