<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Thread;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatReplyController extends Controller
{
    public function store(Request $request, int $threadId)
    {
        $data = $request->validate([
            'message'     => ['required', 'string'],
            'messageType' => ['required', 'string'], // "text"
            'userId'      => ['required', 'integer'],
        ]);

        $base = rtrim((string) config('services.chat.thread_base_url'), '/');
        if ($base === '') {
            Log::error('CHAT_THREAD_BASE_URL no configurado', [
                'services.chat.thread_base_url' => config('services.chat.thread_base_url'),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'CHAT_THREAD_BASE_URL no configurado en config/services.php',
            ], 500);
        }

        $url = "{$base}/{$threadId}/reply";

        try {
            // 👇 Si en local tienes cURL error 60 (certificados), habilita verify false SOLO en local
            $http = Http::timeout(20)->acceptJson();

            if (app()->environment('local')) {
                $http = $http->withOptions(['verify' => false]);
            }

            $res = $http->post($url, $data);
        } catch (\Throwable $e) {
            Log::error('Error llamando omnichannel reply', [
                'url' => $url,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'No se pudo conectar al omnichannel',
            ], 502);
        }

        if (!$res->successful()) {
            Log::error('Omnichannel reply no successful', [
                'url' => $url,
                'payload_sent' => $data,
                'status' => $res->status(),
                'body' => $res->body(),
            ]);

            return response()->json([
                'success' => false,
                'status' => $res->status(),
                'error' => $res->json() ?? $res->body(),
            ], 422);
        }

        $payload = $res->json();

        if (!is_array($payload) || !($payload['success'] ?? false) || empty($payload['interaction'])) {
            Log::warning('Respuesta omnichannel inesperada', ['payload' => $payload]);

            return response()->json([
                'success' => false,
                'error' => 'Respuesta inesperada del omnichannel',
                'raw' => $payload,
            ], 422);
        }

        $interaction = $payload['interaction'];
        $messageId = (int) ($interaction['id'] ?? 0);

        // ✅ NO INSERTAR mensaje, SOLO actualizar origin del mensaje ya creado por omnichannel
        if ($messageId > 0) {
            $updated = Message::query()
                ->where('thread_id', $threadId)
                ->where('id', $messageId)
                ->update(['origin' => 'APP']);

            if ($updated === 0) {
                // Si no existe aún por timing, lo logueamos (puedes reintentar en frontend con fetchMessages)
                Log::warning('No se encontró message para actualizar origin', [
                    'thread_id' => $threadId,
                    'message_id' => $messageId,
                ]);
            }

            // opcional: marcar en threads last_outgoing_message_id si lo usas
            Thread::query()
                ->where('id', $threadId)
                ->update(['last_outgoing_message_id' => $messageId]);
        }

        return response()->json($payload);
    }
}
