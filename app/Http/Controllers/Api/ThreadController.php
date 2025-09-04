<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ThreadController extends Controller
{

    public function show($id)
    {
        if (!is_numeric($id)) {
            return response()->json(['error' => 'El ID debe ser un number'], 400);
        }

       $cliente = DB::select("
            SELECT id, close_type, sender_id, first_conversation_date, 
                last_conversation_date, thread_status,
                communication_channel_id, company_id, total_duration
            FROM public.threads
            WHERE id = ?
        ", [$id]);

        if (!$cliente) {
            $data = [
                'message' => 'Cliente no encontrado',
                'status' => 404
            ];
            return response()->json($data, 404);
        }

        $cliente = $cliente[0];

        $data = [
            'id' => $cliente->id,
            'close_type' => $cliente->close_type,
            'sender_id' => $cliente->sender_id,
            'thread_status' => $cliente->thread_status,
            'first_conversation_date' => $cliente->first_conversation_date,
            'last_conversation_date' => $cliente->last_conversation_date,
            'total_duration' => $cliente->total_duration,
            'communication_channel_id' => $cliente->communication_channel_id,
            'company_id' => $cliente->company_id
        ];

        return response()->json(['cliente' => $data, 'status' => 200], 200);
    }

}